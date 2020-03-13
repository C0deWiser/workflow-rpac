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
     * @param string $role
     * @return bool|null return null, if there is no default rule
     */
    public function getPermission($source, $target, $role)
    {
        return null;
    }
}