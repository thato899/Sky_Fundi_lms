@extends('layouts.web')
@section('title', 'Set up two-factor authentication')
@section('content')
<section class="form-shell panel"><div class="eyebrow">Two-factor authentication</div><h1 style="font-size:2.2rem">Set up your authenticator</h1>
@if($forced)<p>Your organization requires two-factor authentication before you can continue.</p>@else<p>Scan the code below with an authenticator app (Google Authenticator, Authy, 1Password, etc.), or enter the key manually.</p>@endif
@if($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
<dl class="detail-list"><div><dt>Manual entry key</dt><dd><code>{{ implode(' ', str_split($secret, 4)) }}</code></dd></div><div><dt>Setup URI</dt><dd style="word-break:break-all"><small>{{ $provisioningUri }}</small></dd></div></dl>
<form method="POST" action="{{ route('two-factor.enroll.confirm') }}">@csrf<div class="field"><label for="code">Enter the 6-digit code from your app to confirm</label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus></div><div class="actions"><button type="submit">Confirm and enable</button></div></form>
@unless($forced)<p style="margin-top:1rem"><a href="{{ route('security.two-factor.edit') }}">Cancel</a></p>@endunless
</section>
@endsection
