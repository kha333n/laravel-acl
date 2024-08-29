<?php

namespace Kha333n\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    protected $fillable = ['name', 'is_scopeable', 'resource_id'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
