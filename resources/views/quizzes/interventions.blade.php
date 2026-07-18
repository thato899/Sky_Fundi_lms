@extends('assessments.layout')
@section('title','Teacher intervention dashboard')
@section('assessment-content')
<div class="eyebrow">Teacher operations</div>
<h1>Intervention dashboard</h1>
<p class="muted">A deterministic view of released results, study activity, revision progress, and teacher adjustments. AI is used only for optional recommendations.</p>

@php($overview=$dashboard['overview'])
<div class="metric-grid">
    @foreach([
        'Learners'=>$overview['learners'],
        'Average mark'=>$overview['average_class_mark'].'%',
        'Average completion'=>$overview['average_completion'].'%',
        'Study streak average'=>$overview['study_streak_average'].' days',
        'Revision completion'=>$overview['revision_completion'].'%',
        'At risk'=>$overview['learners_at_risk'],
        'Ready for reassessment'=>$overview['ready_for_reassessment'],
    ] as $label=>$value)
        <div class="metric"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
    @endforeach
</div>

<section class="panel" style="margin-top:1rem">
    <h2>Intervention queue</h2>
    @forelse($dashboard['intervention_queue'] as $row)
        <article style="border-top:1px solid #dde3ea;padding:.8rem 0">
            <strong>{{ $row['learner'] }}</strong>
            <span class="badge">{{ strtoupper($row['risk_level']) }}</span>
            <p>{{ $row['subject'] }} · {{ $row['weak_concept'] }} · {{ $row['estimated_intervention_minutes'] }} minutes</p>
            <p>{{ $row['recommended_action'] }}</p>
            <small class="muted">Last activity: {{ $row['last_activity_at'] ? \Illuminate\Support\Carbon::parse($row['last_activity_at'])->diffForHumans() : 'Not started' }}</small>
        </article>
    @empty
        <p class="empty">No orange or red risk learners in your released assessments.</p>
    @endforelse
</section>

<section class="panel" style="margin-top:1rem">
    <h2>Weak concept analysis</h2>
    @forelse($dashboard['weak_concepts'] as $concept)
        <p><strong>{{ $concept['concept'] }}</strong> · {{ $concept['affected_learners'] }} affected · {{ $concept['average_score'] }}% average · confidence {{ $concept['average_confidence'] }}<br>
        <span class="muted">{{ $concept['recommended_intervention'] }}</span></p>
    @empty
        <p class="empty">No weak concepts found.</p>
    @endforelse
</section>

<section class="panel" style="margin-top:1rem">
    <h2>Mastery tracking</h2>
    @forelse($dashboard['mastery'] as $row)
        <p><strong>{{ $row['learner'] }}</strong> · {{ $row['concept'] }}<br>
        <progress value="{{ $row['mastery_percentage'] }}" max="100" style="width:20rem;max-width:100%"></progress> {{ $row['mastery_percentage'] }}%</p>
    @empty
        <p class="empty">Mastery data appears after a published study plan.</p>
    @endforelse
</section>

<section class="panel" style="margin-top:1rem">
    <h2>Cohort trends</h2>
    @forelse($dashboard['trends'] as $trend)
        <p><strong>{{ $trend['date'] }}</strong> · score {{ $trend['average_score'] }}% · study {{ $trend['study_completion'] }}% · AI marks {{ $trend['ai_usage'] }} · overrides {{ $trend['teacher_overrides'] }} · retest passes {{ $trend['intervention_success'] }} · time-to-mastery {{ $trend['time_to_mastery_minutes'] }} min</p>
    @empty
        <p class="empty">No trend data available.</p>
    @endforelse
</section>
@endsection
