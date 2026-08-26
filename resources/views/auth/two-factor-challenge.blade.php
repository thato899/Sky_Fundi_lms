@extends('layouts.web')
@section('title', 'Verify your identity')
@section('content')
<section class="form-shell panel"><div class="eyebrow">Two-factor verification</div><h1 style="font-size:2.2rem">Enter your code</h1><p>Open your authenticator app and enter the 6-digit code, or use one of your recovery codes.</p>@if($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('two-factor.challenge.store') }}">@csrf<div class="field"><label for="code">Authenticator code</label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus></div><div class="actions"><button type="submit">Verify</button></div></form>
<details style="margin-top:1rem"><summary>Use a recovery code instead</summary><form method="POST" action="{{ route('two-factor.challenge.store') }}" style="margin-top:.75rem">@csrf<div class="field"><label for="recovery_code">Recovery code</label><input id="recovery_code" name="recovery_code" type="text" autocomplete="off"></div><div class="actions"><button type="submit">Verify with recovery code</button></div></form></details>
<p style="margin-top:1rem"><a href="{{ route('login') }}">Cancel and return to log in</a></p>
</section>
@endsection
