<?php
namespace Codewiser\Workflow\Rpac\Traits;

use Codewiser\Workflow\Rpac\WorkflowBlueprint;
use Illuminate\Support\Collection;

/**
 * Trait extends Workflow with permissions (who allowed to perform transitions?)
 * @package Codewiser\Workflow\Rpac\Traits
 */
trait Workflow
{
    use \Codewiser\Workflow\Traits\Workflow {
        workflow as private parentWorkflow;
        getWorkflowListing as private getParentWorkflowListing;
    }

    /**
     * @inheritDoc
     * @return WorkflowBlueprint|null
     */
    public function workflow($what = null)
    {
        return $this->parentWorkflow($what);
    }

    /**
     * @inheritDoc
     * @return WorkflowBlueprint[]|Collection
     */
    public function getWorkflowListing()
    {
        return $this->getParentWorkflowListing();
    }
}