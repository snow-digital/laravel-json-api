<?php

namespace SnowDigital\JsonApi;

use Illuminate\Support\Facades\Event;
use SnowDigital\JsonApi\Events\AfterIndex;

class ApiEvents
{
    public function afterIndex(callable $callback): static
    {
        return $this->on(AfterIndex::class, $callback);
    }

    protected function on(string $event, callable $callback): static
    {
        Event::listen($event, $callback);

        return $this;
    }
}
