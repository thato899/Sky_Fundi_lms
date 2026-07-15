@extends('staff.layout')
@section('title', $staffProfile ? 'Edit staff' : 'Add staff')
@section('staff-content')
<h1>{{ $staffProfile ? 'Edit staff member' : 'Add staff member' }}</h1>
@if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $staffProfile ? route('staff.update',$staffProfile) : route('staff.store') }}" class="staff-form">@csrf @if($staffProfile)@method('PUT')@endif
@php($value = fn($name, $fallback = '') => old($name, $staffProfile?->getAttribute($name) ?? $fallback))
<div><label for="employee_number">Employee number</label><input id="employee_number" name="employee_number" required value="{{ $value('employee_number') }}"></div>
<div><label for="title">Title</label><input id="title" name="title" value="{{ $value('title') }}"></div>
<div><label for="first_name">First name</label><input id="first_name" name="first_name" required value="{{ $value('first_name') }}"></div>
<div><label for="last_name">Last name</label><input id="last_name" name="last_name" required value="{{ $value('last_name') }}"></div>
<div><label for="email">Email</label><input id="email" type="email" name="email" required value="{{ old('email',$staffProfile?->work_email) }}"></div>
<div><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone',$staffProfile?->work_phone) }}"></div>
<div><label for="staff_type">Staff type</label><select id="staff_type" name="staff_type" required>@foreach(['teacher','tutor','administrator','support','other'] as $option)<option value="{{ $option }}" @selected($value('staff_type','teacher')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></div>
<div><label for="department_id">Department</label><select id="department_id" name="department_id"><option value="">Not assigned</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected($value('department_id')===$department->id)>{{ $department->name }}</option>@endforeach</select></div>
<div><label for="employment_status">Employment status</label><select id="employment_status" name="employment_status">@foreach(['invited','active','suspended'] as $option)<option value="{{ $option }}" @selected($value('employment_status','invited')===$option)>{{ ucfirst($option) }}</option>@endforeach</select></div>
<div><label><input type="hidden" name="portal_access_enabled" value="0"><input type="checkbox" name="portal_access_enabled" value="1" @checked((bool)$value('portal_access_enabled',false))> Portal access enabled</label></div>
<div class="wide"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="5">{{ $value('notes') }}</textarea></div>
<div class="wide actions-inline"><button type="submit">{{ $staffProfile ? 'Save changes' : 'Create staff member' }}</button><a class="button secondary" href="{{ $staffProfile ? route('staff.show',$staffProfile) : route('staff.index') }}">Cancel</a></div>
</form>
@endsection
