@extends('assessments.layout')
@section('title', $quiz->title)
@section('assessment-content')
<header class="bar"><div><div class="eyebrow">Teacher quiz workspace</div><h1>{{ $quiz->title }}</h1><p>{{ $quiz->subject?->name }} · {{ $quiz->classGroup?->name }} · {{ number_format((float)$quiz->maximum_mark,2) }} marks · {{ $quiz->status->value }}</p></div>@if($quiz->status->value==='draft')<form method="POST" action="{{ route('quizzes.publish',$quiz->uuid) }}">@csrf<button>Publish quiz</button></form>@endif</header>
<section class="panel"><h2>Instructions</h2><p>{{ $quiz->instructions ?: 'No additional instructions.' }}</p></section>
<section style="margin-top:1rem"><h2>Questions</h2>
@forelse($quiz->questions as $question)<article class="panel" style="margin:.75rem 0"><strong>{{ $question->display_order }}. {{ str_replace('_',' ',$question->type->value) }} · {{ $question->marks_available }} marks</strong><p>{{ $question->prompt }}</p>
@if($question->options->isNotEmpty())<ol>@foreach($question->options as $option)<li>{{ $option->label }} @if($option->is_correct)<span class="badge">Correct</span>@endif</li>@endforeach</ol>@endif
@if($question->model_answer)<p><strong>Model answer:</strong> {{ $question->model_answer }}</p>@endif @if($question->marking_guidance)<p><strong>Rubric:</strong> {{ $question->marking_guidance }}</p>@endif</article>
@empty<p class="empty">No questions yet. Add the first question below.</p>@endforelse</section>
@if($quiz->status->value==='draft')
<section class="panel"><h2>Add question</h2><form class="academic-form" method="POST" action="{{ route('quizzes.questions.store',$quiz->uuid) }}">@csrf
<label>Type<select name="type" required><option value="multiple_choice">Multiple choice</option><option value="true_false">True or false</option><option value="short_response">Short written</option><option value="long_response">Long written</option></select></label>
<label>Marks<input type="number" name="marks_available" min=".01" step=".01" required></label>
<label class="wide">Prompt<textarea name="prompt" rows="3" required></textarea></label>
<label class="wide">Model answer<textarea name="model_answer" rows="2"></textarea></label>
<label class="wide">Rubric / marking guidance<textarea name="marking_guidance" rows="2"></textarea></label>
@foreach(range(0,3) as $index)<label>Option {{ $index+1 }}<input name="options[{{ $index }}][label]"></label><label><input type="hidden" name="options[{{ $index }}][is_correct]" value="0"><input type="checkbox" name="options[{{ $index }}][is_correct]" value="1"> Correct</label>@endforeach
<button type="submit">Add question</button></form><p class="muted">For written questions, leave options empty. Exactly one objective option must be correct.</p></section>
@endif
@if($quiz->attempts->isNotEmpty())<section><h2>Submissions</h2><div class="table-wrap"><table class="table"><thead><tr><th>Learner</th><th>Status</th><th>Score</th><th></th></tr></thead><tbody>@foreach($quiz->attempts as $attempt)<tr><td>{{ $attempt->learner->first_name }} {{ $attempt->learner->last_name }}</td><td>{{ str_replace('_',' ',$attempt->status) }}</td><td>{{ $attempt->final_score ?? '—' }}</td><td>@if($attempt->status!=='in_progress')<a href="{{ route('quizzes.review',$attempt->uuid) }}">Review</a>@endif</td></tr>@endforeach</tbody></table></div></section>@endif
@endsection
