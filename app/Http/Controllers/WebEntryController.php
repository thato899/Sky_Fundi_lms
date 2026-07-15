<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\PostLoginDestinationResolver;
use Core\Branding\Application\BrandingService;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WebEntryController
{
    public function __construct(
        private readonly BrandingService $branding,
        private readonly PostLoginDestinationResolver $destinations,
    ) {}

    public function home(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User) {
            return $this->destinations->redirect($request->user(), $request);
        }

        return view('home', ['branding' => $this->branding->current()]);
    }

    public function access(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $memberships = $this->destinations->activeMemberships($user);

        if ($memberships->count() === 1) {
            return $this->destinations->redirect($user, $request);
        }

        return view('auth.access', [
            'branding' => $this->branding->current(),
            'memberships' => $memberships,
            'user' => $user,
        ]);
    }

    public function selectOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate(['organization_id' => ['required', 'uuid']]);

        return $this->destinations->select($request->user(), $request, $validated['organization_id']);
    }
}
