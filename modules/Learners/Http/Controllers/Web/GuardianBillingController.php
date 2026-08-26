<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Web;

use Core\Billing\Application\CheckoutService;
use Core\Billing\Exceptions\BillingException;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

/**
 * The guardian-facing view of an organization's invoices — see
 * core/Billing/README.md ("Guardian Portal"). Visibility is gated on
 * the same `receives_financial_communication` relationship flag the
 * academic side already gates on `receives_academic_communication`
 * (see GuardianWebController::show()); staff with `guardians.view`
 * may also open it, matching every other guardian-profile page.
 */
final class GuardianBillingController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request, mixed $guardian): View
    {
        $guardian = $this->guardian($guardian);
        [$organization, $membership] = $this->context($request);
        Gate::authorize('view', $guardian);
        $actor = $this->actor($request);
        $isStaff = Gate::allows('viewAny', GuardianProfile::class);
        abort_unless($isStaff || $this->hasFinancialAccess($guardian, $actor), 403);

        $invoices = Invoice::query()
            ->where('organization_id', $organization->getKey())
            ->with('lineItems')
            ->latest('billing_period_start')
            ->limit(24)
            ->get();

        return view('guardians.invoices', [
            'guardian' => $guardian,
            'invoices' => $invoices,
            'organization' => $organization,
            'branding' => $this->organizations->branding($organization),
            'permissions' => $this->permissions->permissions($membership),
        ]);
    }

    public function pay(Request $request, mixed $guardian, Invoice $invoice): RedirectResponse
    {
        $guardian = $this->guardian($guardian);
        [$organization] = $this->context($request);
        Gate::authorize('view', $guardian);
        $actor = $this->actor($request);
        $isStaff = Gate::allows('viewAny', GuardianProfile::class);
        abort_unless($isStaff || $this->hasFinancialAccess($guardian, $actor), 403);
        abort_unless($invoice->organization_id === $organization->getKey(), 404);

        try {
            $result = $this->checkout->startInvoiceCheckout(
                $invoice,
                URL::route('guardians.invoices.index', ['guardian' => $guardian->uuid, 'paid' => 1]),
                URL::route('guardians.invoices.index', ['guardian' => $guardian->uuid, 'cancelled' => 1]),
            );
        } catch (BillingException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()->away($result->redirectUrl);
    }

    private function hasFinancialAccess(GuardianProfile $guardian, User $actor): bool
    {
        if ($guardian->getAttribute('user_id') !== $actor->getKey()) {
            return false;
        }

        return $guardian->relationships()
            ->where('status', 'active')
            ->where('receives_financial_communication', true)
            ->whereRaw('(effective_from is null or effective_from <= ?)', [today()->toDateString()])
            ->whereRaw('(effective_until is null or effective_until >= ?)', [today()->toDateString()])
            ->exists();
    }

    private function guardian(mixed $guardian): GuardianProfile
    {
        abort_unless($guardian instanceof GuardianProfile, 404);

        return $guardian;
    }

    private function context(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($organization instanceof Organization && $membership instanceof Membership, 403);

        return [$organization, $membership];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
