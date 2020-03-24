<?php


namespace Codewiser\Workflow\Rpac\Traits;

use Codewiser\Rpac\Helpers\RpacHelper;
use Codewiser\Rpac\Traits\Roles;
use Codewiser\Rpac\Traits\RPAC;
use Codewiser\Workflow\Rpac\Helpers\WrpacHelper;
use Codewiser\Workflow\Rpac\WorkflowBlueprint;
use Codewiser\Workflow\Rpac\WrpacPolicy;
use \Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * @inheritDoc
 * @property-read array $authorizedTransitions
 */
trait WRPAC
{
    use RPAC,
        Workflow;

    /**
     * Get Model W.R.P.A.C. Policy
     * @return WrpacPolicy|null
     */
    public static function getPolicy()
    {
        if (($policy = Gate::getPolicyFor(static::class)) && $policy instanceof WrpacPolicy) {
            return $policy;
        } else {
            return null;
        }
    }

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
        if ($policy = static::getPolicy()) {

            $transitions = $policy->getTransitions($user, $this);

            if ($what) {
                // Ask for exact workflow transitions
                /** @var WorkflowBlueprint $workflow */
                $workflow = $this->workflow($what);
                if (isset($transitions[(string)$workflow])) {
                    return $transitions[(string)$workflow];
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

    /**
     * Get non-model roles, allowed to perform given action
     * @param string $action
     * @param string $workflow
     * @param string $state
     * @return array of roles
     */
    protected function getAuthorizedNonModelRoles($action, $workflow = null, $state = null)
    {
        // Keep only non-model roles
        $roles = array_intersect(
            static::getPolicy()->getPermissions($action, $this->workflow($workflow), $state),
            array_merge(['*'], RpacHelper::getNonModelRoles())
        );

        return $roles;
    }

    /**
     * Get model roles without namespace(!), allowed to perform given action
     * @param string $action
     * @param string $workflow
     * @param string $state
     * @return array of roles
     */
    protected function getAuthorizedModelRoles($action, $workflow, $state)
    {
        // Clean out namespaces
        // Keep only model roles
        $relationships = array_map(function ($n) {
            $n = explode('\\', $n);
            $n = array_pop($n);
            return Str::snake($n);
        }, static::getPolicy()->getPermissions($action, $this->workflow($workflow), $state));

        if (in_array('*', $relationships)) {
            // All model roles allowed
            return $this->relationships;
        } else {
            // Some model roles allowed
            return array_intersect($relationships, $this->relationships);
        }
    }

    /**
     * Apply global scope to the Model, so user can get only records he allowed to $action
     * @param Builder $query
     * @param string $action
     * @param User|Roles|null $user
     * @return Builder
     */
    public function scopeAllowedTo(Builder $query, $action, ?User $user)
    {
        // without workflow we construct Scope that way
        // we get all positive permissions with Model+Action signature
        // we get Relationships from those permissions
        // we apply Relationship Scopes, so Collection got models from those scopes

        // with workflow we should detect, what workflow states user is permitted to $action
        // first we check Permissions for all user concrete Roles
        // second, check user Relationships

        /*
         * Example query
         *
         * where
         *      workflow_1_attr='new' // if user.roles allows him to see those records
         *      or
         *      workflow_2_attr='review'
         *      or
         *      (   // user has access to `correct` state as `owner`
         *          workflow_1_attr='correct'
         *          and
         *          owner_id={user_id}
         *      )
         *
         */

        $userRoles = $user ? array_merge(
            ['any'], $user->roles->pluck('slug')->toArray()
        ) : ['guest'];

//        dump($userRoles);

        $query->where(function (Builder $query) use ($action, $user, $userRoles) {
            $scoped = false;

            foreach ($this->getWorkflowListing() as $workflow) {
                foreach ($workflow->getStates() as $state) {
//                    dump(['wf' => [$workflow->getAttributeName(), $state]]);
                    $userAuthorizedRoles = $this->getAuthorizedNonModelRoles($action, $workflow->getAttributeName(), $state);
//                    dump(['AuthorizedNonModelRoles' => $userAuthorizedRoles]);
                    $fullAccess =
                        in_array('*', $userAuthorizedRoles)
                        ||
                        array_intersect($userRoles, $userAuthorizedRoles);

                    if ($fullAccess) {
                        // Add to scope all records with workflow=state
                        // ex: workflow_1_attr='new'
                        $query->orWhere(function(Builder $query) use ($workflow, $state) {
                            $query->workflow($state, $workflow);
                        });
                        $scoped = true;
                    } elseif ($user) {
                        // Add to scope every chunk, scoped by relationship
                        // ex: workflow_1_attr='correct' and owner_id={user_id}
                        $relationships = $this->getAuthorizedModelRoles($action, $workflow->getAttributeName(), $state);
//                        dump(['AuthorizedModelRoles' => $relationships]);
                        foreach ($relationships as $relationship) {
                            $query->orWhere(function (Builder $query) use ($workflow, $state, $relationship, $user) {
                                $query
                                    ->workflow($state, $workflow)
                                    ->related($relationship, $user);
                            });
                            $scoped = true;
                        }
                    }
                }
            }

            if (!$scoped) {
                // User is anon or there are no authorized
                // Apply empty scope to prevent user access to unauthorized models
                $query->where($this->getKeyName(), 0);
            }
        });

    }
}