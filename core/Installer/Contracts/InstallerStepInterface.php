<?php

declare(strict_types=1);

namespace Core\Installer\Contracts;

/**
 * One step of the platform installation workflow — see
 * core/Installer/README.md. Steps are ordered by config('installer.steps')
 * and are individually idempotent: running an already-complete step
 * again simply re-applies the given input rather than erroring, so the
 * installer can be safely re-run to reconfigure a single step later.
 */
interface InstallerStepInterface
{
    public function key(): string;

    public function label(): string;

    /**
     * Whether this step's configuration already exists (e.g. an
     * administrator account, a branding override) — used to show
     * installation progress without a separate "installed steps" table.
     */
    public function isComplete(): bool;

    /**
     * @return array Arbitrary result data specific to the step, surfaced back to the installer UI/CLI.
     */
    public function run(array $input): array;
}
