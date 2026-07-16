@extends('reports.layout')
@section('title','Generate report cards')
@section('reports-content')
<h1>Generate report cards</h1><p>Choose one learner or leave it blank and scope the batch by grade/class. Each learner is generated atomically and partial failures are reported. Published or withdrawn versions are preserved.</p>
<form class="panel" method="POST" action="{{route('reports.generate')}}">@csrf
<div class="field"><label>Individual learner (optional)<select name="learner_id"><option value="">All eligible learners in scope</option>@foreach($learners as $l)<option value="{{$l->id}}">{{$l->learner_number}} · {{$l->first_name}} {{$l->last_name}}</option>@endforeach</select></label></div>
<div class="field"><label>Grade (optional)<select name="grade_id"><option value="">All grades</option>@foreach($grades as $g)<option value="{{$g->id}}">{{$g->name}}</option>@endforeach</select></label></div>
<div class="field"><label>Class (optional)<select name="class_id"><option value="">All classes</option>@foreach($classes as $c)<option value="{{$c->id}}">{{$c->name}}</option>@endforeach</select></label></div>
<div class="field"><label>Reporting period<select name="reporting_period_id" required>@foreach($periodOptions as $p)<option value="{{$p->id}}">{{$p->name}} ({{$p->status->value}})</option>@endforeach</select></label></div>
<div class="field"><label>Grading scale<select name="grading_scale_id" required>@foreach($scaleOptions as $s)<option value="{{$s->id}}">{{$s->name}}</option>@endforeach</select></label></div>
<div class="field"><label>Template<select name="report_card_template_id" required>@foreach($templateOptions as $t)<option value="{{$t->id}}">{{$t->name}}</option>@endforeach</select></label></div>
<button>Confirm and generate</button></form>
@endsection
