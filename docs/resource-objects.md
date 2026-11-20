# Resource objects

By default, `JsonApiResource` returns `{id, attributes}` with every loaded relation nested inside
`attributes`, so a parent shared by many rows is copied into each of them.

`ResourceObject` serializes spec-shaped JSON:API documents instead, on top of Laravel's own
`Illuminate\Http\Resources\JsonApi\JsonApiResource` (Laravel 12.59+):

- `type`, `id`, `attributes`, `relationships`;
- `included`, deduplicated by `type:id` across the whole page, never repeating a resource of `data`;
- nested includes (`include=offers.entry`);
- sparse fieldsets per type (`fields[providers]=name`).

Both formats can be served side by side, from the same registered resources.

## Classes

| Class | Role |
|---|---|
| `SnowDigital\JsonApi\Resources\ResourceObject` | One model as a resource object. |
| `SnowDigital\JsonApi\Resources\CompoundDocument` | A page of them (Spatie `jsonPaginate()` gives `links` + `meta`). |
| `SnowDigital\JsonApi\ApiController` | `index` and `show`, serving whichever resource class it is given. |
| `SnowDigital\JsonApi\Resources\ResourceRelationships` | The relationships each type declares. |
| `JsonApi::types()` / `JsonApi::type()` | Model → `type`. |

## Serving resource objects

The controller serializes with `$resourceClass` and `$collectionClass`, which default to
`JsonApiResource` and `JsonApiCollection`. Extend it to serve resource objects:

```php
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use SnowDigital\JsonApi\ApiController;
use SnowDigital\JsonApi\Resources\CompoundDocument;
use SnowDigital\JsonApi\Resources\ResourceObject;

class ResourceObjectController extends ApiController
{
    protected string $resourceClass = ResourceObject::class;

    protected string $collectionClass = CompoundDocument::class;

    /**
     * @return CompoundDocument
     *
     * @phpstan-return ResourceCollection
     */
    public function index(): ResourceCollection
    {
        return parent::index();
    }

    /**
     * @return ResourceObject
     *
     * @phpstan-return JsonResource
     */
    public function show(string $id): JsonResource
    {
        return parent::show($id);
    }
}
```

The two overrides only change the phpdoc: Scramble documents a response from the action's `@return`,
and the parent's says `JsonApiResource`. `@phpstan-return` keeps PHPStan on the native types.

Resources are registered as usual (`JsonApi::setResources()`, with `[class, only]`). Declare the
routes, for example to serve them as a second API version:

```php
Route::prefix('api/v2')->name('api:v2:')->group(function () {
    Route::get('{resource}', [ResourceObjectController::class, 'index'])
        ->whereIn('resource', ['offers', 'providers'])
        ->name('index');
    Route::get('{resource}/{id}', [ResourceObjectController::class, 'show'])
        ->whereIn('resource', ['offers', 'providers'])
        ->name('show');
});
```

A fixed route works too: `Route::get('offers', [...])->defaults('resource', 'offers')`.

`index` fires `AfterIndex` as usual (its `collection` is a `ResourceCollection`), so listeners
such as `meta=attributes` keep working.

When the resource class is a `ResourceObject` (`usesResourceObjects()`), the controller also:

- hides `fields` from Spatie (see [Sparse fieldsets](#sparse-fieldsets));
- remembers the type it serves for its model;
- keeps only the include paths the query builder allows (see [Includes](#includes)).

For a custom read (e.g. by slug), extend the controller and reuse its steps: `ensureAction('show')`
(the built-in actions keep that check inline, where Scramble sees the 404),
`newQueryBuilder()`, `afterQuery($builder)`, `newResource($entry)`. The resource is already
resolved from the route's `resource` parameter (or default).

## Types

`JsonApi::type($model)` is, in order:

1. the key given with `JsonApi::types([Offer::class => 'offers'])`, used as given;
2. the key a model class is registered under directly (`JsonApi::setResource('ratings', Rating::class)`);
3. the key the controller is serving (remembered for its model);
4. the morph alias, with characters JSON:API member names forbid replaced by `-`
   (`offers.entry-mobile` gives `offers-entry-mobile`);
5. Laravel's default, the model's class basename in snake case, plural.

**Register the types of models served through a query builder.** A builder's model is only known
once it is constructed, and constructing one reads and validates the current request (and may
have side effects), so the controller never constructs a builder other than the one it serves:

```php
JsonApi::types([
    Offer::class => 'offers',
    Provider::class => 'providers',
]);
```

Without this, a provider included in `offers` falls back to its morph alias (`providers-provider`).

## Relationships

A type declares the relationships its builder allows as includes (`getIncludes()`, top-level names:
`offers.entry` declares `offers`). For builders other than the one being served, `getIncludes()` is
called on an instance that was never constructed, with only `$resource` set: it must not depend
on anything else the constructor does.

- **belongsTo and morphTo always carry an identifier**, even without `include`. It is read from the
  foreign key (and the morph type column), with no load and no query. A null key gives `data: null`.
- Their columns (`provider_id`, `entry_id`, `entry_type`) are removed from `attributes`.
- hasMany, belongsToMany and morphMany appear only when included.
- `id` is removed from `attributes`; it is the resource object's `id`. An attribute called `type`
  is kept (offers have one), although the spec reserves the name.
- A belongsTo whose owner key is not the related primary key gets an identifier only when loaded.

Included models are serialized with `ResourceObject`; override `relatedResourceClass()` to change
that per relation.

## Includes

Includes follow the requested paths only:

- An included resource does not pull in relations that merely happen to be loaded on it (Laravel's
  default), e.g. a `parent` eager loaded through a model's `$with`.
- A resource in `data` is never repeated in `included`, even when a path reaches it again
  (`entry.parent`).
- The controller keeps only the include paths the query builder allows, the ones Spatie eager
  loaded. Laravel would otherwise lazy load the others row by row when invalid includes are
  configured not to throw. A custom endpoint serializing its own query should eager load what it
  lets clients include.

## Sparse fieldsets

`fields[type]=a,b` filters `attributes` of that type, in `data` and `included`. Identifiers stay.

For resource objects the controller hides `fields` from Spatie's query builder: Spatie turns them
into a SQL `select` per *table*, which drops the primary and foreign keys that eager loads and
identifiers need, and rejects fields of related tables. The SQL selects every column and only the
serializer applies sparse fieldsets. Unknown fields are ignored rather than rejected.

## A custom resource

Extend the resource to serialize something the generic controller does not serve:

```php
class OfferResultResource extends \SnowDigital\JsonApi\Resources\ResourceObject
{
    public function toAttributes(Request $request): array
    {
        return [
            ...parent::toAttributes($request),
            'availability_mobile' => $this->resource->availability_mobile,
        ];
    }
}

return OfferResultResource::collection($paginator); // a CompoundDocument
```

The type, identifiers and foreign-key removal still come from the registry, so register the
model's type.

## Tests

```bash
composer install
vendor/bin/phpunit
```
