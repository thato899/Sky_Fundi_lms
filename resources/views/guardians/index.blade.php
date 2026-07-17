@extends('learners.layout')
@section('title','Guardian management')
@section('learner-content')
<div class="learner-heading"><div><div class="eyebrow">People</div><h1>Guardian management</h1><p>Manage guardian profiles independently from portal identities.</p></div>@if(in_array('guardians.create',$permissions,true))<a class="button" href="{{ route('guardians.create') }}">Add guardian</a>@endif</div>
<form method="GET" class="filters"><div class="wide"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Name or email"></div><div><button type="submit">Search</button></div></form>
@if($guardians->isEmpty())<div class="empty">No guardian profiles match this view.</div>@else
<div class="table-wrap"><table class="learner-table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Channel</th><th>Status</th>@if(in_array('guardians.update',$permissions,true)||in_array('guardians.manage_relationships',$permissions,true))<th>Portal identity</th>@endif<th></th></tr></thead><tbody>@foreach($guardians as $guardian)<tr><td>{{ $guardian->first_name }} {{ $guardian->last_name }}</td><td>{{ $guardian->email ?: '—' }}</td><td>{{ $guardian->phone ?: '—' }}</td><td>{{ ucfirst($guardian->preferred_communication_channel) }}</td><td>{{ ucfirst($guardian->status->value) }}</td>@if(in_array('guardians.update',$permissions,true)||in_array('guardians.manage_relationships',$permissions,true))<td>{{ $guardian->user_id ? 'Linked' : 'Profile only' }}</td>@endif<td><a href="{{ route('guardians.show',$guardian->uuid) }}">View</a></td></tr>@endforeach</tbody></table></div>{{ $guardians->links() }}@endif
<p><a href="{{ route('learners.index') }}">← Learner management</a></p>
@endsection
