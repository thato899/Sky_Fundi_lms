<?php

declare(strict_types=1);

use Core\Billing\Http\Controllers\Api\V1\CheckoutController;
use Core\Billing\Http\Controllers\Api\V1\InvoiceController;
use Core\Billing\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by BillingServiceProvider — see
| core/Billing/README.md.
*/

// core.billing.manage is checked per-request against the resolved
// organization membership (Core\Identity\Application\PermissionResolver),
// not the `permission:` middleware — that middleware resolves a
// platform-wide direct-to-user role (see Core\RBAC\Application\PermissionService),
// which an ordinary organization admin never has. See
// Http\Requests\StoreSubscriptionCheckoutRequest/StoreInvoiceCheckoutRequest
// and InvoiceController's own check.
Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])
    ->prefix('billing')->name('billing.')->group(function (): void {
        Route::post('/checkout/subscription', [CheckoutController::class, 'subscription'])->name('checkout.subscription');
        Route::post('/invoices/{invoice}/checkout', [CheckoutController::class, 'invoice'])->name('checkout.invoice');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

// Public gateway notification endpoint — never behind auth/organization
// context, see Http/Controllers/Api/V1/WebhookController.php.
Route::post('/billing/webhooks/payfast', [WebhookController::class, 'payfast'])
    ->middleware('throttle:120,1')
    ->name('billing.webhooks.payfast');
