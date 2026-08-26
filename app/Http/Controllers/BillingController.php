<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\BillingDashboardService;
use Core\Billing\Application\CheckoutService;
use Core\Billing\Exceptions\BillingException;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Subscriptions\Infrastructure\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class BillingController
{
    public function __construct(
        private readonly BillingDashboardService $dashboard,
        private readonly CheckoutService $checkout,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'core.billing.manage'), 403);

        return view('billing.dashboard', $this->dashboard->for($organization) + [
            'organization' => $organization,
            'branding' => $this->organizations->branding($organization),
            'permissions' => $this->permissions->permissions($membership),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'core.billing.manage'), 403);

        $plan = Plan::findByKey((string) $request->string('plan_key'));
        abort_unless($plan !== null, 404);

        try {
            $result = $this->checkout->startSubscriptionCheckout($organization, $plan, URL::route('billing.dashboard', ['subscribed' => 1]), URL::route('billing.dashboard', ['cancelled' => 1]));
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()->away($result->redirectUrl);
    }

    public function payInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        [$organization, $membership] = $this->context($request);
        abort_unless($this->permissions->allows($membership, 'core.billing.manage'), 403);
        abort_unless($invoice->organization_id === $organization->getKey(), 404);

        try {
            $result = $this->checkout->startInvoiceCheckout($invoice, URL::route('billing.dashboard', ['paid' => 1]), URL::route('billing.dashboard', ['cancelled' => 1]));
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()->away($result->redirectUrl);
    }

    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);

        return [$organization, $membership];
    }
}
