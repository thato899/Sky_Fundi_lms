<?php

declare(strict_types=1);

namespace Core\Licensing\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Licensing\Application\LicenseService;
use Core\Licensing\Http\Requests\RenewLicenseRequest;
use Core\Licensing\Http\Requests\StoreLicenseRequest;
use Core\Licensing\Http\Resources\LicenseResource;
use Core\Licensing\Infrastructure\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class LicenseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LicenseService $licenses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $licenses = License::query()
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->string('licensee_id')->isNotEmpty(), fn ($q) => $q->where('licensee_id', $request->string('licensee_id')->value()))
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return $this->ok(LicenseResource::collection($licenses));
    }

    public function store(StoreLicenseRequest $request): JsonResponse
    {
        return $this->created(new LicenseResource($this->licenses->issue($request->validated())));
    }

    public function show(License $license): JsonResponse
    {
        return $this->ok(new LicenseResource($license));
    }

    public function activate(License $license): JsonResponse
    {
        return $this->ok(new LicenseResource($this->licenses->activate($license)));
    }

    public function suspend(Request $request, License $license): JsonResponse
    {
        return $this->ok(new LicenseResource($this->licenses->suspend($license, $request->string('reason')->value() ?: null)));
    }

    public function cancel(License $license): JsonResponse
    {
        return $this->ok(new LicenseResource($this->licenses->cancel($license)));
    }

    public function renew(RenewLicenseRequest $request, License $license): JsonResponse
    {
        $newExpiry = Carbon::parse($request->string('expiry_date')->value());

        return $this->ok(new LicenseResource($this->licenses->renew($license, $newExpiry)));
    }
}
