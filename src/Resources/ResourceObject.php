<?php

namespace SnowDigital\JsonApi\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as BaseJsonApiResource;
use Illuminate\Support\Arr;
use SnowDigital\JsonApi\Facades\JsonApi;
use SnowDigital\JsonApi\Resources\Concerns\KeepsPrimaryDataOutOfIncluded;

/**
 * A model as a JSON:API resource object: relations are relationships, never attributes.
 *
 * Every declared belongsTo always carries its identifier, read from the foreign key
 * without loading anything; other relationships appear only when included.
 *
 * @property-read Model $resource
 */
class ResourceObject extends BaseJsonApiResource
{
    use KeepsPrimaryDataOutOfIncluded;

    /**
     * @var array<string, BelongsTo<Model, Model>>|null
     */
    private ?array $belongsToRelations = null;

    /**
     * @return string|null
     */
    public function toType(Request $request)
    {
        return $this->resource instanceof Model ? JsonApi::type($this->resource) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request)
    {
        if (! $this->resource instanceof Model) {
            return (array) parent::toAttributes($request);
        }

        return Arr::except($this->resource->attributesToArray(), [
            // `id` and `type` share the resource object's namespace; `type` is kept as offers use it.
            'id',
            ...$this->foreignKeys(),
        ]);
    }

    /**
     * @return array<string, class-string<BaseJsonApiResource>>
     */
    public function toRelationships(Request $request)
    {
        if (! $this->resource instanceof Model) {
            return [];
        }

        $names = array_unique([
            ...ResourceRelationships::declared($this->resource),
            ...array_keys($this->resource->getRelations()),
        ]);

        return collect($names)
            ->mapWithKeys(fn (string $name) => [$name => $this->relatedResourceClass($name)])
            ->all();
    }

    /**
     * Included resources follow the requested include paths only. Laravel would also include
     * whatever is already loaded on them, e.g. a relation eager loaded through a model's `$with`.
     *
     * @return $this
     */
    public function includePreviouslyLoadedRelationships()
    {
        return $this;
    }

    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function with($request)
    {
        return $this->withoutPrimaryData(parent::with($request), [$this], $this->resolveJsonApiRequestFrom($request));
    }

    /**
     * The resource class used for related (and included) models.
     *
     * @return class-string<BaseJsonApiResource>
     */
    protected function relatedResourceClass(string $relation): string
    {
        return self::class;
    }

    /**
     * @return list<string>
     */
    protected function foreignKeys(): array
    {
        $columns = ResourceRelationships::foreignKeys($this->resource);

        foreach ($this->belongsToRelations() as $relation) {
            array_push($columns, ...ResourceRelationships::columns($relation));
        }

        return $columns;
    }

    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        $relationships = parent::resolveResourceRelationshipIdentifiers($request);

        if (! $this->resource instanceof Model) {
            return $relationships;
        }

        $identifiers = [];

        foreach ($this->belongsToRelations() as $name => $relation) {
            if (array_key_exists($name, $relationships)) {
                continue;
            }

            $identifier = $this->identifierFor($name, $relation);

            if ($identifier !== false) {
                $identifiers[$name] = ['data' => $identifier];
            }
        }

        return [...$relationships, ...$identifiers];
    }

    /**
     * @return array<string, BelongsTo<Model, Model>>
     */
    protected function belongsToRelations(): array
    {
        if ($this->belongsToRelations !== null) {
            return $this->belongsToRelations;
        }

        $relations = [];

        foreach (ResourceRelationships::belongsTo($this->resource) as $name) {
            // Asked per row: a relation may be a morphTo on one row and a belongsTo on another.
            $relation = $this->resource->{$name}();

            if ($relation instanceof BelongsTo) {
                $relations[$name] = $relation;
            }
        }

        return $this->belongsToRelations = $relations;
    }

    /**
     * The `{type, id}` a belongsTo points at, from the foreign key alone.
     *
     * @param  BelongsTo<Model, Model>  $relation
     * @return array{id: string, type: string}|null|false null for an empty foreign key, false when unknowable without a query
     */
    protected function identifierFor(string $name, BelongsTo $relation): array|null|false
    {
        if ($this->resource->relationLoaded($name)) {
            $related = $this->resource->getRelation($name);

            return $related instanceof Model
                ? ['id' => (string) $related->getKey(), 'type' => JsonApi::type($related)]
                : null;
        }

        $id = $this->resource->getAttribute($relation->getForeignKeyName());

        if ($relation instanceof MorphTo) {
            $morphType = $this->resource->getAttribute($relation->getMorphType());

            if (blank($morphType) || blank($id)) {
                return null;
            }

            $class = Relation::getMorphedModel($morphType) ?? $morphType;
            $ownerKey = $relation->getOwnerKeyName();

            if ($ownerKey && class_exists($class) && $ownerKey !== (new $class)->getKeyName()) {
                return false;
            }

            return ['id' => (string) $id, 'type' => JsonApi::type($class)];
        }

        if (blank($id)) {
            return null;
        }

        if ($relation->getOwnerKeyName() !== $relation->getRelated()->getKeyName()) {
            return false;
        }

        return ['id' => (string) $id, 'type' => JsonApi::type($relation->getRelated())];
    }

    protected static function newCollection($resource)
    {
        return new CompoundDocument($resource, static::class);
    }
}
