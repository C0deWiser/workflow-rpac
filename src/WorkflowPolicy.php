<?php

namespace Codewiser\Workflow\Rpac;

use Codewiser\Workflow\Rpac\Traits\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Trunow\Rpac\Permission;

/**
 * This policy may authorise user to perform Transitions. Also, it gives respect to models states.
 * @package Codewiser\Workflow\Rpac
 */
abstract class WorkflowPolicy extends \Trunow\Rpac\Policies\RpacPolicy
{
    public function transit(?User $user, Model $model, string $workflow, string $target)
    {
        return $this->authorizeTransition($user, $model, $workflow, $target);
    }

    protected function applyScope($action, ?User $user)
    {
        // without workflow we construct Scope that way
        // we get all positive permissions with Model+Action signature
        // we get Relationships from those permissions
        // we apply Relationship Scopes, so Collection got models from those scopes

        $workflowList = $this->getModelWorkflowList();

        if (!$workflowList) {
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

        $model::addGlobalScope($globalScopeName, function (Builder $query) use ($workflowList, $action, $user, $keyName) {
            $scoped = false;
            foreach ($workflowList as $workflow) {
                foreach ($workflow->getStates() as $state) {
                    $signature = "{$this->getNamespace()}({$workflow->getAttributeName()}:{$state}):{$action}";
                    if ($this->getPermissions($signature, $this->getRoles($user))->count()) {
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
     * @return \Illuminate\Support\Collection|WorkflowBlueprint[]
     */
    protected function getModelWorkflowList(Model $model = null)
    {
        $w = [];
        if (!$model) {
            $modelClass = $this->model();
            $model = new $modelClass();
        }

        if (method_exists($model, 'workflow')) {
            /* @var Model $model */
            foreach (array_keys($model->getAttributes()) as $attribute) {
                if ($workflow = $model->workflow($attribute)) {
                    $w[] = $workflow;
                }
            }
        }
        return new \Illuminate\Support\Collection($w);
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

        if (!$this->getModelWorkflowList()) {
            // Has no workflow
            return false;
        } elseif (!($workflow = $model->workflow($workflow))) {
            // Has no such workflow
            return false;
        }

        $default = null;
        $roles = $this->getRoles($user, $model);

        foreach ($roles as $role) {
            if (!is_null($rule = $workflow->getPermission($target, $role))) {
                $default = $rule ? : ($default ? : $rule);
            }
        }

        if (is_null($default)) {
            // There is no default rule
            $signature = "{$this->getNamespace()}({$workflow->getAttributeName()}:{$workflow->getState()}):transit({$target})";
            $permissions = $this->getPermissions($signature, $roles);

            // If we have at least one permission, then User allowed to action
            return (boolean)$permissions->count();
        } else {
            return $default;
        }
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
        if ($model && ($workflowList = $this->getModelWorkflowList($model))) {
            // It is enough for user to have access to model through any workflow
            // So, we return few signatures any of which gives access
            foreach ($workflowList as $workflow) {
                $signatures[] = "{$this->getNamespace()}({$workflow->getAttributeName()}:{$workflow->getState()}):{$action}";
            }
        } else {
            $signatures[] = parent::getSignature($action);
        }
        return $signatures;
    }

    /**
     * Return permissions for signatures and roles
     * @param array|string $signature
     * @param array|string $roles
     * @return Collection|Permission[]|Collection
     */
    protected function getPermissions($signature, $roles)
    {
        // Take permissions with signature and user role
        $permissions = Permission::cached()->filter(
            function (Permission $perm) use ($signature, $roles) {
                return (
                    in_array($perm->signature, (array)$signature) &&
                    in_array($perm->role, (array)$roles)
                );
            }
        );
//        dump($permissions->toArray());
        return $permissions;
    }
}