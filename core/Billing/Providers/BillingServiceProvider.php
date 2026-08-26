<?php

declare(strict_types=1);

namespace Core\Billing\Providers;

use Core\Billing\Events\InvoiceIssued;
use Core\Billing\Events\PaymentFailed;
use Core\Billing\Listeners\NotifyOnInvoiceIssued;
use Core\Billing\Listeners\NotifyOnPaymentFailed;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../routes/api.php');

        Event::listen(PaymentFailed::class, NotifyOnPaymentFailed::class);
        Event::listen(InvoiceIssued::class, NotifyOnInvoiceIssued::class);
    }
}
