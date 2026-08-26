@extends('layouts.web')
@section('title', 'Your recovery codes')
@section('content')
<section class="form-shell panel"><div class="eyebrow">Two-factor authentication</div><h1 style="font-size:2.2rem">Save your recovery codes</h1><p>Store these somewhere safe. Each code can be used once to sign in if you lose access to your authenticator app. They will not be shown again.</p>
<ul class="history">@foreach($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
<div class="actions"><a class="button" href="{{ $continueUrl }}">I've saved these — continue</a></div>
</section>
@endsection
