@extends('layouts.web')
@section('title','Subscription and profitability')
@section('content')
<style>
.billing{padding:2rem 0 4rem}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
.metric{background:#fff;border:1px solid var(--line);border-radius:.9rem;padding:1rem}
.metric span{font-size:.82rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.metric strong{font-size:1.55rem;display:block;font-variant-numeric:tabular-nums;font-family:var(--display)}
.margin-hero{display:grid;grid-template-columns:1fr 1.2fr;gap:2rem;align-items:center;background:#fff;border:1px solid var(--line);border-radius:1rem;padding:1.8rem 2rem;box-shadow:var(--shadow);margin:1.4rem 0}
.margin-hero .big{font-family:var(--display);font-weight:700;font-size:clamp(2.4rem,5vw,3.4rem);line-height:1;font-variant-numeric:tabular-nums}
.bar-row{display:grid;grid-template-columns:96px 1fr 110px;gap:.7rem;align-items:center;margin:.55rem 0;font-size:.92rem}
.bar-row .lbl{font-weight:700}
.bar-row .val{text-align:right;font-variant-numeric:tabular-nums;color:var(--ink)}
.bar-track{background:#eef2f6;border-radius:4px;height:14px;position:relative}
.bar-fill{height:14px;border-radius:0 4px 4px 0;min-width:3px}
.bar-fill.revenue{background:#175cd3}
.bar-fill.cost{background:#b54708}
.meter{margin:.4rem 0 0}
.meter .bar-track{height:8px}
.meter .bar-fill{height:8px;background:var(--secondary)}
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
.plan-card{position:relative}
.plan-card.current{border-color:var(--primary);box-shadow:inset 0 0 0 1px var(--primary),var(--shadow)}
.plan-card .price{font-family:var(--display);font-size:1.6rem;font-weight:700}
section{margin-top:1.8rem}
h2{margin-bottom:.7rem}
@media(max-width:800px){.grid,.plans{grid-template-columns:1fr}.margin-hero{grid-template-columns:1fr}}
</style>
<div class="wrap billing"><a href="{{ route('dashboard') }}">← Dashboard</a><div class="eyebrow" style="margin-top:.8rem">Demo billing · estimates</div><h1 style="font-size:clamp(1.8rem,3.6vw,2.6rem)">Subscription and profitability</h1>
<p><span class="chip warn">Billing integration pending</span> All prices and margins are configurable demonstration assumptions, not audited financial information.</p>

@php($maxBar = max($financials['revenue'], $financials['variable_cost'], 1))
<div class="margin-hero"><div><span class="metric" style="border:0;padding:0"><span>Contribution margin · {{ $plan['name'] }} plan</span></span><div class="big">R{{ number_format($financials['margin'],2) }}</div>
<p style="margin:.5rem 0 0"><span class="chip {{ $financials['margin'] >= 0 ? 'success' : 'danger' }}">{{ number_format($financials['margin_percent'],1) }}% margin</span> <span class="meta">per school per month</span></p></div>
<div role="img" aria-label="Monthly revenue R{{ number_format($financials['revenue'],2) }} against variable cost R{{ number_format($financials['variable_cost'],2) }}">
<div class="bar-row"><span class="lbl">Revenue</span><div class="bar-track"><div class="bar-fill revenue" style="width:{{ round($financials['revenue']/$maxBar*100,1) }}%"></div></div><span class="val">R{{ number_format($financials['revenue'],2) }}</span></div>
<div class="bar-row"><span class="lbl">Cost</span><div class="bar-track"><div class="bar-fill cost" style="width:{{ round($financials['variable_cost']/$maxBar*100,1) }}%"></div></div><span class="val">R{{ number_format($financials['variable_cost'],2) }}</span></div>
<p class="meta" style="margin:.6rem 0 0">Variable cost = AI marking (R{{ number_format($usage['ai_cost'],4) }}) + notifications, hosting and support allocations.</p></div></div>

<section><h2>Licence and usage</h2><div class="grid">
<div class="metric"><span>Learners</span><strong>{{ $usage['learners'] }}/{{ $license?->max_learners ?? $plan['learners'] }}</strong><div class="meter"><div class="bar-track"><div class="bar-fill" style="width:{{ min(100, round($usage['learners']/max(1,(int)($license?->max_learners ?? $plan['learners']))*100)) }}%"></div></div></div></div>
<div class="metric"><span>Active staff</span><strong>{{ $usage['staff'] }}/{{ $plan['staff'] }}</strong><div class="meter"><div class="bar-track"><div class="bar-fill" style="width:{{ min(100, round($usage['staff']/max(1,(int)$plan['staff'])*100)) }}%"></div></div></div></div>
<div class="metric"><span>AI markings this month</span><strong>{{ $usage['ai_requests'] }}/{{ $plan['ai_allowance'] }}</strong><div class="meter"><div class="bar-track"><div class="bar-fill" style="width:{{ min(100, round($usage['ai_requests']/max(1,(int)$plan['ai_allowance'])*100)) }}%"></div></div></div></div>
<div class="metric"><span>Estimated AI cost</span><strong>R{{ number_format($usage['ai_cost'],4) }}</strong><small class="meta">this month</small></div>
</div></section>

<section><h2>Traction this month</h2><div class="grid">@foreach($metrics as $label=>$value)<div class="metric"><span>{{ ucwords(str_replace('_',' ',$label)) }}</span><strong>{{ $value }}</strong><small class="meta">Seeded demonstration data</small></div>@endforeach</div></section>

<section><h2>Upgrade scenarios</h2><div class="plans">@foreach($plans as $key=>$candidate)<article class="panel plan-card {{ strtolower($plan['name'])===strtolower($candidate['name']) ? 'current' : '' }}">@if(strtolower($plan['name'])===strtolower($candidate['name']))<span class="chip" style="position:absolute;top:1rem;right:1rem">Current</span>@endif<h3 style="margin-top:0">{{ $candidate['name'] }}</h3><div class="price">R{{ number_format($candidate['price']) }}<span class="meta" style="font-size:.9rem;font-family:system-ui">/month</span></div><p>{{ $candidate['learners'] }} learners · {{ $candidate['staff'] }} staff · {{ $candidate['ai_allowance'] }} AI markings</p></article>@endforeach</div></section>

<section class="panel"><h2>Assumptions and scale story</h2><p>Variable cost includes configured AI usage, notification cost, hosting allocation and support allocation. Taxes, payment-processing fees and add-ons are excluded.</p><p><strong>Scenario:</strong> 10 Growth schools produce estimated MRR of <strong>R{{ number_format(10*config('hackathon.plans.growth.price'),2) }}</strong> before add-ons — AI marking cost stays under {{ number_format($financials['revenue'] > 0 ? max($usage['ai_cost'],0.01)/$financials['revenue']*100 : 0,2) }}% of revenue at current usage.</p></section></div>
@endsection
