@extends('staff.layout')
@section('title', 'Teaching assignments')
@section('staff-content')
<div class="staff-nav"><div><div class="eyebrow">Organization-wide</div><h1>Teaching assignments</h1><p class="meta">Every teaching assignment across the organization, with a bulk-entry grid for onboarding a term's coverage at once.</p></div><div class="actions-inline"><a class="button secondary" href="{{ route('staff.index') }}">Back to staff</a></div></div>
@if(session('status'))<p class="status-message">{{ session('status') }}</p>@endif
@if($errors->has('assignments'))<p class="status-message" role="alert">{{ $errors->first('assignments') }}</p>@endif
@if(!empty($bulkFailures))<section class="panel"><h2>Rows that failed</h2><ul>@foreach($bulkFailures as $failure)<li>Row {{ $failure['index'] + 1 }}: {{ $failure['message'] }}</li>@endforeach</ul></section>@endif
<form method="GET" action="{{ route('teaching-assignments.index') }}" class="filters">
    <select name="staff_profile_id" aria-label="Staff member"><option value="">All staff</option>@foreach($staffMembers as $staff)<option value="{{ $staff->id }}" @selected(request('staff_profile_id')===$staff->id)>{{ $staff->first_name }} {{ $staff->last_name }}</option>@endforeach</select>
    <select name="class_id" aria-label="Class"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(request('class_id')===$class->id)>{{ $class->name }}</option>@endforeach</select>
    <select name="subject_id" aria-label="Subject"><option value="">All subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(request('subject_id')===$subject->id)>{{ $subject->name }}</option>@endforeach</select>
    <select name="academic_year_id" aria-label="Academic year"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(request('academic_year_id')===$year->id)>{{ $year->name }}</option>@endforeach</select>
    <label><input type="checkbox" name="active_only" value="1" @checked(request('active_only'))> Active only</label>
    <button type="submit">Apply</button><a class="button secondary" href="{{ route('teaching-assignments.index') }}">Clear</a>
</form>
@if($assignments->isEmpty())<div class="empty">No teaching assignments match the current filters.</div>@else<div class="table-wrap"><table class="staff-table"><thead><tr><th>Staff</th><th>Class</th><th>Subject</th><th>Academic year</th><th>Period</th><th>Status</th></tr></thead><tbody>@foreach($assignments as $assignment)<tr><td>@if($assignment->staffProfile)<a href="{{ route('staff.show', $assignment->staffProfile) }}">{{ $assignment->staffProfile->first_name }} {{ $assignment->staffProfile->last_name }}</a>@else Unavailable @endif</td><td>{{ $assignment->classGroup?->name ?? 'Unavailable' }}</td><td>{{ $assignment->subject?->name ?? 'All subjects' }}</td><td>{{ $assignment->academicYear?->name ?? 'Unavailable' }}</td><td>{{ $assignment->started_on?->toDateString() }}@if($assignment->ended_on) – {{ $assignment->ended_on->toDateString() }}@endif</td><td>{{ $assignment->ended_on ? 'Ended' : 'Active' }}</td></tr>@endforeach</tbody></table></div><div class="pagination">{{ $assignments->links() }}</div>@endif
@if(in_array('teaching_assignments.manage', $permissions, true))
<section class="panel" style="margin-top:1rem"><h2>Bulk-assign teaching coverage</h2><p class="meta">Fill in as many rows as you need (leave the rest blank). Each row assigns one staff member to one class; leave subject blank to cover every subject taught by the class. Rows are attempted independently — a mistake on one row will not block the others.</p>
<form method="POST" action="{{ route('teaching-assignments.bulk') }}" class="table-wrap"><table class="staff-table"><thead><tr><th>Staff</th><th>Class</th><th>Subject</th><th>Academic year</th><th>Start date</th></tr></thead><tbody>
@csrf
@for($i = 0; $i < $bulkRows; $i++)
<tr>
<td><select name="assignments[{{ $i }}][staff_profile_id]"><option value="">—</option>@foreach($staffMembers as $staff)<option value="{{ $staff->id }}">{{ $staff->first_name }} {{ $staff->last_name }}</option>@endforeach</select></td>
<td><select name="assignments[{{ $i }}][class_id]"><option value="">—</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></td>
<td><select name="assignments[{{ $i }}][subject_id]"><option value="">All subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></td>
<td><select name="assignments[{{ $i }}][academic_year_id]"><option value="">—</option>@foreach($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select></td>
<td><input type="date" name="assignments[{{ $i }}][started_on]" value="{{ now()->toDateString() }}"></td>
</tr>
@endfor
</tbody></table><div class="wide" style="margin-top:1rem"><button type="submit">Create teaching assignments</button></div></form>
</section>
@endif
@endsection
