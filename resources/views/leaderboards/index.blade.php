@extends('leaderboards.layout')
@section('title', 'Leaderboards')
@section('leaderboard-content')
<h1>Leaderboards</h1><p>Rank learners by overall average or a specific subject, and by sports points. Every leaderboard starts private — only you (and each learner's own position) can see it until you publish it.</p>

@if($canManage)
<div class="staff-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem">
<section class="panel"><h2>Generate an academic leaderboard</h2>
<form class="lb-form" method="POST" action="{{ route('leaderboards.academic.store') }}">@csrf
<label class="wide">Reporting period<select name="reporting_period_id" required><option value="">Choose a reporting period</option>@foreach($reportingPeriods as $period)<option value="{{ $period->id }}">{{ $period->name }}</option>@endforeach</select></label>
<label>Subject (optional)<select name="subject_id"><option value="">Overall average</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
<label>Grade (optional)<select name="grade_id"><option value="">Whole school</option>@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></label>
<label>Class (optional)<select name="class_id"><option value="">Any class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
<label class="wide">Name (optional)<input name="name" maxlength="255" placeholder="Auto-generated if left blank"></label>
<div class="wide"><button type="submit">Generate academic leaderboard</button></div>
</form></section>

<section class="panel"><h2>Generate a sports leaderboard</h2><p class="meta">Ranked by points logged on the <a href="{{ route('leaderboards.sports-records.index') }}">sports results</a> page.</p>
<form class="lb-form" method="POST" action="{{ route('leaderboards.sports.store') }}">@csrf
<label>From<input type="date" name="period_start_date" required></label>
<label>To<input type="date" name="period_end_date" required></label>
<label>Grade (optional)<select name="grade_id"><option value="">Whole school</option>@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></label>
<label>Class (optional)<select name="class_id"><option value="">Any class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
<label class="wide">Name (optional)<input name="name" maxlength="255" placeholder="Auto-generated if left blank"></label>
<div class="wide"><button type="submit">Generate sports leaderboard</button></div>
</form></section>
</div>
@endif

<section style="margin-top:1.5rem"><h2>All leaderboards</h2>
@if($leaderboards->isEmpty())<p class="empty">No leaderboards have been generated yet.</p>@else
<div class="table-wrap"><table class="lb-table"><thead><tr><th>Name</th><th>Type</th><th>Scope</th><th>Visibility</th><th>Generated</th><th></th></tr></thead><tbody>
@foreach($leaderboards as $board)
<tr>
<td><a href="{{ route('leaderboards.show', $board->uuid) }}">{{ $board->name }}</a></td>
<td>{{ ucfirst($board->type->value) }}</td>
<td>{{ $board->classGroup?->name ?? $board->grade?->name ?? 'Whole school' }}{{ $board->subject ? ' · '.$board->subject->name : '' }}</td>
<td><span class="chip {{ $board->visibility->value === 'published' ? 'success' : 'neutral' }}">{{ ucfirst($board->visibility->value) }}</span></td>
<td>{{ $board->generated_at?->toDayDateTimeString() }}</td>
<td>@if($canManage)<div class="actions-inline">
@if($board->visibility->value === 'private')<form method="POST" action="{{ route('leaderboards.publish', $board->uuid) }}" onsubmit="return confirm('Publish this leaderboard? The full ranking becomes visible to everyone in scope.')">@csrf<button class="small">Publish</button></form>
@else<form method="POST" action="{{ route('leaderboards.unpublish', $board->uuid) }}">@csrf<button class="small secondary">Make private</button></form>@endif
</div>@endif</td>
</tr>
@endforeach
</tbody></table></div>
@endif
</section>
@endsection
