<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Academics\Application\EducationSettingsService;
use Modules\Academics\Http\Requests\UpdateEducationSettingsRequest;

final class EducationSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EducationSettingsService $settings,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(['data' => $this->settings->all()]);
    }

    public function update(UpdateEducationSettingsRequest $request): JsonResponse
    {
        $this->settings->setMany($request->array('values'));

        return $this->ok(['data' => $this->settings->all()]);
    }
}
