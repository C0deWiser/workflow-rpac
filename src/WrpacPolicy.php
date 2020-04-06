<?php

namespace Codewiser\Workflow\Rpac;

use Codewiser\Rpac\Policies\RpacPolicy;
use Codewiser\Workflow\Rpac\Traits\Workflow;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Contracts\Auth\Authenticatable as User;
use Codewiser\Rpac\Permission;

/**
 * This policy may authorise user to perform Transitions. Also, it gives respect to models states.
 * @package Codewiser\Workflow\Rpac
 */
abstract class WrpacPolicy extends RpacPolicy
{
    public function transit(?User $user, Model $model, string $workflow, string $target)
    {
        return $this->authorize('transit', $user, $model, $workflow, $target);
    }

    /**
     * Default (built-in) permissions
     * @param string $action
     * @param string|null $workflow
     * @param string|null $state
     * @return array|string|null|void return namespaced(!) roles, allowed to $action
     */
    abstract public function permissions($action, $workflow = null, $state = null);

    /**
     * @inheritDoc
     * @param Model|Workflow $model
     */
    protected function authorize($action, ?User $user, Model $model = null, $workflow = null, $target = null)
    {
        if ($action == 'transit') {
            return $this->authorizeTransition($user, $model, $workflow, $target);
        }

        if ($model) {
            $roles = $this->getUserRoles($user, $model);

            // Iterate each workflow
            foreach ($model->getWorkflowListing() as $workflow) {

                $permissions = $this->getPermissions($action, $workflow, $workflow->getState());

                if (in_array('*', $permissions)
                    ||
                    array_intersect($roles, $permissions)) {
                    return true;
                }
            }
            return false;
        } else {
            return parent::authorize($action, $user, $model);
        }
    }

    /**
     * Checks User ability tr perform Workflow Transition in Model
     * @param User|Model|null $user
     * @param Model|Workflow $model
     * @param string $workflow
     * @param string $target
     * @return bool
     */
    private function authorizeTransition(?User $user, Model $model, string $workflow, string $target)
    {
        // Signature for this action is
        // Model+Workflow+SourceState+TargetState
        // signature: Model(workflowName:state):transit(newState)

        if (!($workflow = $model->workflow($workflow))) {
            // Has no such workflow
            return false;
        }

        $source = $workflow->getState();
        $roles = $this->getUserRoles($user, $model);

        $permissions = $this->getPermissions('transit', $workflow, $source, $target);

        return
            in_array('*', $permissions)
            ||
            array_intersect($roles, $permissions);
    }

    /**
     * Model with multiple workflow may has multiple signatures.
     * Signature is Model + [Workflow + State] + Action + [Transition] string
     * @param string $action
     * @param string $workflow
     * @param string $currentState
     * @param string $targetState
     * @return string
     */
    protected function getSignature($action, $workflow = null, $currentState = null, $targetState = null)
    {
        if ($workflow && $currentState) {
            if ($targetState) {
                // This is action=transit
                // Model(workflowName:state):action(newState)
                return "{$this->getNamespace()}({$workflow}:{$currentState}):{$action}({$targetState})";
            } else {
                // Model(workflowName:state):action
                return "{$this->getNamespace()}({$workflow}:{$currentState}):{$action}";
            }
        } else {
            // Model:action
            return parent::getSignature($action);
        }
    }

    /**
     * Get roles for signature
     * @param string $action
     * @param StateMachineEngine $workflow if model has workflow
     * @param string $currentState current state
     * @param string $targetState target state (for transition)
     * @return array
     */
    public function getPermissions($action, StateMachineEngine $workflow = null, $currentState = null, $targetState = null)
    {
        $signature = $this->getSignature($action, $workflow ? $workflow->getAttributeName() : null, $currentState, $targetState );

        // Take permissions with signature and user role
        $permissions = Permission::cached()->filter(
            function (Permission $perm) use ($signature) {
                return ($perm->signature == $signature);
            }
        );

        if ($targetState) {
            $defaults = $workflow->getBlueprint()->permissions($currentState, $targetState);
        } else {
            $defaults = $this->permissions($action, $workflow ? $workflow->getAttributeName() : null, $currentState);
        }

        return array_merge(
            (array)$defaults,
            (array)$permissions->pluck('role')->toArray()
        );
    }

    /**
     * Get listing of transitions, available for given User
     *
     * [workflow_attr => [transition_1, transition_2]]
     *
     * @param User|null $user
     * @param Model|Workflow $model
     * @return array
     */
    public function getTransitions(?User $user, Model $model)
    {
        $transitions = [];

        foreach ($model->getWorkflowListing() as $workflow) {
            $w = $workflow->getAttributeName();
            $transitions[$w] = [];
            foreach ($workflow->getRelevantTransitions() as $transition) {
                if ($this->authorizeTransition($user, $model, $w, $transition->getTarget())) {
                    $transitions[$w][] = $transition->toArray();
                }
            }
        }

        return $transitions;
    }
}