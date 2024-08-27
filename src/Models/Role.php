<?php

namespace Kha333n\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends Model
{
    protected array $fillable = ['name', 'description'];

    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(Policy::class, 'role_policy');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_role');
    }

    public function models(): MorphToMany
    {
        return $this->morphedByMany(Model::class, 'model', 'model_has_role');
    }
}
