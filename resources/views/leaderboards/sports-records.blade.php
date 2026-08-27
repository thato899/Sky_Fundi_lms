@extends('leaderboards.layout')
@section('title', 'Sports results')
@section('leaderboard-content')
<h1>Sports results</h1><p>Log points for a sports activity or event. These feed the sports leaderboard the principal can generate and publish.</p>
<section class="panel"><h2>Log a result</h2>
<form class="lb-form" method="POST" action="{{ route('leaderboards.sports-records.store') }}">@csrf
<label>Learner<select name="learner_profile_id" required><option value="">Choose a learner</option>@foreach($learners as $learner)<option value="{{ $learner->id }}">{{ $learner->first_name }} {{ $learner->last_name }}</option>@endforeach</select></label>
<label>Activity<input name="activity" required maxlength="255" placeholder="e.g. Athletics — 100m"></label>
<label>Points<input type="number" name="points" min="0" step=".01" required></label>
<label>Date<input type="date" name="event_date" required value="{{ now()->toDateString() }}"></label>
<label class="wide">Notes (optional)<textarea name="notes" rows="2" maxlength="2000"></textarea></label>
<div class="wide"><button type="submit">Log result</button></div>
</form></section>

<section style="margin-top:1.5rem"><h2>Recent results</h2>
@if($records->isEmpty())<p class="empty">No sports results have been logged yet.</p>@else
<div class="table-wrap"><table class="lb-table"><thead><tr><th>Date</th><th>Learner</th><th>Activity</th><th>Points</th></tr></thead><tbody>
@foreach($records as $record)<tr><td>{{ $record->event_date->toDateString() }}</td><td>{{ $record->learner->first_name }} {{ $record->learner->last_name }}</td><td>{{ $record->activity }}</td><td>{{ number_format((float)$record->points, 2) }}</td></tr>@endforeach
</tbody></table></div>
@endif
</section>
@endsection
