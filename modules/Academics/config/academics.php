<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Academics Module Configuration
|--------------------------------------------------------------------------
|
| Module-local config, per docs/architecture/module-system.md#module-anatomy.
| Runtime, organisation-configurable values (current academic year,
| default curriculum, grading/assessment/promotion/timetable rules)
| are NOT here — they live in Core\Settings under the "academics"
| group via Application\EducationSettingsService, so they're
| changeable without a redeploy. This file is for structural defaults
| only.
|
*/

return [
    // Fallback term-per-year count used only where a caller needs a
    // sane default before any AcademicTerm rows exist for a year.
    'default_terms_per_year' => 4,
];
