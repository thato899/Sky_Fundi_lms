@extends('assessments.layout')
@section('title','Result: '.$quizAttempt->assessment->title)
@section('assessment-content')
<div class="eyebrow">Released result</div><h1>{{ $quizAttempt->assessment->title }}</h1>
<div class="metric-grid"><div class="metric"><span>Score</span><strong>{{ $quizAttempt->final_score }}/{{ $quizAttempt->assessment->maximum_mark }}</strong></div><div class="metric"><span>Percentage</span><strong>{{ number_format((float)$quizAttempt->result->percentage,1) }}%</strong></div></div>
@foreach($quizAttempt->answers as $answer)<article class="panel" style="margin:1rem 0"><h2>{{ $answer->question->prompt }}</h2><p>{{ $answer->marks_awarded }}/{{ $answer->marks_available }} marks</p><p>{{ $answer->teacher_feedback ?: 'Marked objectively.' }}</p></article>@endforeach
@php($plan=$quizAttempt->publishedStudyPlan)
@if($plan)
<section class="panel" style="margin-top:1rem"><div class="eyebrow">Adaptive revision · version {{ $plan->version }}</div><h2>Your personalized study plan</h2>
<p>{{ $plan->content['summary'] }}</p>
<div class="metric-grid"><div class="metric"><span>Current mastery</span><strong>{{ count($plan->mastered_concepts ?? []) }}/{{ count($plan->content['weak_concepts']) }}</strong></div><div class="metric"><span>Progress</span><strong>{{ $plan->completion_percentage }}%</strong></div><div class="metric"><span>Study time</span><strong>{{ $plan->time_spent_minutes }} min</strong></div><div class="metric"><span>Next quiz readiness</span><strong>{{ empty($plan->remaining_concepts) ? 'Ready' : 'Building' }}</strong></div></div>
<progress max="100" value="{{ $plan->completion_percentage }}" style="width:100%">{{ $plan->completion_percentage }}%</progress>
<h3>Weak concepts</h3><ul>@foreach($plan->content['weak_concepts'] as $concept)<li>{{ $concept }}</li>@endforeach</ul>
<h3>Learning goals</h3><ul>@foreach($plan->content['learning_goals'] as $goal)<li>{{ $goal }}</li>@endforeach</ul>
<h3>Upcoming revision</h3><form method="POST" action="{{ route('quizzes.study-plan.progress',[$quizAttempt->uuid,$plan->uuid]) }}">@csrf
@foreach($plan->content['daily_schedule'] as $activity)<label style="display:block;margin:.6rem 0"><input type="checkbox" name="completed_activity_ids[]" value="{{ $activity['activity_id'] }}" @checked(in_array($activity['activity_id'],$plan->completed_activities ?? [],true))> Day {{ $activity['day'] }} · {{ $activity['duration_minutes'] }} min — {{ $activity['topic'] }}: {{ $activity['activity'] }}</label>@endforeach
<label>Time spent this session (minutes)<input type="number" name="time_spent_minutes" min="1" max="1440" value="30"></label><button>Update progress</button></form>
<h3>Targeted revision exercises</h3><form method="POST" action="{{ route('quizzes.study-plan.retest',[$quizAttempt->uuid,$plan->uuid]) }}">@csrf
@foreach($plan->content['revision_exercises'] as $exercise)<article style="margin:1rem 0"><span class="badge">{{ ucfirst($exercise['difficulty']) }}</span><p><strong>{{ $exercise['concept'] }}:</strong> {{ $exercise['question'] }}</p><textarea name="responses[{{ $exercise['activity_id'] }}]" required></textarea></article>@endforeach<button>Submit adaptive retest</button></form>
<h3>Reflection questions</h3><ul>@foreach($plan->content['reflection_questions'] as $question)<li>{{ $question }}</li>@endforeach</ul>
<h3>Recommended videos</h3><ul>@foreach($plan->content['recommended_videos'] as $resource)<li><strong>{{ $resource['title'] }}</strong> — search for “{{ $resource['search_topic'] }}”</li>@endforeach</ul>
<h3>Recommended reading</h3><ul>@foreach($plan->content['recommended_reading'] as $resource)<li><strong>{{ $resource['title'] }}</strong> — {{ $resource['description'] }}</li>@endforeach</ul>
<p><strong>Estimated duration:</strong> {{ $plan->content['estimated_duration_minutes'] }} minutes</p><p><strong>Next assessment:</strong> {{ $plan->content['next_assessment_recommendation'] }}</p>
@if($plan->revisionAttempts->isNotEmpty())@php($revision=$plan->revisionAttempts->sortByDesc('attempt_number')->first())<p><strong>Latest retest:</strong> {{ $revision->score_percentage }}% · {{ $revision->evaluation['feedback'] }}</p>@endif
</section>
@endif
@endsection
