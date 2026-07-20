@extends('assessments.layout')
@section('title','Review '.$quizAttempt->assessment->title)
@section('assessment-content')
@php($readOnly=$quizAttempt->status==='released' && !in_array('quiz_submissions.override_released',$permissions,true))
<style>
.review-wrap{max-width:860px}
.review-wrap h1{font-size:clamp(1.6rem,3.2vw,2.2rem)}
.rv-card{margin:1rem 0;padding:1.3rem 1.5rem}
.rv-card h2{font-size:1.05rem;font-family:inherit;margin:.1rem 0 .8rem}
.kv{display:grid;grid-template-columns:110px 1fr;gap:.25rem .8rem;font-size:.94rem;margin:.6rem 0}
.kv dt{color:var(--muted);font-weight:700}
.kv dd{margin:0}
.ai-callout{border:1px solid color-mix(in srgb,var(--secondary) 40%,var(--line));border-left:4px solid var(--secondary);background:color-mix(in srgb,var(--secondary) 5%,#fff);border-radius:.7rem;padding:.9rem 1rem;margin:.8rem 0}
.ai-callout .head{display:flex;justify-content:space-between;align-items:center;gap:.8rem;flex-wrap:wrap;margin-bottom:.4rem}
.ai-callout .head strong{color:var(--secondary)}
.mark-row{display:flex;gap:1rem;align-items:start;flex-wrap:wrap;margin-top:.8rem}
.mark-row label{font-weight:700;font-size:.9rem}
.mark-row input[type=number]{display:block;width:130px;margin-top:.3rem;padding:.6rem;border:1px solid #b9c5d3;border-radius:.55rem;font:inherit}
.mark-row .fb{flex:1;min-width:260px}
.mark-row textarea{display:block;width:100%;min-height:74px;margin-top:.3rem;padding:.6rem;border:1px solid #b9c5d3;border-radius:.55rem;font:inherit}
.action-bar{position:sticky;bottom:0;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);border:1px solid var(--line);border-radius:.9rem;padding:.9rem 1.2rem;display:flex;gap:.8rem;align-items:center;box-shadow:var(--shadow);margin-top:1.2rem}
.action-bar .meta{margin-right:auto}
button.ghost{background:#fff;color:var(--secondary);border:1px solid color-mix(in srgb,var(--secondary) 40%,var(--line))}
</style>
<div class="review-wrap">
<div class="eyebrow">Teacher oversight</div><h1>Review: {{ $quizAttempt->assessment->title }}</h1>
<p><span class="chip">{{ $quizAttempt->learner->first_name }} {{ $quizAttempt->learner->last_name }}</span> <span class="chip neutral">{{ str_replace('_',' ',$quizAttempt->status) }}</span></p>
@if($readOnly)<p class="chip warn">Released result — read-only without administrative override.</p>@endif
<form method="POST" action="{{ route('quizzes.review.save',$quizAttempt->uuid) }}">@csrf
@foreach($quizAttempt->answers as $answer)<article class="panel rv-card"><h2>{{ $answer->question->prompt }}</h2>
<dl class="kv"><dt>Learner</dt><dd>{{ $answer->selectedOption?->label ?? $answer->answer_text ?? 'No answer' }}</dd><dt>Model answer</dt><dd>{{ $answer->question->model_answer ?: 'Not supplied' }}</dd><dt>Rubric</dt><dd>{{ $answer->question->marking_guidance ?: 'Not supplied' }}</dd></dl>
@if($answer->ai_feedback)<div class="ai-callout"><div class="head"><strong>AI suggests {{ $answer->ai_suggested_mark }}/{{ $answer->marks_available }}</strong><span><span class="chip neutral">{{ number_format($answer->ai_feedback['confidence']*100) }}% confidence</span> @if($answer->ai_feedback['requires_teacher_review'])<span class="chip warn">Needs your review</span>@endif</span></div>
<p style="margin:.2rem 0">{{ $answer->ai_feedback['grading_rationale'] }}</p>
<dl class="kv"><dt>Strengths</dt><dd>{{ implode('; ',$answer->ai_feedback['strengths']) ?: 'None identified' }}</dd><dt>Improve</dt><dd>{{ implode('; ',$answer->ai_feedback['improvements']) ?: 'None identified' }}</dd><dt>Misconceptions</dt><dd>{{ implode('; ',$answer->ai_feedback['misconceptions']) ?: 'None identified' }}</dd></dl></div>@endif
@if(!$readOnly && !$answer->question->type->isObjective() && !$answer->ai_feedback)<button type="submit" class="ghost small" formaction="{{ route('quizzes.answers.suggest',[$quizAttempt->uuid,$answer->uuid]) }}" formmethod="POST">Suggest mark with AI</button>@elseif(!$readOnly && !$answer->question->type->isObjective() && $answer->ai_feedback)<button type="submit" class="ghost small" name="regenerate" value="1" formaction="{{ route('quizzes.answers.suggest',[$quizAttempt->uuid,$answer->uuid]) }}" formmethod="POST">Regenerate AI once</button>@endif
<div class="mark-row"><label>Final mark (max {{ $answer->marks_available }})<input name="answers[{{ $answer->uuid }}][marks_awarded]" type="number" min="0" max="{{ $answer->marks_available }}" step=".01" value="{{ $answer->marks_awarded ?? $answer->ai_suggested_mark ?? 0 }}" @if($readOnly || $answer->question->type->isObjective())readonly @endif></label>
<label class="fb">Teacher feedback<textarea name="answers[{{ $answer->uuid }}][teacher_feedback]" @if($readOnly)readonly @endif>{{ $answer->teacher_feedback }}</textarea></label></div></article>@endforeach
@if(!$readOnly)<div class="action-bar"><span class="meta">You stay in control — AI only suggests, you approve.</span><button type="submit" class="secondary" name="action" value="draft">Save draft</button> <button type="submit" name="action" value="approve">Approve final marks</button></div>@endif</form>
<section class="panel rv-card"><h2>Study plan and release</h2>
@if(!$quizAttempt->studyPlan && !$readOnly && $quizAttempt->status==='reviewed')<p>The first personalized plan will be generated through the AI Gateway when this result is released.</p>
@elseif($quizAttempt->studyPlan)<p><span class="chip">Version {{ $quizAttempt->studyPlan->version }}</span> <span class="chip neutral">{{ $quizAttempt->studyPlan->status }}</span> <span class="chip success">{{ $quizAttempt->studyPlan->completion_percentage }}% complete</span> <span class="chip neutral">{{ $quizAttempt->studyPlan->time_spent_minutes }} min studied</span></p><p>{{ $quizAttempt->studyPlan->content['summary'] }}</p>
@if(!$readOnly)<form method="POST" action="{{ route('quizzes.study-plan.comment',[$quizAttempt->uuid,$quizAttempt->studyPlan->uuid]) }}" style="margin:.8rem 0">@csrf<label style="font-weight:700;font-size:.9rem">Report to parent<textarea name="teacher_comment" style="display:block;width:100%;min-height:80px;margin-top:.3rem;padding:.6rem;border:1px solid #b9c5d3;border-radius:.55rem;font:inherit" placeholder="Motivate the learner's performance for their guardian.">{{ old('teacher_comment', $quizAttempt->studyPlan->content['teacher_comment'] ?? '') }}</textarea></label><p class="meta" style="margin:.3rem 0">This note reaches the guardian portal and the learner's released result.</p><button class="small">Save report to parent</button></form>@endif
@if(!$readOnly && $quizAttempt->studyPlan->status==='published')<form method="POST" action="{{ route('quizzes.study-plan.generate',$quizAttempt->uuid) }}">@csrf<input type="hidden" name="regenerate" value="1"><button class="secondary small">Regenerate as new version</button></form>@elseif(!$readOnly && $quizAttempt->studyPlan->status==='draft')<form method="POST" action="{{ route('quizzes.study-plan.approve',[$quizAttempt->uuid,$quizAttempt->studyPlan->uuid]) }}">@csrf<button class="small">Publish updated plan</button></form>@endif
@endif
@if(!$readOnly && $quizAttempt->status==='reviewed')<form method="POST" action="{{ route('quizzes.release',$quizAttempt->uuid) }}" style="margin-top:1rem">@csrf<button>Release result to learner and parent</button></form>@endif</section>
</div>
@endsection
