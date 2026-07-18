@extends('assessments.layout')
@section('title','Adaptive learning analytics')
@section('assessment-content')
<div class="eyebrow">Teacher dashboard</div><h1>Adaptive learning analytics</h1>
<div class="metric-grid"><div class="metric"><span>Average completion</span><strong>{{ $analytics['average_completion'] }}%</strong></div><div class="metric"><span>Students needing intervention</span><strong>{{ $analytics['students_needing_intervention']->count() }}</strong></div></div>
<section class="panel"><h2>Most missed concepts</h2>@forelse($analytics['most_missed_concepts'] as $concept=>$count)<p><strong>{{ $concept }}</strong> · {{ $count }} learner plan(s)</p>@empty<p class="empty">No published plan data yet.</p>@endforelse</section>
<section class="panel" style="margin-top:1rem"><h2>Class weaknesses</h2>@forelse($analytics['class_weaknesses'] as $class=>$concepts)<h3>{{ $class }}</h3><ul>@foreach($concepts as $concept=>$count)<li>{{ $concept }} · {{ $count }}</li>@endforeach</ul>@empty<p class="empty">No class weakness data yet.</p>@endforelse</section>
<section class="panel" style="margin-top:1rem"><h2>Students needing intervention</h2>@forelse($analytics['students_needing_intervention'] as $plan)<p><strong>{{ $plan->attempt->learner->first_name }} {{ $plan->attempt->learner->last_name }}</strong> · {{ $plan->completion_percentage }}% complete · {{ implode(', ',$plan->remaining_concepts ?? []) }}<br><span class="muted">Suggestion: schedule a short teacher check-in and work through the next medium exercise together.</span></p>@empty<p class="empty">No learner currently meets the intervention threshold.</p>@endforelse</section>
@endsection
