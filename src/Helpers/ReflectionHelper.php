<?php
namespace Codewiser\Workflow\Rpac\Helpers;

use Codewiser\Workflow\Rpac\Traits\Workflow;
use Codewiser\Workflow\Rpac\WorkflowBlueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Trunow\Rpac\Policies\RpacPolicy;

class ReflectionHelper extends \Trunow\Rpac\Helpers\ReflectionHelper
{
    protected function getAllFlows()
    {
        $models = [];

        foreach ($this->scanDir(app_path()) as $file) {
            $className = Str::replaceFirst(app_path() . '/', '', $file);
            $className = str_replace('/', '\\', $className);
            $className = app()->getNamespace() . substr($className,0,-4);
            $reflection = new \ReflectionClass($className);
        }

        return $models;
    }
    public function getFlows($policy)
    {
        /** @var RpacPolicy $policy */
        $policy = new $policy();
        $className = $policy->model();
        /** @var Workflow $model */
        $model = new $className();
        $model->workflow();
    }
}