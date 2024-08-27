Usage with Model

```php
use SnowDigital\LaravelJsonApi\JsonApi;

JsonApi::setResource('users', \App\Models\User::class);
JsonApi::setResources([
    'users' => \App\Models\User::class,
    // ...
]);
```

Usage with custom QueryBuilder

```php
use App\Models\User;
use SnowDigital\LaravelJsonApi\DefaultQueryBuilder;

class UserQueryBuilder extends DefaultQueryBuilder
{
    public function __construct()
    {
        parent::__construct(new User());
    }
    
    public function getFilters(): array
    {
        return array_merge(parent::getFilters(), [
            AllowedFilter::exact('email'),
        ]);
    }
}

JsonApi::setResource('users', UserQueryBuilder::class);
```
