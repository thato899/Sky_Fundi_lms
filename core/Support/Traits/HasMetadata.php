<?php

declare(strict_types=1);

namespace Core\Support\Traits;

/**
 * For models with a `metadata` JSON column used as a flexible,
 * schemaless extension point (license metadata, deployment metadata,
 * analytics dimensions, ...) — see docs/database/conventions.md#json-columns
 * ("used sparingly, for genuinely schemaless... configuration").
 * Provides typed accessors instead of every caller reaching into the
 * raw array with magic string keys.
 */
trait HasMetadata
{
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    public function setMeta(string $key, mixed $value): static
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        return $this;
    }
}
