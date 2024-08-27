<?php

namespace Kha333n\LaravelAcl\Traits;

use Kha333n\LaravelAcl\Models\Role;
use Kha333n\LaravelAcl\Models\Policy;

trait HasAcl
{
    // Methods related to roles
    public function roles()
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_role');
    }

    public function hasRole(string|Role $role)
    {
        if (is_string($role)) {
            return $this->roles()->where('name', $role)->exists();
        }
        return $this->roles()->where('roles.id', $role->id)->exists();
    }

    public function assignRole(string|Role $role)
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function revokeRole(string|Role $role)
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        return $this->roles()->detach($role);
    }

    // Methods related to policies
    public function policies()
    {
        return $this->morphToMany(Policy::class, 'model', 'model_has_policy');
    }

    public function hasPolicy(string|Policy $policy)
    {
        if (is_string($policy)) {
            return $this->policies()->where('name', $policy)->exists();
        }
        return $this->policies()->where('policies.id', $policy->id)->exists();
    }

    public function assignPolicy(string|Policy $policy)
    {
        if (is_string($policy)) {
            $policy = Policy::where('name', $policy)->firstOrFail();
        }

        return $this->policies()->syncWithoutDetaching([$policy->id]);
    }

    public function revokePolicy(string|Policy $policy)
    {
        if (is_string($policy)) {
            $policy = Policy::where('name', $policy)->firstOrFail();
        }

        return $this->policies()->detach($policy);
    }

    // Check for permissions
    public function hasAccess($action, $resource): bool
    {
//        foreach ($this->policies as $policy) {
//            $policyArray = json_decode($policy->policy_json, true);
//            if (isset($policyArray[$resource][$action])) {
//                return $policyArray[$resource][$action];
//            }
//        }
        //TODO: Implement this method

        return false;
    }
}
