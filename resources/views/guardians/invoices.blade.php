@extends('learners.layout')
@section('title','Billing')
@section('learner-content')
<style>
.portal-wrap{max-width:820px}
.invoice-table{width:100%;border-collapse:collapse;margin-top:.8rem}
.invoice-table th,.invoice-table td{text-align:left;padding:.6rem .5rem;border-bottom:1px solid var(--line);font-size:.92rem}
.invoice-table th{color:var(--muted);font-weight:700;text-transform:uppercase;font-size:.76rem;letter-spacing:.05em}
</style>
<div class="portal-wrap">
<div class="learner-heading"><div><div class="eyebrow">Guardian portal</div><h1>Billing</h1><p>Invoices and payment status for {{ $organization->name }}.</p></div></div>

@if($errors->any())<p class="chip danger">{{ $errors->first('billing') }}</p>@endif
@if(request('paid'))<p class="chip success">Checkout started — this invoice updates once payment is confirmed.</p>@endif
@if(request('cancelled'))<p class="chip warn">Checkout was cancelled.</p>@endif

<section class="panel">
@if($invoices->isEmpty())
<p class="empty">No invoices yet.</p>
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
<form method="POST" action="{{ route('guardians.invoices.pay', ['guardian' => $guardian->uuid, 'invoice' => $invoice->id]) }}">
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
