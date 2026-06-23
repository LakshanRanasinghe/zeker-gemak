<?php

use Vanilo\Foundation\Providers\ModuleServiceProvider;

return [
    'modules' => [
        ModuleServiceProvider::class,
        Vanilo\Translation\Providers\ModuleServiceProvider::class,
    ],
];
