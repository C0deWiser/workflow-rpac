<?php


namespace Codewiser\Workflow\Rpac;

/**
 * Extends StateMachineEngine with permissions
 * @package Codewiser\Workflow\Rpac
 */
class StateMachineEngine extends \Codewiser\Workflow\StateMachineEngine
{
    /**
     * @inheritDoc
     * @return WorkflowBlueprint
     */
    public function getBlueprint()
    {
        return parent::getBlueprint();
    }
}