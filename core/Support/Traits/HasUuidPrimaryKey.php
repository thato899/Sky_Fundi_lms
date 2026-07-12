<?php

declare(strict_types=1);

namespace Core\Support\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Every Core/module Eloquent model uses UUID primary keys (see
 * docs/database/conventions.md). This trait exists purely so that
 * convention is a one-line `use` statement with a project-specific
 * name in IDEs/code review, rather than every model reaching for
 * Laravel's generic Illuminate\Database\Eloquent\Concerns\HasUuids
 * directly — behaviourally identical, just a documented alias.
 */
trait HasUuidPrimaryKey
{
    use HasUuids;
}
