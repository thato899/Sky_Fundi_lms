@extends('assessments.layout')
@section('title',$quizAttempt->assessment->title)
@section('assessment-content')
<div class="eyebrow">Quiz attempt</div><h1>{{ $quizAttempt->assessment->title }}</h1><p>{{ $quizAttempt->assessment->instructions }}</p>
@if($quizAttempt->status==='in_progress')<form method="POST" action="{{ route('quizzes.submit',$quizAttempt->uuid) }}">@csrf
@foreach($quizAttempt->answers as $answer)<section class="panel" style="margin:1rem 0"><h2>{{ $answer->question->display_order }}. {{ $answer->question->prompt }}</h2><p>{{ $answer->marks_available }} marks</p>
@if($answer->question->type->isObjective())@foreach($answer->question->options as $option)<label style="display:block;margin:.5rem"><input type="radio" name="answers[{{ $answer->question->uuid }}][selected_option_uuid]" value="{{ $option->uuid }}"> {{ $option->label }}</label>@endforeach
@else<textarea style="width:100%;min-height:130px" name="answers[{{ $answer->question->uuid }}][answer_text]"></textarea>@endif</section>@endforeach
<button type="submit">Submit final answers</button></form>@else<p class="empty">This attempt was submitted and can no longer be edited. Results appear after teacher release.</p>@endif
@endsection
