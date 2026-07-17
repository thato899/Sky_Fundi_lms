<?php

declare(strict_types=1);

return [
    'request_id_header' => env('OBSERVABILITY_REQUEST_ID_HEADER', 'X-Request-ID'),
    'slow_request_ms' => (int) env('OBSERVABILITY_SLOW_REQUEST_MS', 1000),
    'slow_query_ms' => (int) env('OBSERVABILITY_SLOW_QUERY_MS', 500),
];
