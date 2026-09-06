<?php

namespace Webkul\Prospect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Prospect\Contracts\ProspectContact as ProspectContactContract;

class ProspectContact extends Model implements ProspectContactContract
{
    /**
     * Statuses a prospect contact can move through.
     */
    const STATUS_NEW = 'new';

    const STATUS_CALL_NOT_PICKED = 'call_not_picked';

    const STATUS_CONTACTED = 'contacted';

    const STATUS_WRONG_NUMBER_EMAIL = 'wrong_number_email';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'prospect_id',
        'name',
        'mobile',
        'phone',
        'email',
        'designation',
        'department',
        'status',
        'comment',
        'converted_lead_id',
    ];

    /**
     * Get the prospect this contact belongs to.
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(ProspectProxy::modelClass());
    }

    /**
     * Get the lead this contact was converted into, if any.
     */
    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'converted_lead_id');
    }
}
