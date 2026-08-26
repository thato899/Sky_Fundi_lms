<?php

declare(strict_types=1);

namespace Core\Billing\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Billing\Http\Resources\InvoiceResource;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;

final class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PermissionResolver $permissions) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $this->authorizeBilling($request);
        $invoices = Invoice::query()
            ->where('organization_id', $organization->getKey())
            ->latest('billing_period_start')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->ok(InvoiceResource::collection($invoices));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $organization = $this->organization($request);
        $this->authorizeBilling($request);
        abort_unless($invoice->organization_id === $organization->getKey(), 404);

        return $this->ok(new InvoiceResource($invoice->load('lineItems')));
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function authorizeBilling(Request $request): void
    {
        $membership = $request->attributes->get('organization_membership');
        abort_unless($membership instanceof Membership && $this->permissions->allows($membership, 'core.billing.manage'), 403);
    }
}
