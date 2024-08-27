<?php

namespace Kha333n\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    protected array $fillable = ['name', 'description'];

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }
}
