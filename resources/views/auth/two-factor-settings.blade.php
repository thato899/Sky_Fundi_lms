@extends('layouts.web')
@section('title', 'Two-factor authentication')
@section('content')
<section class="form-shell panel"><div class="eyebrow">Account security</div><h1 style="font-size:2.2rem">Two-factor authentication</h1>
@if(session('status'))<p class="status-message">{{ session('status') }}</p>@endif
@if($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
@if($enabled)
<p>Two-factor authentication is <strong>enabled</strong> on your account. You'll be asked for a code from your authenticator app whenever you log in.</p>
<form method="POST" action="{{ route('security.two-factor.recovery-codes.regenerate') }}" style="margin-top:1rem">@csrf<button type="submit" class="secondary">Regenerate recovery codes</button></form>
<form method="POST" action="{{ route('security.two-factor.disable') }}" style="margin-top:1rem" onsubmit="return confirm('Disable two-factor authentication?')">@csrf<div class="field"><label for="password">Confirm your password to disable</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><div class="actions"><button type="submit" class="danger">Disable two-factor authentication</button></div></form>
@else
<p>Two-factor authentication is <strong>not enabled</strong>. Add an authenticator app for an extra layer of protection on your account.</p>
<div class="actions"><a class="button" href="{{ route('security.two-factor.enroll') }}">Enable two-factor authentication</a></div>
@endif
</section>
@endsection
