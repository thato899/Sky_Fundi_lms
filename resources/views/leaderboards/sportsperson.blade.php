@extends('leaderboards.layout')
@section('title', 'Sportsperson of the Week')
@section('leaderboard-content')
<h1>Sportsperson of the Week</h1><p>A weekly spotlight the principal posts and publishes to the whole school.</p>

@if($canManage)
<section class="panel"><h2>Post this week's Sportsperson</h2>
<form class="lb-form" method="POST" action="{{ route('leaderboards.sportsperson.store') }}">@csrf
<label>Learner<select name="learner_profile_id" required><option value="">Choose a learner</option>@foreach($learners as $learner)<option value="{{ $learner->id }}">{{ $learner->first_name }} {{ $learner->last_name }}</option>@endforeach</select></label>
<label>Week starting<input type="date" name="week_start_date" required></label>
<label class="wide">Citation<textarea name="citation" rows="3" required maxlength="2000" placeholder="e.g. Outstanding effort and sportsmanship at the inter-house athletics meet."></textarea></label>
<div class="wide"><button type="submit">Post (stays private until you publish it)</button></div>
</form></section>
@endif

<section style="margin-top:1.5rem"><h2>{{ $canManage ? 'History' : 'Announcements' }}</h2>
@if($posts->isEmpty())<p class="empty">No Sportsperson of the Week has been {{ $canManage ? 'posted' : 'announced' }} yet.</p>@else
<div class="stack">
@foreach($posts as $post)
<article class="panel"><div class="lb-nav"><strong>{{ $post->learner->first_name }} {{ $post->learner->last_name }}</strong><span class="chip {{ $post->is_published ? 'success' : 'neutral' }}">{{ $post->is_published ? 'Announced' : 'Draft' }}</span></div><p class="meta">Week of {{ $post->week_start_date->toDateString() }}</p><p>{{ $post->citation }}</p>
@if($canManage && ! $post->is_published)<form method="POST" action="{{ route('leaderboards.sportsperson.publish', $post->uuid) }}" onsubmit="return confirm('Announce this Sportsperson of the Week to the whole school?')">@csrf<button class="small">Announce</button></form>@endif
</article>
@endforeach
</div>
@endif
</section>
@endsection
