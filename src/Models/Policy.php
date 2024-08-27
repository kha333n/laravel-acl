<?php

namespace Kha333n\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Kha333n\LaravelAcl\Exceptions\InvalidPolicyException;

class Policy extends Model
{
    protected array $fillable = ['name', 'policy_json', 'description'];

    public static function boot(): void
    {
        parent::boot();

        static::saving(/**
         * @throws InvalidPolicyException
         */ function ($model) {
            laravelAclValidatePolicy($model->policy_json);
        });

        static::updating(/**
         * @throws InvalidPolicyException
         */ function ($model) {
            laravelAclValidatePolicy($model->policy_json);
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_policy');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_policy');
    }

    public function models(): MorphToMany
    {
        return $this->morphedByMany(Model::class, 'model', 'model_has_policy');
    }
}
