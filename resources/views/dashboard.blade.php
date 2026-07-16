@extends('layouts.web')
@section('title', 'Organization dashboard')
@section('content')
<style>
.dashboard{padding:2.5rem 0 4rem}.dashboard-head{display:flex;justify-content:space-between;gap:1rem;align-items:end;margin-bottom:1.5rem}.dashboard h1{font-size:clamp(2rem,4vw,3.25rem);margin:.35rem 0}.badge{display:inline-flex;padding:.3rem .65rem;border-radius:999px;background:#e7f8f3;color:#08765f;font-size:.82rem;font-weight:800;text-transform:capitalize}.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin:1rem 0 2rem}.metric{background:var(--card);border:1px solid var(--line);border-radius:.9rem;padding:1.15rem}.metric span{display:block;color:var(--muted);font-size:.85rem}.metric strong{display:block;font-size:1.8rem;margin-top:.25rem}.dashboard-grid{display:grid;grid-template-columns:1.35fr .65fr;gap:1rem}.section{margin-top:2rem}.section h2{margin-bottom:.75rem}.list{list-style:none;padding:0;margin:0}.list li{padding:.8rem 0;border-bottom:1px solid var(--line)}.list li:last-child{border-bottom:0}.gap{color:#8a4b08}.empty{padding:1rem;border:1px dashed #aab8c8;border-radius:.7rem;color:var(--muted)}.capacity{font-size:1.1rem}.manage-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}.disabled{opacity:.78}.disabled h3{margin:.1rem 0}.switcher{display:inline-flex;margin-top:.5rem}@media(max-width:900px){.metric-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid,.manage-grid{grid-template-columns:1fr}}@media(max-width:520px){.metric-grid{grid-template-columns:1fr}.dashboard-head{align-items:flex-start;flex-direction:column}}
</style>
<div class="wrap dashboard">
    <header class="dashboard-head">
        <div><div class="eyebrow">Organization dashboard</div><h1>{{ $organization['name'] }}</h1><span class="badge">{{ $organization['status'] }}</span></div>
        <div><div class="meta">Signed in as {{ auth()->user()->name }}</div><a class="switcher" href="{{ route('access') }}">Switch organization</a></div>
    </header>

    <section aria-labelledby="people-heading"><h2 id="people-heading">People</h2><div class="metric-grid">
        <div class="metric"><span>Total learners</span><strong>{{ $people['learners_total'] }}</strong></div>
        <div class="metric"><span>Active learners</span><strong>{{ $people['learners_active'] }}</strong></div>
        <div class="metric"><span>Suspended learners</span><strong>{{ $people['learners_suspended'] }}</strong></div>
        <div class="metric"><span>Profile-only learners</span><strong>{{ $people['learners_profile_only'] }}</strong></div>
        <div class="metric"><span>Portal-enabled learners</span><strong>{{ $people['learners_portal_enabled'] }}</strong></div>
        <div class="metric"><span>Total staff</span><strong>{{ $people['staff_total'] }}</strong></div>
        <div class="metric"><span>Active staff</span><strong>{{ $people['staff_active'] }}</strong></div>
        <div class="metric"><span>Suspended staff</span><strong>{{ $people['staff_suspended'] }}</strong></div>
    </div></section>

    <section aria-labelledby="academics-heading"><h2 id="academics-heading">Academics</h2><div class="metric-grid">
        <div class="metric"><span>Current academic year</span><strong>{{ $academics['current_year'] ?? 'Not set' }}</strong></div>
        @foreach(['curricula' => 'Curricula', 'grades' => 'Grades', 'classes' => 'Classes', 'subjects' => 'Subjects', 'departments' => 'Departments'] as $key => $label)
            <div class="metric"><span>{{ $label }}</span><strong>{{ $academics[$key] }}</strong></div>
        @endforeach
    </div></section>

    <div class="dashboard-grid">
        <section class="panel" aria-labelledby="access-heading"><h2 id="access-heading">Access and licensing</h2>
            <p class="capacity"><strong>{{ $access['active_memberships'] }}</strong> active users · <strong>{{ $access['pending_memberships'] }}</strong> pending invitations</p>
            <p>License: <span class="badge">{{ str_replace('_', ' ', $access['license_status'] ?? 'Unavailable') }}</span> Subscription: <span class="badge">{{ str_replace('_', ' ', $access['subscription_status'] ?? 'Unavailable') }}</span></p>
            @if($access['maximum_users'] !== null)<p>{{ $access['remaining_users'] }} of {{ $access['maximum_users'] }} licensed user places remaining.</p>@else<p class="empty">No licensed user-capacity limit is available.</p>@endif
            @if($access['grace_period_ends_at'])<p>Grace period ends {{ $access['grace_period_ends_at'] }}.</p>@endif
        </section>
        <section class="panel" aria-labelledby="setup-heading"><h2 id="setup-heading">Setup status</h2>
            @if($setupGaps === [])<p class="empty">No setup gaps were detected from the available records.</p>@else<ul class="list">@foreach($setupGaps as $gap)<li class="gap">{{ $gap }} <span class="meta">Coming later</span></li>@endforeach</ul>@endif
        </section>
    </div>

    <section class="section" aria-labelledby="activity-heading"><h2 id="activity-heading">Recent activity</h2>
        @if($activity->isEmpty())<div class="empty">No recent organization activity is available.</div>@else<div class="panel"><ul class="list">@foreach($activity as $item)<li><strong>{{ ucfirst($item['label']) }}</strong><br><span class="meta">{{ $item['actor'] }} · {{ $item['occurred_at'] }}</span></li>@endforeach</ul></div>@endif
    </section>

    <section class="section" aria-labelledby="management-heading"><h2 id="management-heading">Management areas</h2><div class="manage-grid">
        @if(in_array('learners.view', $permissions ?? [], true))<article class="feature"><h3>Learner management</h3><p>Search and manage organization learner profiles, placement and status.</p><a href="{{ route('learners.index') }}">Open learner management</a></article>@else<article class="feature disabled"><h3>Learner management</h3><p>You do not have permission to view learner management.</p></article>@endif
        @if(in_array('staff.view', $permissions ?? [], true))<article class="feature"><h3>Staff management</h3><p>Manage organization staff profiles and employment status.</p><a href="{{ route('staff.index') }}">Open staff management</a></article>@else<article class="feature disabled"><h3>Staff management</h3><p>You do not have permission to view staff management.</p></article>@endif
        @if(in_array('academics.academic-years.view', $permissions ?? [], true))<article class="feature"><h3>Academic management</h3><p>Manage organization academic structures, calendars and timetable periods.</p><a href="{{ route('academics.web.index') }}">Open academic management</a></article>@else<article class="feature disabled"><h3>Academic management</h3><p>You do not have permission to view academic management.</p></article>@endif
        @if(in_array('attendance.view', $permissions ?? [], true))<article class="feature"><h3>Attendance management</h3><p>Create sessions, record registers, review factual histories and export safe CSV files.</p><a href="{{ route('attendance.index') }}">Open attendance management</a></article>@else<article class="feature disabled"><h3>Attendance management</h3><p>You do not have permission to view attendance management.</p></article>@endif
        @if(in_array('assessments.view', $permissions ?? [], true))<article class="feature"><h3>Assessment management</h3><p>Manage assessments, atomic mark sheets, gradebooks, factual summaries and safe exports.</p><a href="{{ route('assessments.index') }}">Open assessment management</a></article>@else<article class="feature disabled"><h3>Assessment management</h3><p>You do not have permission to view assessment management.</p></article>@endif
        @if(in_array('reports.view', $permissions ?? [], true))<article class="feature"><h3>Report-card management</h3><p>Configure grading, generate immutable academic snapshots, and export PDF or CSV reports.</p><a href="{{ route('reports.dashboard') }}">Open report-card management</a></article>@else<article class="feature disabled"><h3>Report-card management</h3><p>You do not have permission to view reporting.</p></article>@endif
    </div></section>
</div>
@endsection
