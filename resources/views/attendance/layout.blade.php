@extends('layouts.web')
@section('content')
<style>
.attendance{padding:2rem 0 4rem}.bar,.actions{display:flex;justify-content:space-between;align-items:center;gap:.8rem;flex-wrap:wrap}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}.card{background:var(--card);border:1px solid var(--line);border-radius:.8rem;padding:1rem}.filters,.form{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;margin:1rem 0}.filters input,.filters select,.form input,.form select,.form textarea,.register select,.register input{width:100%;padding:.65rem;border:1px solid var(--line);border-radius:.5rem;background:var(--card);color:var(--ink)}.wide{grid-column:1/-1}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:.7rem;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.table-wrap{overflow:auto}.badge{padding:.2rem .5rem;border-radius:999px;background:#edf3fb}.notice{padding:.8rem;background:#e7f8f3;color:#08765f;border-radius:.5rem}.pagination{margin-top:1rem}.pagination a,.pagination span{padding:.35rem}.muted{color:var(--muted)}@media(max-width:800px){.grid,.filters,.form{grid-template-columns:1fr 1fr}}@media(max-width:520px){.grid,.filters,.form{grid-template-columns:1fr}}
</style>
<div class="wrap attendance"><nav class="bar"><div><div class="eyebrow">{{ $organization->name }}</div><a href="{{ route('dashboard') }}">Dashboard</a> / <a href="{{ route('attendance.index') }}">Attendance</a></div><div class="actions">@if(in_array('attendance.create',$permissions,true))<a class="button" href="{{ route('attendance.create') }}">Create session</a>@endif</div></nav>
@if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
@if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('attendance-content')</div>
@endsection
