<?php


namespace Codewiser\Workflow\Rpac\Traits;

use Codewiser\Workflow\Rpac\WorkflowBlueprint;
use Codewiser\Workflow\Rpac\WorkflowPolicy;
use \Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * @inheritDoc
 * @property-read array $authorizedTransitions
 */
trait Permissions
{
    use \Codewiser\Rpac\Traits\Permissions;

    public function getAuthorizedTransitionsAttribute()
    {
        return $this->getAuthorizedTransitions(Auth::user());
    }

    /**
     * Get list of transitions allowed to given User
     * @param User|null $user
     * @param string $what attribute name or workflow class (if null, then first Workflow will be returned)
     * @return array
     */
    public function getAuthorizedTransitions(?User $user, $what = null)
    {
        if (($policy = Gate::getPolicyFor($this)) && $policy instanceof WorkflowPolicy) {

            $transitions = $policy->getTransitions($user, $this);

            if ($what) {
                // Ask for exact workflow transitions
                /** @var WorkflowBlueprint $workflow */
                $workflow = $this->workflow($what);
                if (isset($transitions[$workflow->getAttributeName()])) {
                    return $transitions[$workflow->getAttributeName()];
                }
            } else {
                // Ask for the first workflow transitions
                if ($transitions) {
                    return current($transitions);
                }
            }
        }

        return [];
    }
}