<?php

declare(strict_types=1);

namespace Core\Api\Http\Controllers;

/**
 * Base controller for every Core service and future module controller.
 * Deliberately empty of behaviour — shared response shaping lives in
 * Core\Api\Http\Responses\ApiResponse (a trait), kept separate so
 * controllers only pull in what they use. See
 * docs/architecture/clean-architecture.md#interface--adapters:
 * controllers stay thin — validate via Form Request, call a Service,
 * return a Response.
 */
abstract class Controller
{
    //
}
