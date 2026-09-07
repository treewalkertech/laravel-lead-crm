<?php

namespace Webkul\Prospect\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Prospect\Models\Prospect;
use Webkul\Prospect\Models\ProspectContact;
use Webkul\Prospect\Models\ProspectSource;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Prospect::class,
        ProspectContact::class,
        ProspectSource::class,
    ];
}
