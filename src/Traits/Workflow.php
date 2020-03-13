<?php
namespace Codewiser\Workflow\Rpac\Traits;

use Codewiser\Workflow\Rpac\WorkflowBlueprint;

/**
 * Trait extends Workflow with permissions (who allowed to perform transitions?)
 * @package Codewiser\Workflow\Rpac\Traits
 */
trait Workflow
{
    use \Codewiser\Workflow\Traits\Workflow {
        \Codewiser\Workflow\Traits\Workflow::workflow as protected parentWorkflow;
    }

    /**
     * @inheritDoc
     * @return WorkflowBlueprint|null
     */
    public function workflow($what = null)
    {
        return $this->parentWorkflow($what);
    }
}