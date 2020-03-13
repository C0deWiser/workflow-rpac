<?php
namespace Codewiser\Workflow\Rpac\Helpers;

use Codewiser\Workflow\Rpac\Traits\Workflow;
use Codewiser\Workflow\Rpac\WorkflowPolicy;
use Illuminate\Database\Eloquent\Model;

class ReflectionHelper extends \Codewiser\Rpac\Helpers\ReflectionHelper
{
    /**
     * @param $policy
     * @return Model|Workflow
     */
    protected function getModel($policy)
    {
        /** @var WorkflowPolicy $policy */
        $policy = new $policy();
        $className = $policy->model();
        /** @var Model|Workflow $model */
        return new $className();
    }

    /**
     * Get workflow listing of given policy (means model)
     * @param string $policy
     * @return array|string[]
     * @example [editorial_workflow, ...]
     */
    public function getFlows($policy)
    {
        $model = $this->getModel($policy);

        $flows = [];

        if (method_exists($model, 'getWorkflowListing')) {
            foreach ($model->getWorkflowListing() as $workflow) {
                $flows[] = $workflow->getAttributeName();
            }
        }

        return $flows;
    }

    /**
     * Get list of states of given policy (model) workflow
     * @param string $policy
     * @param string $workflow
     * @return array|string[]
     * @example [new, view, review, ...]
     */
    public function getStates($policy, $workflow)
    {
        $model = $this->getModel($policy);

        if (method_exists($model, 'workflow')) {
            $workflow = $model->workflow($workflow);
            return $workflow->getStates()->toArray();
        } else {
            return [];
        }
    }

    /**
     * Get list of transitions of given policy (model) workflow
     * @param string $policy
     * @param string $workflow
     * @return array|array[]
     * @example [[source, target], ...]
     */
    public function getTransitions($policy, $workflow)
    {
        $model = $this->getModel($policy);

        if (method_exists($model, 'workflow')) {
            $workflow = $model->workflow($workflow);
            return $workflow->getTransitions()->toArray();
        } else {
            return [];
        }
    }

    /**
     * Return default rule. If set, you can not override it from user interface
     * @param string $policy
     * @param string $action
     * @param string $role
     * @param string|null $workflow
     * @param string|null $state
     * @return bool|null
     */
    public function getBuiltInPermission($policy, $action, $role, $workflow = null, $state = null)
    {
        /** @var WorkflowPolicy $policy */
        $policy = new $policy();
        return $policy->getPermission($action, $role, $workflow, $state);
    }

    /**
     * Return default permission to perform transition. If set, you can not override it from user interface
     * @param string $policy
     * @param string $role
     * @param string $workflow
     * @param array $transition
     * @return bool|null
     */
    public function getTransitionBuiltInPermission($policy, $role, $workflow, $transition)
    {
        $model = $this->getModel($policy);

        if (method_exists($model, 'workflow')) {
            $workflow = $model->workflow($workflow);
            return $workflow->getPermission($transition[0], $transition[1], $role);
        } else {
            return null;
        }
    }
}