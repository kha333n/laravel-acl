<?php

namespace Kha333n\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Team extends Model
{
    protected $fillable = ['name', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'team_role');
    }

    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(Policy::class, 'team_policy');
    }

    public function models(): MorphToMany
    {
        return $this->morphToMany(Model::class, 'model', 'model_has_team');
    }

    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function assignPolicy(Policy $policy): void
    {
        $this->policies()->syncWithoutDetaching([$policy->id]);
    }

    public function revokeRole(Role $role): void
    {
        $this->roles()->detach($role);
    }

    public function revokePolicy(Policy $policy): void
    {
        $this->policies()->detach($policy);
    }
}
