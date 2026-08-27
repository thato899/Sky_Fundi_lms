@extends('leaderboards.layout')
@section('title', 'My leaderboard standings')
@section('leaderboard-content')
<h1>My leaderboard standings</h1><p>Your own position and average or points on every leaderboard you appear on. Other learners' positions are only shown once a leaderboard has been published by the principal.</p>
@if($entriesByLeaderboard->isEmpty())<p class="empty">You don't have an entry on any leaderboard yet.</p>@else
<div class="table-wrap"><table class="lb-table"><thead><tr><th>Leaderboard</th><th>Learner</th><th>Position</th><th>Value</th><th>Visibility</th><th></th></tr></thead><tbody>
@foreach($entriesByLeaderboard as $leaderboardId => $entries)
@foreach($entries as $entry)
<tr>
<td>{{ $entry->leaderboard->name }}</td>
<td>{{ $entry->learner->first_name }} {{ $entry->learner->last_name }}</td>
<td><span class="rank-badge {{ $entry->rank===1?'gold':($entry->rank===2?'silver':($entry->rank===3?'bronze':'')) }}">{{ $entry->rank }}</span></td>
<td>{{ number_format((float)$entry->value, 2) }}{{ $entry->leaderboard->type->value === 'academic' ? '%' : ' pts' }}</td>
<td><span class="chip {{ $entry->leaderboard->visibility->value === 'published' ? 'success' : 'neutral' }}">{{ ucfirst($entry->leaderboard->visibility->value) }}</span></td>
<td><a href="{{ route('leaderboards.show', $entry->leaderboard->uuid) }}">View</a></td>
</tr>
@endforeach
@endforeach
</tbody></table></div>
@endif
@endsection
