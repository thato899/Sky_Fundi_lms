@extends('layouts.web')
@section('content')
<style>
.lb-shell{padding:2rem 0 4rem}.lb-nav{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}.lb-nav .links{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}.lb-table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line)}.lb-table th,.lb-table td{padding:.75rem;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}.lb-table th{font-size:.78rem;text-transform:uppercase;color:var(--muted)}.lb-table tr.is-you{background:color-mix(in srgb,var(--primary) 8%,transparent)}.table-wrap{overflow-x:auto;border-radius:.8rem}.lb-form{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}.lb-form input,.lb-form select,.lb-form textarea{width:100%;padding:.7rem;border:1px solid #b9c5d3;border-radius:.55rem;background:var(--card);color:var(--ink);font:inherit}.lb-form .wide{grid-column:1/-1}.lb-form label{display:block;font-weight:700;margin-bottom:.3rem}.status-message{padding:.8rem;border-radius:.6rem;background:#e7f8f3;color:#08765f}.actions-inline{display:flex;gap:.5rem;flex-wrap:wrap}.actions-inline form{margin:0}.rank-badge{display:inline-flex;align-items:center;justify-content:center;min-width:2rem;height:2rem;border-radius:999px;background:color-mix(in srgb,var(--primary) 12%,#fff);color:var(--primary);font-weight:800}.rank-badge.gold{background:#fef3c7;color:#92400e}.rank-badge.silver{background:#e5e7eb;color:#374151}.rank-badge.bronze{background:#fde4cf;color:#9a3412}@media(max-width:900px){.lb-form{grid-template-columns:1fr 1fr}}@media(max-width:600px){.lb-form{grid-template-columns:1fr}.lb-nav{align-items:flex-start;flex-direction:column}}
</style>
<div class="wrap lb-shell">
    <nav class="lb-nav" aria-label="Leaderboards"><div><div class="eyebrow">{{ $organization->name }}</div><a href="{{ route('dashboard') }}">Dashboard</a> / <a href="{{ route('leaderboards.mine') }}">Leaderboards</a></div><div class="links">
        @if(in_array('leaderboards.manage',$permissions,true) || in_array('leaderboards.view_organization',$permissions,true))<a class="button secondary" href="{{ route('leaderboards.index') }}">Manage leaderboards</a>@endif
        @if(in_array('sports_records.manage',$permissions,true))<a class="button secondary" href="{{ route('leaderboards.sports-records.index') }}">Log sports results</a>@endif
        <a class="button secondary" href="{{ route('leaderboards.sportsperson.index') }}">Sportsperson of the Week</a>
        <a class="button secondary" href="{{ route('leaderboards.mine') }}">My standings</a>
    </div></nav>
    @if(session('status'))<p class="status-message">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('leaderboard-content')
</div>
@endsection
