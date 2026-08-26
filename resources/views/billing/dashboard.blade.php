@extends('layouts.web')
@section('title','Billing')
@section('content')
<style>
.billing-page{padding:2rem 0 4rem}
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:.8rem}
.plan-card{position:relative}
.plan-card.current{border-color:var(--primary);box-shadow:inset 0 0 0 1px var(--primary),var(--shadow)}
.plan-card .price{font-family:var(--display);font-size:1.6rem;font-weight:700}
.invoice-table{width:100%;border-collapse:collapse;margin-top:.8rem}
.invoice-table th,.invoice-table td{text-align:left;padding:.6rem .5rem;border-bottom:1px solid var(--line);font-size:.92rem}
.invoice-table th{color:var(--muted);font-weight:700;text-transform:uppercase;font-size:.76rem;letter-spacing:.05em}
section{margin-top:1.8rem}
h2{margin-bottom:.5rem}
@media(max-width:800px){.plans{grid-template-columns:1fr}}
</style>
<div class="wrap billing-page">
<a href="{{ route('dashboard') }}">← Dashboard</a>
<div class="eyebrow" style="margin-top:.8rem">Billing</div>
<h1 style="font-size:clamp(1.8rem,3.6vw,2.6rem)">Subscription and invoices</h1>

@if($errors->any())<p class="chip danger">{{ $errors->first('billing') }}</p>@endif
@if(request('subscribed'))<p class="chip success">Checkout started — your subscription activates once payment is confirmed.</p>@endif
@if(request('paid'))<p class="chip success">Checkout started — this invoice updates once payment is confirmed.</p>@endif
@if(request('cancelled'))<p class="chip warn">Checkout was cancelled.</p>@endif

<section class="panel">
<h2>Current subscription</h2>
@if($subscription)
<p><span class="chip {{ $subscription->status->value === 'active' ? 'success' : 'warn' }}">{{ ucfirst(str_replace('_',' ',$subscription->status->value)) }}</span> {{ ucfirst($subscription->plan) }} plan · {{ ucfirst($subscription->billing_cycle->value) }}</p>
<p class="meta">Renews {{ $subscription->renewal_date?->toFormattedDateString() ?? 'not scheduled' }}</p>
@else
<p class="empty">No subscription yet — choose a plan below to start one.</p>
@endif
</section>

<section>
<h2>Plans</h2>
<div class="plans">
@foreach($plans as $candidate)
<article class="panel plan-card {{ $subscription && $subscription->plan === $candidate->key ? 'current' : '' }}">
@if($subscription && $subscription->plan === $candidate->key)<span class="chip" style="position:absolute;top:1rem;right:1rem">Current</span>@endif
<h3 style="margin-top:0">{{ $candidate->name }}</h3>
<div class="price">R{{ number_format((float)$candidate->price) }}<span class="meta" style="font-size:.9rem;font-family:system-ui">/{{ $candidate->billing_cycle->value }}</span></div>
<p>{{ $candidate->max_learners }} learners · {{ $candidate->max_staff }} staff · {{ $candidate->ai_allowance }} AI markings</p>
@if(!$subscription || $subscription->plan !== $candidate->key)
<form method="POST" action="{{ route('billing.subscribe') }}">
@csrf
<input type="hidden" name="plan_key" value="{{ $candidate->key }}">
<button type="submit" class="button">Subscribe</button>
</form>
@endif
</article>
@endforeach
</div>
</section>

<section class="panel">
<h2>Invoices</h2>
@if($invoices->isEmpty())
<p class="empty">No invoices yet — the first is generated automatically at your next billing cycle, from actual attendance and enrolment data.</p>
@else
<table class="invoice-table">
<thead><tr><th>Period</th><th>Status</th><th>Total</th><th>Due</th><th></th></tr></thead>
<tbody>
@foreach($invoices as $invoice)
<tr>
<td>{{ $invoice->billing_period_start->toFormattedDateString() }} – {{ $invoice->billing_period_end->toFormattedDateString() }}</td>
<td><span class="chip {{ $invoice->status->value === 'paid' ? 'success' : ($invoice->status->value === 'overdue' ? 'danger' : 'neutral') }}">{{ ucfirst($invoice->status->value) }}</span></td>
<td>{{ $invoice->currency }} {{ number_format((float)$invoice->total,2) }}</td>
<td>{{ $invoice->due_date?->toFormattedDateString() ?? '—' }}</td>
<td>
@if($invoice->isPayable())
<form method="POST" action="{{ route('billing.invoices.pay',$invoice->id) }}">
@csrf
<button type="submit" class="button">Pay now</button>
</form>
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
@endif
</section>
</div>
@endsection
