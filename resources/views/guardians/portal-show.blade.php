@extends('learners.layout')
@section('title','My guardian profile')
@section('learner-content')
<div class="learner-heading"><div><div class="eyebrow">Guardian profile</div><h1>{{ $guardian->first_name }} {{ $guardian->last_name }}</h1></div></div>
<section class="panel"><h2>Contact preferences</h2><dl class="detail-list">@foreach([['Email',$guardian->email],['Phone',$guardian->phone],['Preferred channel',ucfirst($guardian->preferred_communication_channel)]] as [$label,$value])<div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>@endforeach</dl></section>
<section class="panel" style="margin-top:1rem"><h2>Linked learners</h2>@if($guardian->relationships->isEmpty())<p class="empty">No current learner relationship is available.</p>@else<ul>@foreach($guardian->relationships as $relationship)<li><a href="{{ route('learners.show',$relationship->learner->uuid) }}">{{ $relationship->learner->first_name }} {{ $relationship->learner->last_name }}</a> — {{ str_replace('_',' ',$relationship->relationship_type) }}</li>@endforeach</ul>@endif</section>
@endsection
