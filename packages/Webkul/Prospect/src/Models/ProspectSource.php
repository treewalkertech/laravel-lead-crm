<?php

namespace Webkul\Prospect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Prospect\Contracts\ProspectSource as ProspectSourceContract;

class ProspectSource extends Model implements ProspectSourceContract
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the prospects using this source.
     */
    public function prospects(): HasMany
    {
        return $this->hasMany(ProspectProxy::modelClass(), 'prospect_source_id');
    }
}
