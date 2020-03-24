<?php
namespace Codewiser\Workflow\Rpac\Helpers;

use Codewiser\Workflow\Rpac\Traits\Workflow;
use Codewiser\Workflow\Rpac\WrpacPolicy;
use Illuminate\Database\Eloquent\Model;

class WrpacHelper extends \Codewiser\Rpac\Helpers\RpacHelper
{
    /**
     * @var WrpacPolicy
     */
    protected $policy;
    /**
     * @return Model|Workflow
     */
    protected function getModel()
    {
        $className = $this->model;
        /** @var Model|Workflow $model */
        return new $className();
    }

    /**
     * Get workflow listing of given policy (means model)
     * @return array|string[]
     * @example [editorial_workflow, ...]
     */
    public function getFlows()
    {
        $model = $this->getModel();

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
     * @param string $workflow
     * @return array|string[]
     * @example [new, view, review, ...]
     */
    public function getStates($workflow)
    {
        $model = $this->getModel();

        if (method_exists($model, 'workflow')) {
            $workflow = $model->workflow($workflow);
            return $workflow->getStates()->toArray();
        } else {
            return [];
        }
    }

    /**
     * Get list of transitions of given policy (model) workflow
     * @param string $workflow
     * @return array|array[]
     * @example [[source, target], ...]
     */
    public function getTransitions($workflow)
    {
        $model = $this->getModel();

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
     * @return bool
     */
    public function getBuiltInPermission($action, $role, $workflow = null, $state = null)
    {
        $defaults = $this->policy->getDefaults($action, $workflow, $state);
        return $defaults == '*' || in_array($role, (array)$defaults);
    }

    /**
     * Return default permission to perform transition. If set, you can not override it from user interface
     * @param string $policy
     * @param string $role
     * @param string $workflow
     * @param array $transition
     * @return bool|void
     */
    public function getTransitionBuiltInPermission($policy, $role, $workflow, $transition)
    {
        $model = $this->getModel();

        if (method_exists($model, 'workflow')) {
            $workflow = $model->workflow($workflow);
            $defaults = $workflow->getDefaults($transition[0], $transition[1]);
            return $defaults == '*' || in_array($role, (array)$defaults);
        }
    }
}