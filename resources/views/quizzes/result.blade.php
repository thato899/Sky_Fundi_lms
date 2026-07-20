@extends('assessments.layout')
@section('title','Result: '.$quizAttempt->assessment->title)
@section('assessment-content')
<style>
.result-hero{max-width:820px;display:flex;align-items:center;gap:1.5rem;padding:1.6rem 1.8rem;border-radius:1rem;background:linear-gradient(120deg,color-mix(in srgb,var(--primary) 10%,#fff),color-mix(in srgb,var(--secondary) 10%,#fff));border:1px solid var(--line);margin:1rem 0 1.5rem}
.score-badge{flex:none;width:110px;height:110px;border-radius:50%;display:grid;place-items:center;background:#fff;border:6px solid var(--primary);font-family:var(--display);font-weight:700;font-size:1.55rem}
.score-badge small{display:block;font:600 .68rem/1 system-ui;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;text-align:center}
.answer-card{max-width:820px;margin:.8rem 0;padding:1.1rem 1.3rem;border-left:5px solid var(--line)}
.answer-card.full{border-left-color:var(--success)}
.answer-card.partial{border-left-color:var(--warn)}
.answer-card.zero{border-left-color:var(--danger)}
.answer-card h2{font-size:1.02rem;font-family:inherit;margin:.1rem 0 .4rem}
.answer-top{display:flex;justify-content:space-between;gap:1rem;align-items:baseline}
.plan{max-width:820px;margin-top:1.6rem}
.plan h3{margin:1.4rem 0 .5rem}
.day-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.7rem}
.day-card{border:1px solid var(--line);border-radius:.7rem;padding:.7rem .8rem;background:#fff;display:block;cursor:pointer}
.day-card:has(input:checked){border-color:var(--success);background:#f4fdf8}
.day-card .d{font-weight:800;color:var(--secondary);font-size:.78rem;letter-spacing:.06em;text-transform:uppercase}
.exercise{border:1px solid var(--line);border-radius:.8rem;padding:1rem;margin:.8rem 0;background:#fff}
.exercise textarea{width:100%;min-height:90px;margin-top:.5rem;padding:.7rem;border:1px solid #b9c5d3;border-radius:.55rem;font:inherit}
.two-lists{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.plan progress{width:100%;height:.7rem;accent-color:var(--secondary)}
.session-row{display:flex;gap:1rem;align-items:end;flex-wrap:wrap;margin:.8rem 0}
.session-row input{width:110px;padding:.6rem;border:1px solid #b9c5d3;border-radius:.55rem}
@media(max-width:640px){.result-hero{flex-direction:column;text-align:center}.two-lists{grid-template-columns:1fr}}
</style>
<div class="eyebrow">Released result</div><h1 style="font-size:clamp(1.7rem,3.5vw,2.4rem)">{{ $quizAttempt->assessment->title }}</h1>
<div class="result-hero"><div class="score-badge"><span>{{ number_format((float)$quizAttempt->result->percentage,0) }}%<small>{{ $quizAttempt->final_score }}/{{ $quizAttempt->assessment->maximum_mark }}</small></span></div>
<div><h2 style="margin:.1rem 0 .35rem">Marked and released by your teacher</h2><p style="margin:0">Every question below shows your marks and feedback. @if($quizAttempt->publishedStudyPlan) Your personalized study plan is ready underneath.@endif</p></div></div>
@foreach($quizAttempt->answers as $answer)
@php($ratio = $answer->marks_available > 0 ? $answer->marks_awarded / $answer->marks_available : 0)
<article class="panel answer-card {{ $ratio >= 1 ? 'full' : ($ratio > 0 ? 'partial' : 'zero') }}"><div class="answer-top"><h2>{{ $answer->question->prompt }}</h2><span class="chip {{ $ratio >= 1 ? 'success' : ($ratio > 0 ? 'warn' : 'danger') }}">{{ $answer->marks_awarded }}/{{ $answer->marks_available }}</span></div><p style="margin:.2rem 0 0">{{ $answer->teacher_feedback ?: 'Marked objectively.' }}</p></article>
@endforeach
@php($plan=$quizAttempt->publishedStudyPlan)
@if($plan)
<section class="panel plan"><div class="eyebrow">Adaptive revision · version {{ $plan->version }}</div><h2>Your personalized study plan</h2>
<p>{{ $plan->content['summary'] }}</p>
<div class="metric-grid"><div class="metric"><span>Current mastery</span><strong class="metric-num">{{ count($plan->mastered_concepts ?? []) }}/{{ count($plan->content['weak_concepts']) }}</strong></div><div class="metric"><span>Progress</span><strong class="metric-num">{{ $plan->completion_percentage }}%</strong></div><div class="metric"><span>Study time</span><strong class="metric-num">{{ $plan->time_spent_minutes }} min</strong></div><div class="metric"><span>Next quiz readiness</span><strong>{{ empty($plan->remaining_concepts) ? 'Ready' : 'Building' }}</strong></div></div>
<progress max="100" value="{{ $plan->completion_percentage }}">{{ $plan->completion_percentage }}%</progress>
<div class="two-lists"><div><h3>Weak concepts</h3><ul>@foreach($plan->content['weak_concepts'] as $concept)<li>{{ $concept }}</li>@endforeach</ul></div><div><h3>Learning goals</h3><ul>@foreach($plan->content['learning_goals'] as $goal)<li>{{ $goal }}</li>@endforeach</ul></div></div>
<h3>Your 7-day schedule</h3><form method="POST" action="{{ route('quizzes.study-plan.progress',[$quizAttempt->uuid,$plan->uuid]) }}">@csrf
<div class="day-grid">@foreach($plan->content['daily_schedule'] as $activity)<label class="day-card"><span class="d">Day {{ $activity['day'] }} · {{ $activity['duration_minutes'] }} min</span><br><input type="checkbox" name="completed_activity_ids[]" value="{{ $activity['activity_id'] }}" @checked(in_array($activity['activity_id'],$plan->completed_activities ?? [],true))> <strong>{{ $activity['topic'] }}</strong><br><span class="meta">{{ $activity['activity'] }}</span></label>@endforeach</div>
<div class="session-row"><label>Time spent this session (minutes)<br><input type="number" name="time_spent_minutes" min="1" max="1440" value="30"></label><button class="small">Update progress</button></div></form>
<h3>Targeted revision exercises</h3><form method="POST" action="{{ route('quizzes.study-plan.retest',[$quizAttempt->uuid,$plan->uuid]) }}">@csrf
@foreach($plan->content['revision_exercises'] as $exercise)<article class="exercise"><span class="chip {{ $exercise['difficulty']==='hard' ? 'danger' : ($exercise['difficulty']==='medium' ? 'warn' : 'neutral') }}">{{ ucfirst($exercise['difficulty']) }}</span><p style="margin:.5rem 0 0"><strong>{{ $exercise['concept'] }}:</strong> {{ $exercise['question'] }}</p><textarea name="responses[{{ $exercise['activity_id'] }}]" required placeholder="Work through it here."></textarea></article>@endforeach<button>Submit adaptive retest</button></form>
<h3>Reflection questions</h3><ul>@foreach($plan->content['reflection_questions'] as $question)<li>{{ $question }}</li>@endforeach</ul>
<div class="two-lists"><div><h3>Recommended videos</h3><ul>@foreach($plan->content['recommended_videos'] as $resource)<li><strong>{{ $resource['title'] }}</strong> — search for “{{ $resource['search_topic'] }}”</li>@endforeach</ul></div><div><h3>Recommended reading</h3><ul>@foreach($plan->content['recommended_reading'] as $resource)<li><strong>{{ $resource['title'] }}</strong> — {{ $resource['description'] }}</li>@endforeach</ul></div></div>
<p><strong>Estimated duration:</strong> {{ $plan->content['estimated_duration_minutes'] }} minutes · <strong>Next assessment:</strong> {{ $plan->content['next_assessment_recommendation'] }}</p>
@if($plan->revisionAttempts->isNotEmpty())@php($revision=$plan->revisionAttempts->sortByDesc('attempt_number')->first())<p class="chip success">Latest retest: {{ $revision->score_percentage }}%</p><p class="meta">{{ $revision->evaluation['feedback'] }}</p>@endif
</section>
@endif
@endsection
