<?php

declare(strict_types=1);

return [
    // Configuration, rather than an enum, deliberately keeps organisation types extensible.
    'types' => [
        'school' => 'School',
        'tutoring-centre' => 'Tutoring Centre',
        'college' => 'College',
        'training-academy' => 'Training Academy',
        'other' => 'Other',
    ],
    'settings_defaults' => [
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'language' => 'en',
        'timezone' => 'Africa/Johannesburg',
        'theme' => 'system',
    ],
];
