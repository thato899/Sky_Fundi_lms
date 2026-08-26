<?php

declare(strict_types=1);

namespace Core\Billing\Http\Requests;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;

final class StoreInvoiceCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $membership = $this->attributes->get('organization_membership');

        return $membership instanceof Membership && app(PermissionResolver::class)->allows($membership, 'core.billing.manage');
    }

    public function rules(): array
    {
        return [
            'return_url' => ['required', 'url'],
            'cancel_url' => ['required', 'url'],
        ];
    }
}
