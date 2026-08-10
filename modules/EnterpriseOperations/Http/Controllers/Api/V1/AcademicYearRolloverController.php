<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EnterpriseOperations\Application\AcademicYearRolloverService;
use Modules\EnterpriseOperations\Infrastructure\Models\EnterpriseOperationRun;
use Modules\Organizations\Infrastructure\Models\Organization;

final class AcademicYearRolloverController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AcademicYearRolloverService $rollovers)
    {
    }

    public function dryRun(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate(['source_year_id' => ['required', 'uuid'], 'destination_year_id' => ['required', 'uuid'], 'rules' => ['required', 'array'], 'activate_destination' => ['boolean']]);
        return $this->created($this->rollovers->dryRun($organization, $request->user(), $data));
    }

    public function show(Request $request, EnterpriseOperationRun $run): JsonResponse
    {
        $this->guard($request, $run);

        return $this->ok($run);
    }

    public function submit(Request $request, EnterpriseOperationRun $run): JsonResponse
    {
        $this->guard($request, $run);

        return $this->ok($this->rollovers->submit($run, $request->user()));
    }

    public function approve(Request $request, EnterpriseOperationRun $run): JsonResponse
    {
        $this->guard($request, $run);

        return $this->ok($this->rollovers->approve($run, $request->user()));
    }

    public function execute(Request $request, EnterpriseOperationRun $run): JsonResponse
    {
        $this->guard($request, $run);

        return $this->ok($this->rollovers->execute($run, $request->user()));
    }

    public function rollback(Request $request, EnterpriseOperationRun $run): JsonResponse
    {
        $this->guard($request, $run);

        return $this->ok($this->rollovers->rollback($run, $request->user()));
    }

    public function override(Request $request, EnterpriseOperationRun $run, string $learnerId): JsonResponse
    {
        $this->guard($request, $run);
        $data = $request->validate(['grade_id' => ['required', 'uuid'], 'class_id' => ['nullable', 'uuid'], 'reason' => ['nullable', 'string', 'max:1000']]);
        return $this->ok($this->rollovers->override($run, $request->user(), $learnerId, $data));
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function guard(Request $request, EnterpriseOperationRun $run): void
    {
        abort_unless($this->organization($request)->getKey() === $run->getAttribute('organization_id'), 404);
    }
}
