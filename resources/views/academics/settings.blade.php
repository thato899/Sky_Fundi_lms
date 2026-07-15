@extends('academics.layout')
@section('title','Academic settings')
@section('academic-content')
<h1>Academic settings</h1><section class="panel"><h2>Organization-safe availability</h2><p>The existing Academics settings backend stores values in the platform-global Core Settings group. It is not organization-owned, so this interface does not expose an editable form that could present shared values as tenant-specific settings.</p><p>Use the organization-owned academic year, term, curriculum, calendar and timetable-period workflows instead. A settings form can be enabled after the backend provides an explicit organization-owned contract.</p></section>
@endsection
