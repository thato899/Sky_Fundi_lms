@extends('assessments.layout')
@section('title','Assessment mark sheet')
@section('assessment-content')
<h1>{{ $assessment->title }}</h1>
<p>{{ $assessment->academicTerm->name }} · {{ $assessment->grade->name }} · {{ $assessment->classGroup->name }} · {{ $assessment->subject->name }} · maximum {{ $assessment->maximum_mark }}</p>
<p><strong>{{ $assessment->status->value }}</strong> · results {{ $assessment->result_release_status->value }}</p>
<form method="POST" action="{{ route('assessments.marks',$assessment->uuid) }}">@csrf
<div class="table-wrap"><table class="table"><thead><tr><th>Learner</th><th>Status</th><th>Score</th><th>Percentage</th><th>Feedback</th>@if(in_array('assessments.mark',$permissions,true))<th>Private note</th>@endif</tr></thead><tbody>
@foreach($assessment->results as $i=>$result)<tr>
<td>{{ $result->learner->learner_number }}<br>{{ $result->learner->first_name }} {{ $result->learner->last_name }}<input type="hidden" name="results[{{ $i }}][result_uuid]" value="{{ $result->uuid }}"></td>
<td><select name="results[{{ $i }}][result_status]" @disabled($assessment->status->value==='finalized'||$assessment->status->value==='cancelled')>@foreach($statuses as $s)<option value="{{ $s->value }}" @selected($result->result_status===$s)>{{ str_replace('_',' ',$s->value) }}</option>@endforeach</select></td>
<td><input type="number" step="0.01" min="0" max="{{ $assessment->maximum_mark }}" name="results[{{ $i }}][score]" value="{{ old("results.$i.score",$result->score) }}" @disabled($assessment->status->value==='finalized'||$assessment->status->value==='cancelled')></td>
<td>{{ $result->percentage === null ? '—' : $result->percentage.'%' }}</td><td><input name="results[{{ $i }}][feedback]" value="{{ $result->feedback }}" @disabled($assessment->status->value==='finalized'||$assessment->status->value==='cancelled')></td>
@if(in_array('assessments.mark',$permissions,true))<td><input name="results[{{ $i }}][private_note]" value="{{ $result->private_note }}" @disabled($assessment->status->value==='finalized'||$assessment->status->value==='cancelled')></td>@endif
</tr>@endforeach</tbody></table></div>
@if(in_array($assessment->status->value,['draft','open'],true)&&in_array('assessments.mark',$permissions,true))<button>Save complete mark sheet</button>@endif</form>
<div class="actions">
@if(in_array($assessment->status->value,['draft','open'],true)&&in_array('assessments.finalize',$permissions,true))<form method="POST" action="{{ route('assessments.finalize',$assessment->uuid) }}">@csrf<button>Finalize and lock</button></form>@endif
@if($assessment->status->value==='finalized'&&in_array('assessments.reopen',$permissions,true))<form method="POST" action="{{ route('assessments.reopen',$assessment->uuid) }}">@csrf<input required name="reason" placeholder="Required reopening reason"><button>Reopen</button></form>@endif
@if($assessment->status->value==='finalized'&&in_array('assessments.release',$permissions,true))<form method="POST" action="{{ route($assessment->result_release_status->value==='released'?'assessments.withhold':'assessments.release',$assessment->uuid) }}">@csrf<button>{{ $assessment->result_release_status->value==='released'?'Withhold results':'Release results' }}</button></form>@endif
@if(in_array('assessments.export',$permissions,true))<a class="button secondary" href="{{ route('assessments.export',$assessment->uuid) }}">Export safe CSV</a>@endif
</div>
@endsection
