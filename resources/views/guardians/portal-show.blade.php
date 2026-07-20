@extends('learners.layout')
@section('title','My guardian profile')
@section('learner-content')
<style>
.portal-wrap{max-width:820px}
.learner-card{margin:1rem 0;padding:1.4rem 1.6rem}
.learner-card .head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
.learner-card .head h3{font-family:var(--display);font-size:1.3rem;margin:0}
.score-line{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:.7rem 0}
.qa-row{border-top:1px solid var(--line);padding:.7rem 0}
.qa-row .q{font-weight:700;font-size:.95rem}
.qa-row .f{color:var(--muted);font-size:.92rem;margin-top:.15rem}
.study-box{border:1px solid var(--line);border-radius:.8rem;background:color-mix(in srgb,var(--secondary) 4%,#fff);padding:1rem 1.1rem;margin-top:1rem}
.study-box progress{width:100%;height:.65rem;accent-color:var(--secondary)}
.detail-list-inline{display:flex;gap:1.4rem;flex-wrap:wrap}
.detail-list-inline div dt{font-size:.78rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.detail-list-inline div dd{margin:0;font-weight:600}
</style>
<div class="portal-wrap">
<div class="learner-heading"><div><div class="eyebrow">Guardian portal</div><h1>Welcome, {{ $guardian->first_name }} {{ $guardian->last_name }}</h1><p>Released results and approved study plans for your linked learners appear here as soon as teachers share them.</p></div></div>
<section class="panel"><h2>Contact preferences</h2><dl class="detail-list-inline">@foreach([['Email',$guardian->email],['Phone',$guardian->phone],['Preferred channel',ucfirst($guardian->preferred_communication_channel)]] as [$label,$value])<div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>@endforeach</dl></section>
@if($guardian->relationships->isEmpty())<section class="panel" style="margin-top:1rem"><p class="empty">No current learner relationship is available. Your school links learners to your profile.</p></section>
@else @foreach($guardian->relationships as $relationship)
@php($summary=$academicSummaries[$relationship->learner->getKey()] ?? null)
<section class="panel learner-card"><div class="head"><h3>{{ $relationship->learner->first_name }} {{ $relationship->learner->last_name }}</h3><span class="chip neutral">{{ ucfirst(str_replace('_',' ',$relationship->relationship_type)) }}</span></div>
@if(!$summary)<p class="empty">No released quiz summary yet — you will see results here the moment a teacher releases them.</p>
@else
<div class="score-line"><span class="chip">{{ $summary->assessment->subject?->name }}</span><strong>{{ $summary->assessment->title }}</strong></div>
<div class="score-line"><span class="meta">Latest released score</span><span class="chip {{ (float)$summary->result->percentage >= 50 ? 'success' : 'warn' }}">{{ $summary->final_score }}/{{ $summary->assessment->maximum_mark }} · {{ number_format((float)$summary->result->percentage,1) }}%</span></div>
@foreach($summary->answers as $answer)<div class="qa-row"><div class="q">{{ $answer->question->prompt }} <span class="chip neutral">{{ $answer->marks_awarded }}/{{ $answer->marks_available }}</span></div><div class="f">{{ $answer->teacher_feedback ?: 'Marked objectively.' }}</div></div>@endforeach
@if($summary->publishedStudyPlan)<div class="study-box"><strong>Overall study progress</strong><progress max="100" value="{{ $summary->publishedStudyPlan->completion_percentage }}">{{ $summary->publishedStudyPlan->completion_percentage }}%</progress>
<p class="meta">{{ count($summary->publishedStudyPlan->completed_activities ?? []) }} activities done · {{ $summary->publishedStudyPlan->time_spent_minutes }} minutes studied · Remaining: {{ implode(', ',$summary->publishedStudyPlan->remaining_concepts ?? []) ?: 'nothing — all concepts covered' }}</p>
<p style="margin:.3rem 0 0"><strong>Teacher's note:</strong> {{ $summary->publishedStudyPlan->content['teacher_comment'] }}</p></div>@endif
@endif</section>
@endforeach @endif
</div>
@endsection
