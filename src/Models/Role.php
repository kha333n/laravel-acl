<?php

namespace Kha333n\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    public function models(): MorphToMany
    {
        return $this->morphedByMany(Model::class, 'model', 'model_has_role');
    }

    public function assignPolicy(Policy $policy): void
    {
        $this->policies()->syncWithoutDetaching([$policy->id]);
    }

    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(Policy::class, 'role_policy');
    }

    public function assignTeam(Team $team): void
    {
        $this->teams()->syncWithoutDetaching([$team->id]);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_role');
    }

    public function revokePolicy(Policy $policy): void
    {
        $this->policies()->detach($policy);
    }

    public function revokeTeam(Team $team): void
    {
        $this->teams()->detach($team);
    }
}
