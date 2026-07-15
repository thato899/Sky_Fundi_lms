@extends('layouts.web')
@section('title', 'Dashboard')
@section('content')
<section class="wrap" style="padding:4rem 0"><div class="eyebrow">Organization access</div><h1 style="font-size:clamp(2rem,5vw,3.5rem)">{{ $membership->organization->name }}</h1><div class="two-col"><div class="panel"><h2>Your access</h2><p><strong>{{ $user->name }}</strong><br>{{ $user->email }}</p><p class="meta">Membership: {{ $membership->status->value }}<br>Role: {{ $membership->role?->name ?? 'No role assigned' }}</p></div><div class="panel"><h2>Dashboard availability</h2><p>Your organization access is active. A role-specific web dashboard is not yet available, so this page provides a safe application entry point without showing placeholder metrics.</p>@if($permissions !== [])<p class="meta">Granted capabilities: {{ implode(', ', $permissions) }}</p>@else<p class="meta">No enabled capabilities are currently assigned.</p>@endif</div></div></section>
@endsection
