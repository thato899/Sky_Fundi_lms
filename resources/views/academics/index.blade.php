@extends('academics.layout')
@section('title','Academic management')
@section('academic-content')
<h1>Academic management</h1><p>Manage the active organization's academic structure and calendar.</p><div class="academic-grid">@foreach($counts as $label=>$count)<article class="feature"><h2>{{ ucfirst($label) }}</h2><strong style="font-size:2rem">{{ $count }}</strong>@php($route=['academic years'=>'years.index','terms'=>'years.index','calendar entries'=>'years.index'][$label]??str_replace(' ','-',$label).'.index')@if(Route::has("academics.web.$route"))<p><a href="{{ route("academics.web.$route") }}">Manage {{ $label }}</a></p>@endif</article>@endforeach</div><section class="panel" style="margin-top:1rem"><h2>Academic settings</h2><p>Review the current platform-global settings limitation.</p><a href="{{ route('academics.web.settings') }}">View settings information</a></section>
@endsection
