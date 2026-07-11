<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Module Manager Configuration
|--------------------------------------------------------------------------
|
| Bootstrap configuration for Core\Modules (the module registry/loader
| framework — see docs/architecture/module-system.md). No modules are
| shipped in this repository yet; this only configures where the
| registry looks and how it behaves.
|
*/

return [
    'path' => base_path('modules'),

    'manifest_filename' => 'module.json',

    // Modules discovered on disk but not yet present in the
    // `modules` registry table are reported, never auto-installed —
    // installation is always an explicit, audited action.
    'auto_discover' => true,
];
