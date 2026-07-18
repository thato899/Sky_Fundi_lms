<?php

declare(strict_types=1);

return [
    'demo_password' => env('HACKATHON_DEMO_PASSWORD'),
    'ai' => [
        'marking_enabled' => (bool) env('AI_MARKING_ENABLED', true),
        'monthly_allowance' => (int) env('AI_MONTHLY_MARKING_ALLOWANCE', 250),
        'max_output_tokens' => (int) env('AI_MARKING_MAX_OUTPUT_TOKENS', 900),
        'input_cost_per_million' => (float) env('AI_INPUT_COST_PER_MILLION', 4.50),
        'output_cost_per_million' => (float) env('AI_OUTPUT_COST_PER_MILLION', 18.00),
    ],
    'plans' => [
        'starter' => ['name' => 'Starter', 'price' => 499.00, 'learners' => 100, 'staff' => 5, 'ai_allowance' => 50],
        'growth' => ['name' => 'Growth', 'price' => 1499.00, 'learners' => 500, 'staff' => 25, 'ai_allowance' => 500],
        'school_pro' => ['name' => 'School Pro', 'price' => 3999.00, 'learners' => 1500, 'staff' => 75, 'ai_allowance' => 2000],
    ],
    'profitability' => [
        'notification_cost' => (float) env('DEMO_NOTIFICATION_COST', 35.00),
        'hosting_allocation' => (float) env('DEMO_HOSTING_ALLOCATION', 180.00),
        'support_allocation' => (float) env('DEMO_SUPPORT_ALLOCATION', 250.00),
    ],
];
