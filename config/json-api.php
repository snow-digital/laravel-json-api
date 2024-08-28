<?php

return [

    /*
     * Discover and register all Eloquent models as resources
     */
    'auto_register' => [
        'enabled' => true,
        'path' => app_path('Models'),
        'namespace' => 'App\Models',
    ],

    /*
     * Automatically create routes for all registered resources
     */
    'auto_routing' => [
        'enabled' => true,
        'prefix' => 'api',
        'routes' => [
            'index' => true,
            'show' => true,
            'post' => true,
            'patch' => true,
            'delete' => true,
            'restore' => false,
        ],
    ],

];
