<?php


namespace Codewiser\Workflow\Rpac\Helpers;


use Codewiser\Rpac\Helpers\RpacHelper;
use Codewiser\Workflow\Rpac\WrpacPolicy;
use Illuminate\Support\Facades\Gate;

class WrpacHelper extends RpacHelper
{
    /**
     * Get full list of Models with WrpacPolicy
     * @return array|string[]
     */
    public static function getRpacModels()
    {
        $models = [];

        foreach (parent::getRpacModels() as $className)
        if (($policy = Gate::getPolicyFor($className)) && $policy instanceof WrpacPolicy) {
            $models[] = $className;
        }

        return $models;
    }
}