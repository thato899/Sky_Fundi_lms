<?php

declare(strict_types=1);

namespace Core\Settings\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Settings\Application\SettingsService;
use Core\Settings\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok(['data' => $this->settings->all($request->string('group')->value() ?: null)]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->setMany(
            values: $request->array('values'),
            group: $request->string('group')->value(),
            encryptedKeys: $request->array('encrypted_keys'),
        );

        return $this->ok(['data' => $this->settings->all($request->string('group')->value())]);
    }
}
