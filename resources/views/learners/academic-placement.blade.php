@extends('learners.layout')
@section('title','Update academic placement')
@section('learner-content')
<h1>Update academic placement</h1><p>Set the current placement for {{ $learner->first_name }} {{ $learner->last_name }}. This does not create historical enrolment.</p>
<form method="POST" action="{{ route('learners.academic-placement.update',$learner->uuid) }}" class="learner-form">@csrf @method('PUT')
<fieldset><legend>Current placement</legend>@foreach([['current_academic_year_id','Academic year',$academicYears],['curriculum_id','Curriculum',$curricula],['current_grade_id','Grade',$grades],['current_class_id','Class',$classes]] as [$name,$label,$options])<div><label for="{{ $name }}">{{ $label }}</label><select id="{{ $name }}" name="{{ $name }}"><option value="">Not assigned</option>@foreach($options as $option)<option value="{{ $option->id }}" @selected(old($name,$learner->getAttribute($name))===$option->id)>{{ $option->name }}</option>@endforeach</select>@error($name)<span class="field-error">{{ $message }}</span>@enderror</div>@endforeach</fieldset>
<div class="wide actions-inline"><button type="submit">Update placement</button><a class="button secondary" href="{{ route('learners.show',$learner->uuid) }}">Cancel</a></div></form>
@endsection
