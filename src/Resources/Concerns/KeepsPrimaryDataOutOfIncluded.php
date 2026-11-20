<?php

namespace SnowDigital\JsonApi\Resources\Concerns;

use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use SnowDigital\JsonApi\Resources\ResourceObject;

trait KeepsPrimaryDataOutOfIncluded
{
    /**
     * Laravel dedupes `included` against itself, not against `data`.
     *
     * @param  array<string, mixed>  $with
     * @param  array<int, ResourceObject>  $primary
     * @return array<string, mixed>
     */
    protected function withoutPrimaryData(array $with, array $primary, JsonApiRequest $request): array
    {
        if (empty($with['included'])) {
            return $with;
        }

        $keys = [];

        foreach ($primary as $resource) {
            $keys[$resource->resolveResourceType($request) . ':' . $resource->resolveResourceIdentifier($request)] = true;
        }

        $with['included'] = array_values(array_filter(
            $with['included'],
            fn (array $included) => ! isset($keys["{$included['type']}:{$included['id']}"]),
        ));

        if (! $with['included']) {
            unset($with['included']);
        }

        return $with;
    }
}
