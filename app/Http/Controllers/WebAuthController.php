<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\PostLoginDestinationResolver;
use Core\Auth\Application\AuthService;
use Core\Auth\Exceptions\AccountNotActiveException;
use Core\Branding\Application\BrandingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class WebAuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly BrandingService $branding,
        private readonly PostLoginDestinationResolver $destinations,
    ) {}

    public function create(): View
    {
        return view('auth.login', ['branding' => $this->branding->current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        try {
            $user = $this->auth->authenticate(
                $credentials['email'],
                $credentials['password'],
                $request->ip() ?? '0.0.0.0',
            );
        } catch (AccountNotActiveException|ValidationException) {
            throw ValidationException::withMessages([
                'email' => ['These credentials could not be accepted.'],
            ]);
        }

        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        return $this->destinations->redirect($user, $request);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $this->auth->logout($user, revokeCurrentToken: false);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
