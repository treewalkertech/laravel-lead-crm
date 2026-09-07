<?php

namespace Webkul\Prospect\Repositories;

use Webkul\Core\Eloquent\Repository;

class ProspectSourceRepository extends Repository
{
    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Prospect\Contracts\ProspectSource';
    }
}
