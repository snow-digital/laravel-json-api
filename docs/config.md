# Configuration

## Resources registration

```php
return [

    'auto_register' => [
        /*
         * Use `false` to disable resources to be registered automatically,
         * You will need to register your resources manually.
         */
        'enabled' => true,
        // Where are your model stored?
        'path' => app_path('Models'),
        // What is the base namespace, it will be removed from the resorces name
        'namespace' => 'App\Models',
    ],

];
```

## Routes creation

```php
return [

    'auto_routing' => [
        /*
         * Use `false` to disable routes to be created automattically.
         * You will need to create your routes manually.
         */
        'enabled' => true,
        // Route prefix, e.g. `api/v1`
        'prefix' => 'api',
        // CRUD to enable or not
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
```
