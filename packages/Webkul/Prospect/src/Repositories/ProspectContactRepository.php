<?php

namespace Webkul\Prospect\Repositories;

use Webkul\Core\Eloquent\Repository;

class ProspectContactRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'email',
        'phone',
        'mobile',
        'status',
        'prospect_id',
    ];

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Prospect\Contracts\ProspectContact';
    }
}
