@extends('assessments.layout')
@section('title',$quizAttempt->assessment->title)
@section('assessment-content')
<style>
.quiz-head{max-width:760px}
.quiz-head h1{font-size:clamp(1.7rem,3.5vw,2.4rem);margin:.4rem 0 .6rem}
.quiz-meta{display:flex;gap:.5rem;flex-wrap:wrap;margin:.6rem 0 1.4rem}
.q-card{max-width:760px;margin:1rem 0;padding:1.35rem 1.5rem}
.q-top{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.5rem}
.q-num{font-family:var(--display);font-weight:700;color:var(--primary);font-size:.95rem}
.q-card h2{font-size:1.12rem;font-family:inherit;font-weight:700;margin:.2rem 0 .9rem;letter-spacing:0}
.option{display:flex;align-items:center;gap:.7rem;border:1px solid var(--line);border-radius:.7rem;padding:.75rem .9rem;margin:.5rem 0;cursor:pointer;background:#fff;transition:border-color .12s,background .12s}
.option:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--line))}
.option:has(input:checked){border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,#fff);box-shadow:inset 0 0 0 1px var(--primary)}
.option input{accent-color:var(--primary)}
.q-card textarea{width:100%;min-height:140px;padding:.8rem;border:1px solid #b9c5d3;border-radius:.6rem;font:inherit}
.q-card textarea:focus{outline:3px solid color-mix(in srgb,var(--primary) 22%,transparent);border-color:var(--primary)}
.submit-bar{position:sticky;bottom:0;max-width:760px;margin:1.5rem 0 0;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);border:1px solid var(--line);border-radius:.9rem;padding:.9rem 1.2rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;box-shadow:var(--shadow)}
</style>
<div class="quiz-head"><div class="eyebrow">{{ $quizAttempt->assessment->subject?->name ?? 'Quiz' }}</div><h1>{{ $quizAttempt->assessment->title }}</h1>
@if($quizAttempt->assessment->instructions)<p>{{ $quizAttempt->assessment->instructions }}</p>@endif
<div class="quiz-meta"><span class="chip neutral">{{ $quizAttempt->answers->count() }} questions</span><span class="chip neutral">{{ $quizAttempt->answers->sum('marks_available') }} marks</span><span class="chip">Answers are final on submit</span></div></div>
@if($quizAttempt->status==='in_progress')<form method="POST" action="{{ route('quizzes.submit',$quizAttempt->uuid) }}">@csrf
@foreach($quizAttempt->answers as $answer)<section class="panel q-card"><div class="q-top"><span class="q-num">Question {{ $answer->question->display_order }}</span><span class="chip neutral">{{ $answer->marks_available }} {{ \Illuminate\Support\Str::plural('mark',(int) $answer->marks_available) }}</span></div><h2>{{ $answer->question->prompt }}</h2>
@if($answer->question->type->isObjective())@foreach($answer->question->options as $option)<label class="option"><input type="radio" name="answers[{{ $answer->question->uuid }}][selected_option_uuid]" value="{{ $option->uuid }}"><span>{{ $option->label }}</span></label>@endforeach
@else<textarea name="answers[{{ $answer->question->uuid }}][answer_text]" placeholder="Write your answer here."></textarea>@endif</section>@endforeach
<div class="submit-bar"><span class="meta">Check your answers — you cannot edit after submitting.</span><button type="submit">Submit final answers</button></div></form>
@else<section class="panel q-card"><h2>Submitted and locked</h2><p>This attempt was submitted and can no longer be edited. Your marks and feedback appear here once your teacher releases them.</p><p><a class="button secondary" href="{{ route('quizzes.assigned') }}">Back to my quizzes</a></p></section>@endif
@endsection
