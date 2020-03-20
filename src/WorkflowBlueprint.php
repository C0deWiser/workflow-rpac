<?php
namespace Codewiser\Workflow\Rpac;

/**
 * Extends Workflow blueprint with permissions (who allowed to perform transitions?)
 * @package Codewiser\Workflow\Rpac
 */
abstract class WorkflowBlueprint extends \Codewiser\Workflow\WorkflowBlueprint
{
    /**
     * Default (built-in) permissions to perform transitions
     * @param string $source
     * @param string $target
     * @return array|string|null|void return namespaced(!) roles, allowed to perform transition
     */
    public function getDefaults($source, $target)
    {

    }
}