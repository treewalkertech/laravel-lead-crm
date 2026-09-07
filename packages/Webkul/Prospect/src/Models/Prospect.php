<?php

namespace Webkul\Prospect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Prospect\Contracts\Prospect as ProspectContract;
use Webkul\User\Models\UserProxy;

class Prospect extends Model implements ProspectContract
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'industry',
        'prospect_source_id',
        'user_id',
    ];

    /**
     * Get the contacts belonging to this prospect.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ProspectContactProxy::modelClass());
    }

    /**
     * Get the user that owns this prospect.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the source this prospect came from.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ProspectSourceProxy::modelClass(), 'prospect_source_id');
    }
}
