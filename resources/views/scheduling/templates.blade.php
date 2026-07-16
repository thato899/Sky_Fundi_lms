@extends('scheduling.layout')
@section('title','Timetable templates')
@section('scheduling-content')<h1>Timetable templates</h1><div class="stack">@forelse($templates as $template)<article class="panel"><strong>{{ $template->name }}</strong><p>{{ $template->status->value }} · {{ $template->effective_start_date->toDateString() }} – {{ $template->effective_end_date->toDateString() }} · {{ $template->entries_count }} entries</p></article>@empty<p>No templates configured.</p>@endforelse</div>{{ $templates->links() }}@endsection
