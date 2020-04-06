<?php
namespace Codewiser\Workflow\Rpac\Traits;

use Codewiser\Workflow\Rpac\StateMachineEngine;
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
     * Get the model workflow
     * @param string $what attribute name or workflow class (if null, then first Workflow will be returned)
     * @return StateMachineEngine|null
     */
    public function workflow($what = null)
    {
        if ($workflow = $this->parentWorkflow($what)) {
            return new StateMachineEngine($workflow->getBlueprint(), $this, $workflow->getAttributeName());
        }
        return null;
    }

    /**
     * @inheritDoc
     * @return StateMachineEngine[]|Collection
     */
    public function getWorkflowListing()
    {
        return $this->getParentWorkflowListing();
    }
}