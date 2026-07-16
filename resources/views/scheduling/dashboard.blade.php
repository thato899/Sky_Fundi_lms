@extends('scheduling.layout')
@section('title','Scheduling')
@section('scheduling-content')
<h1>Scheduling</h1><p>Times are shown in {{ $organization->timezone }}. Scheduling uses organization-local dates and stores concrete instants in UTC.</p>
<div class="feature-grid"><article class="feature"><h3>Lessons today</h3><strong>{{ $today }}</strong></article><article class="feature"><h3>Lessons this week</h3><strong>{{ $week }}</strong></article><article class="feature"><h3>Cancelled this week</h3><strong>{{ $cancelled }}</strong></article><article class="feature"><h3>Active periods</h3><strong>{{ $periods }}</strong></article><article class="feature"><h3>Active template</h3><strong>{{ $template?->name ?? 'None' }}</strong></article></div>
<h2>Upcoming teaching closures</h2><div class="stack">@forelse($closures as $closure)<article class="panel"><strong>{{ $closure->name }}</strong><p>{{ $closure->start_date->toDateString() }} – {{ $closure->end_date->toDateString() }} · {{ $closure->closure_scope }}</p></article>@empty<p>No upcoming closures.</p>@endforelse</div>
@endsection
