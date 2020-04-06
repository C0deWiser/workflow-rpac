<?php


namespace Codewiser\Workflow\Rpac\Traits;

use Codewiser\Rpac\Role;
use Codewiser\Rpac\Traits\Roles;
use Codewiser\Rpac\Traits\RPAC;
use Codewiser\Workflow\Rpac\StateMachineEngine;
use Codewiser\Workflow\Rpac\WorkflowBlueprint;
use Codewiser\Workflow\Rpac\WrpacPolicy;
use \Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
                /** @var StateMachineEngine $workflow */
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

    /**
     * Get non-model roles, allowed to perform given action
     * @param string $action
     * @param StateMachineEngine $workflow
     * @param string $state
     * @return array of roles
     */
    protected function getAuthorizedNonModelRoles($action, StateMachineEngine $workflow, $state)
    {
        // Keep only non-model roles
        $roles = static::getPolicy()->getPermissions($action, $workflow, $state);

        if (in_array('*', $roles)) {
            // All non-model roles allowed
            return Role::allSlugs();
        } else {
            // Some non-model roles allowed
            return array_intersect($roles, Role::allSlugs());
        }
    }

    /**
     * Get model roles without namespace(!), allowed to perform given action
     * @param string $action
     * @param StateMachineEngine $workflow
     * @param string $state
     * @return array of roles
     */
    protected function getAuthorizedModelRoles($action, StateMachineEngine $workflow, $state)
    {
        // Keep only model roles
        $relationships = static::getPolicy()->getPermissions($action, $workflow, $state);

        if (in_array('*', $relationships)) {
            // All model roles allowed
            return self::getRelationshipListing();
        } else {
            // Some model roles allowed
            return array_intersect($relationships, self::getRelationshipListing());
        }
    }

    /**
     * Apply global scope to the Model, so user can get only records he allowed to $action
     * @param Builder $query
     * @param string $action
     * @param User|Roles|null $user
     * @return Builder
     */
    public function scopeOnlyAllowedTo(Builder $query, $action, ?User $user)
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

        $userRoles = $user ? $user->getRoles() : ['guest'];

        $query->where(function (Builder $query) use ($action, $user, $userRoles) {
            $scoped = false;


            foreach ($this->getWorkflowListing() as $workflow) {
                $whereIn = [];
                foreach ($workflow->getStates() as $state) {
//                    dump(['wf' => [$workflow->getAttributeName(), $state]]);
                    $userAuthorizedRoles = $this->getAuthorizedNonModelRoles($action, $workflow, $state);
//                    dump(['AuthorizedNonModelRoles' => $userAuthorizedRoles]);
                    $fullAccess = array_intersect($userRoles, $userAuthorizedRoles);

                    if ($fullAccess) {
                        $whereIn[] = $state;
                    } elseif ($user) {
                        // Add to scope every chunk, scoped by relationship
                        // ex: workflow_1_attr='correct' and owner_id={user_id}
                        $relationships = $this->getAuthorizedModelRoles($action, $workflow, $state);
//                        dump(['AuthorizedModelRoles' => $relationships]);
                        foreach ($relationships as $relationship) {
                            $query->orWhere(function (Builder $query) use ($workflow, $state, $relationship, $user) {
                                $query
                                    ->onlyState($state, $workflow)
                                    ->onlyRelated($relationship, $user);
                            });
                            $scoped = true;
                        }
                    }
                }
                if ($whereIn) {
                    // Add to scope all records with workflow in $whereIn
                    // ex: workflow_1_attr in ('new',...)
                    $query->orWhereIn($workflow->getAttributeName(), $whereIn);
                    $scoped = true;
                }
            }

            if (!$scoped) {
                // User is anon or there are no authorized
                // Apply empty scope to prevent user access to unauthorized models
                $query->whereKey(0);
            }
        });

    }
}