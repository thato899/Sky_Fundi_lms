<?php

declare(strict_types=1);

namespace Core\Billing\Http\Controllers\Api\V1;

use Core\Api\Http\Controllers\Controller;
use Core\Api\Http\Responses\ApiResponse;
use Core\Billing\Application\CheckoutService;
use Core\Billing\Http\Requests\StoreInvoiceCheckoutRequest;
use Core\Billing\Http\Requests\StoreSubscriptionCheckoutRequest;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizations\Infrastructure\Models\Organization;

final class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CheckoutService $checkout) {}

    public function subscription(StoreSubscriptionCheckoutRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $plan = Plan::findByKey($request->string('plan_key')->toString());
        abort_unless($plan !== null, 404);

        $result = $this->checkout->startSubscriptionCheckout($organization, $plan, $request->string('return_url')->toString(), $request->string('cancel_url')->toString());

        return $this->ok(['redirect_url' => $result->redirectUrl]);
    }

    public function invoice(StoreInvoiceCheckoutRequest $request, Invoice $invoice): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($invoice->organization_id === $organization->getKey(), 404);

        $result = $this->checkout->startInvoiceCheckout($invoice, $request->string('return_url')->toString(), $request->string('cancel_url')->toString());

        return $this->ok(['redirect_url' => $result->redirectUrl]);
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }
}
