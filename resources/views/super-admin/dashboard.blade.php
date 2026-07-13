@extends('super-admin.layout')
@section('content')
<div class="eyebrow">Platform control centre</div><h1>Good day, Super Administrator</h1><p style="color:var(--muted)">Sky Fundi · {{ app()->environment() }} · Health: <span class="pill">{{ $health }}</span></p>
<div class="grid">@foreach(['organizations'=>'Organizations','active_organizations'=>'Active organizations','suspended_organizations'=>'Suspended','users'=>'Users','today_logins'=>"Today's logins",'storage'=>'Storage used'] as $key=>$label)<div class="card"><div style="color:var(--muted)">{{ $label }}</div><div class="metric">{{ number_format($stats[$key]) }}</div></div>@endforeach</div>
<div class="split"><section><h2>Recent organizations</h2><table><tbody>@forelse($organizations_list as $organization)<tr><td>{{ $organization->name }}</td><td><span class="pill">{{ $organization->status->value }}</span></td></tr>@empty<tr><td>No organizations yet</td></tr>@endforelse</tbody></table></section><section><h2>Recent activity</h2><table><tbody>@forelse($activity as $item)<tr><td>{{ $item->action }}</td><td>{{ $item->created_at?->diffForHumans() }}</td></tr>@empty<tr><td>No activity yet</td></tr>@endforelse</tbody></table></section></div>
<section class="card" style="margin-top:20px"><h2>AI providers</h2><p>{{ $providers ? implode(', ', $providers) : 'No provider is currently available.' }}</p></section>
@endsection
