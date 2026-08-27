@extends('leaderboards.layout')
@section('title', $leaderboard->name)
@section('leaderboard-content')
<div class="lb-nav"><div><div class="eyebrow">{{ ucfirst($leaderboard->type->value) }} leaderboard</div><h1>{{ $leaderboard->name }}</h1><p class="meta">{{ $leaderboard->classGroup?->name ?? $leaderboard->grade?->name ?? 'Whole school' }}@if($leaderboard->subject) · {{ $leaderboard->subject->name }}@endif · <span class="chip {{ $leaderboard->visibility->value === 'published' ? 'success' : 'neutral' }}">{{ ucfirst($leaderboard->visibility->value) }}</span></p></div>
@if($canManage)<div class="actions-inline">@if($leaderboard->visibility->value === 'private')<form method="POST" action="{{ route('leaderboards.publish', $leaderboard->uuid) }}" onsubmit="return confirm('Publish this leaderboard? The full ranking becomes visible to everyone in scope.')">@csrf<button>Publish</button></form>@else<form method="POST" action="{{ route('leaderboards.unpublish', $leaderboard->uuid) }}">@csrf<button class="secondary">Make private</button></form>@endif</div>@endif
</div>

@unless($fullyVisible)<p class="meta">This leaderboard is still private — you can only see your own position. Only the principal can make the full ranking visible to everyone.</p>@endunless

@if($entries->isEmpty())<p class="empty">@if($fullyVisible)No entries on this leaderboard.@else You don't have an entry on this leaderboard.@endif</p>@else
<div class="table-wrap"><table class="lb-table"><thead><tr><th>Position</th><th>{{ $fullyVisible ? 'Learner' : '' }}</th><th>{{ $leaderboard->type->value === 'academic' ? 'Average' : 'Points' }}</th></tr></thead><tbody>
@foreach($entries as $entry)
@php($isYou = in_array($entry->learner_profile_id, $viewerLearnerIds ?? [], true))
<tr class="{{ $isYou ? 'is-you' : '' }}">
<td><span class="rank-badge {{ $entry->rank===1?'gold':($entry->rank===2?'silver':($entry->rank===3?'bronze':'')) }}">{{ $entry->rank }}</span></td>
<td>@if($fullyVisible){{ $entry->learner->first_name }} {{ $entry->learner->last_name }}@if($isYou) <span class="chip">You</span>@endif @else @if($isYou)You@endif @endif</td>
<td>{{ number_format((float)$entry->value, 2) }}{{ $leaderboard->type->value === 'academic' ? '%' : ' pts' }}</td>
</tr>
@endforeach
</tbody></table></div>
@endif
<p style="margin-top:1rem"><a href="{{ route('leaderboards.mine') }}">← My standings</a></p>
@endsection
