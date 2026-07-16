@extends('scheduling.layout')
@section('title','Rooms')
@section('scheduling-content')<h1>Rooms and lesson locations</h1><div class="stack">@forelse($rooms as $room)<article class="panel"><strong>{{ $room->name }}</strong><p>{{ $room->location_type }} · {{ $room->is_active ? 'active' : 'inactive' }}@if($room->capacity) · capacity {{ $room->capacity }}@endif</p></article>@empty<p>No rooms configured.</p>@endforelse</div>{{ $rooms->links() }}@endsection
