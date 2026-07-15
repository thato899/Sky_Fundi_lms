@extends('staff.layout')
@section('title', 'Staff management')
@section('staff-content')
<h1>Staff management</h1><p>Search and manage staff in the active organization.</p>
<form method="GET" action="{{ route('staff.index') }}" class="filters">
    <input name="search" value="{{ request('search') }}" placeholder="Search staff" aria-label="Search staff">
    <select name="department_id" aria-label="Department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id') === $department->id)>{{ $department->name }}</option>@endforeach</select>
    <select name="employment_status" aria-label="Employment status"><option value="">All statuses</option>@foreach(['invited','active','suspended'] as $value)<option value="{{ $value }}" @selected(request('employment_status') === $value)>{{ ucfirst($value) }}</option>@endforeach</select>
    <select name="staff_type" aria-label="Staff type"><option value="">All staff types</option>@foreach(['teacher','tutor','administrator','support','other'] as $value)<option value="{{ $value }}" @selected(request('staff_type') === $value)>{{ ucfirst($value) }}</option>@endforeach</select>
    <select name="portal_access_enabled" aria-label="Portal access"><option value="">Any portal status</option><option value="1" @selected(request('portal_access_enabled') === '1')>Enabled</option><option value="0" @selected(request('portal_access_enabled') === '0')>Disabled</option></select>
    <select name="sort" aria-label="Sort"><option value="created_at">Created date</option>@foreach(['employee_number','first_name','last_name'] as $value)<option value="{{ $value }}" @selected(request('sort') === $value)>{{ ucfirst(str_replace('_',' ',$value)) }}</option>@endforeach</select>
    <select name="direction" aria-label="Direction"><option value="desc">Descending</option><option value="asc" @selected(request('direction') === 'asc')>Ascending</option></select>
    <button type="submit">Apply</button><a class="button secondary" href="{{ route('staff.index') }}">Clear</a>
</form>
@if($staff->isEmpty())<div class="empty">No staff match the current search and filters.</div>@else<div class="table-wrap"><table class="staff-table"><thead><tr><th>Employee number</th><th>Full name</th><th>Email</th><th>Staff type</th><th>Department</th><th>Employment status</th><th>Portal access</th><th>Created date</th><th>Actions</th></tr></thead><tbody>
@foreach($staff as $profile)<tr><td>{{ $profile->employee_number }}</td><td>{{ trim(($profile->title ? $profile->title.' ' : '').$profile->first_name.' '.$profile->last_name) }}</td><td>{{ $profile->work_email }}</td><td>{{ ucfirst($profile->staff_type) }}</td><td>{{ $profile->department?->name ?? 'Not assigned' }}</td><td><span class="badge">{{ $profile->employment_status }}</span></td><td>{{ $profile->portal_access_enabled ? 'Enabled' : 'Disabled' }}</td><td>{{ $profile->created_at->toDateString() }}</td><td><div class="actions-inline"><a href="{{ route('staff.show',$profile) }}">View</a>@if(in_array('staff.update',$permissions,true))<a href="{{ route('staff.edit',$profile) }}">Edit</a>@endif</div></td></tr>@endforeach
</tbody></table></div><div class="pagination">@if($staff->previousPageUrl())<a href="{{ $staff->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $staff->currentPage() }} of {{ $staff->lastPage() }}</span>@if($staff->nextPageUrl())<a href="{{ $staff->nextPageUrl() }}">Next</a>@endif</div>@endif
@endsection
