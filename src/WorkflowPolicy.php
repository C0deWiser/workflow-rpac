<?php

namespace Codewiser\Workflow\Rpac;

use Codewiser\Rpac\Policies\RpacPolicy;
use Codewiser\Workflow\Rpac\Traits\Workflow;
use Codewiser\Workflow\Transition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Codewiser\Rpac\Permission;

/**
 * This policy may authorise user to perform Transitions. Also, it gives respect to models states.
 * @package Codewiser\Workflow\Rpac
 */
abstract class WorkflowPolicy extends RpacPolicy
{
    public function transit(?User $user, Model $model, string $workflow, string $target)
    {
        return $this->authorizeTransition($user, $model, $workflow, $target);
    }

    /**
     * Default (built-in) permissions
     * @param string $action
     * @param string|null $workflow
     * @param string|null $state
     * @return array|string|null|void return namespaced(!) roles, allowed to $action
     */
    public function getDefaults($action, $workflow = null, $state = null)
    {
    }

    /**
     * @inheritDoc
     * @param Model|Workflow $model
     */
    protected function authorize($action, ?User $user, Model $model = null)
    {
        $roles = $this->getUserRoles($user, $model);

        if ($model && ($workflowListing = $this->getWorkflowListing($model))) {
            // Iterate each workflow
            foreach ($workflowListing as $workflow) {

                // Check default permissions
                $permittedRoles = $this->getDefaults($action, $workflow->getAttributeName(), $workflow->getState());
                if (array_intersect($roles, $permittedRoles)) {
                    return true;
                }

                // Check permissions from database
                $permittedRoles = $this->getPermissions($this->getSignature($action, $model));
                if (array_intersect($roles, $permittedRoles)) {
                    return true;
                }
            }
            return false;
        } else {
            return parent::authorize($action, $user, $model);
        }
    }

    protected function applyScope($action, ?User $user)
    {
        // without workflow we construct Scope that way
        // we get all positive permissions with Model+Action signature
        // we get Relationships from those permissions
        // we apply Relationship Scopes, so Collection got models from those scopes

        $workflowListing = $this->getWorkflowListing();

        if (!$workflowListing) {
            parent::applyScope($action, $user);
            return;
        }

        // with workflow we should detect, what workflow states user is permitted to $action
        // first we check Permissions for all user concrete Roles
        // second, check user Relationships

        /*
         * Example query
         *
         * where
         *      workflow_1_attr='new' // if user.roles allows him to see those records
         *      or
         *      workflow_2_attr='review'
         *      or
         *      (   // user has access to `correct` state as `owner`
         *          workflow_1_attr='correct'
         *          and
         *          owner_id={user_id}
         *      )
         *
         */

        $modelClass = $this->model();
        $model = new $modelClass();
        $globalScopeName = "{$this->model()}\\{$action}";
        $keyName = (new $model())->getKeyName();

        $model::addGlobalScope($globalScopeName, function (Builder $query) use ($workflowListing, $action, $user, $keyName) {
            $scoped = false;
            $roles = $this->getUserRoles($user);
            foreach ($workflowListing as $workflow) {
                foreach ($workflow->getStates() as $state) {
                    $signature = "{$this->getNamespace()}({$workflow->getAttributeName()}:{$state}):{$action}";
                    if (array_intersect($roles, $this->getPermissions($signature))) {
                        // user permitted to $action this workflow:state in model
                        // add it to the global scope
                        $query->orWhere($workflow->getAttributeName(), $state);
                        $scoped = true;
                    } elseif ($user && ($relationships = $this->getRelationshipsForSignature($signature))) {
                        // get user Relationships permitted to $action
                        // if any, then add relationship scope to global scope
                        foreach ($relationships as $relationship) {
                            $scopeName = $this->getScopeName($relationship);
                            $query->orWhere(function (Builder $query) use ($workflow, $scopeName, $user) {
                                $query
                                    ->where($workflow->getAttributeName(), $workflow->getState())
                                    ->$scopeName($user);
                            });
                        }
                        $scoped = true;
                    }
                }
            }
            // if user has no access neither by concrete roles, neither by relationships
            // then apply empty scope
            if (!$scoped) {
                $query->where($keyName, 0);
            }
        });
    }

    /**
     * Model's workflow
     * @param Model|Workflow $model
     * @return \Illuminate\Support\Collection|WorkflowBlueprint[]
     */
    protected function getWorkflowListing(Model $model = null)
    {
        if (!$model) {
            $modelClass = $this->model();
            /** @var Model|Workflow $model */
            $model = new $modelClass();
        }

        if (method_exists($model, 'getWorkflowListing')) {
            return collect($model->getWorkflowListing());
        }

        return collect();
    }

    /**
     * Checks User ability tr perform Workflow Transition in Model
     * @param User|null $user
     * @param Model|Workflow $model
     * @param string $workflow
     * @param string $target
     * @return bool
     */
    protected function authorizeTransition(?User $user, Model $model, string $workflow, string $target)
    {
        // Signature for this action is
        // Model+Workflow+SourceState+TargetState
        // signature: Model(workflowName:state):transit(newState)

        if (!$this->getWorkflowListing()) {
            // Has no workflow
            return false;
        } elseif (!($workflow = $model->workflow($workflow))) {
            // Has no such workflow
            return false;
        }

        $default = null;
        $source = $workflow->getState();
        $roles = $this->getUserRoles($user, $model);

        if (array_intersect($roles, (array)$workflow->getDefaults($source, $target))) {
            return true;
        }

        // There is no default rule
        $signature = "{$this->getNamespace()}({$workflow->getAttributeName()}:{$source}):transit({$target})";
        if (array_intersect($roles, $this->getPermissions($signature))) {
            return true;
        }

        return false;
    }

    /**
     * Model with multiple workflow may has multiple signatures.
     * Signature is Model+Workflow+State+Action string
     * @param string $action
     * @param Model|Workflow $model
     * @return array|string[]
     */
    protected function getSignature($action, Model $model = null)
    {
        $signatures = [];
        if ($model && ($workflowListing = $this->getWorkflowListing($model))) {
            // It is enough for user to have access to model through any workflow
            // So, we return few signatures any of which gives access
            foreach ($workflowListing as $workflow) {
                $signatures[] = "{$this->getNamespace()}({$workflow->getAttributeName()}:{$workflow->getState()}):{$action}";
            }
        } else {
            $signatures[] = parent::getSignature($action);
        }
        return $signatures;
    }

    /**
     * Get roles for signature
     * @param array|string $signature
     * @return array
     */
    protected function getPermissions($signature)
    {
        // Take permissions with signature and user role
        $permissions = Permission::cached()->filter(
            function (Permission $perm) use ($signature) {
                return (in_array($perm->signature, (array)$signature));
            }
        );
        return $permissions->pluck('role')->toArray();
    }

    /**
     * Get listing of transitions, available for given User
     *
     * [workflow_attr => [transition_1, transition_2]]
     *
     * @param User|null $user
     * @param Model|Workflow $model
     * @return Collection
     */
    public function getTransitions(?User $user, Model $model)
    {
        $transitions = [];

        foreach ($model->getWorkflowListing() as $workflow) {
            $w = $workflow->getAttributeName();
            $transitions[$w] = [];
            foreach ($workflow->getRelevantTransitions() as $transition) {
                if ($this->authorizeTransition($user, $model, $w, $transition->getTarget())) {
                    $transitions[$w] = $transition->toArray();
                }
            }
        }

        return collect($transitions);
    }
}