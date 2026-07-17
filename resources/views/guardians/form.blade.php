@extends('learners.layout')
@section('title',$guardian ? 'Edit guardian' : 'Add guardian')
@section('learner-content')
<h1>{{ $guardian ? 'Edit guardian' : 'Add guardian' }}</h1><p>A guardian profile does not create login credentials. Identity access must use an existing organization invitation or membership.</p>
<form method="POST" action="{{ $guardian ? route('guardians.update',$guardian->uuid) : route('guardians.store') }}" class="learner-form">@csrf @if($guardian)@method('PUT')@endif
@php($value=fn($name,$fallback='')=>old($name,$guardian?->getAttribute($name)??$fallback))
@foreach([['first_name','First name','text'],['last_name','Last name','text'],['email','Email','email'],['phone','Phone','tel']] as [$name,$label,$type])<div><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value($name) }}" @required(in_array($name,['first_name','last_name']))>@error($name)<span class="field-error">{{ $message }}</span>@enderror</div>@endforeach
<div><label for="preferred_communication_channel">Preferred communication</label><select id="preferred_communication_channel" name="preferred_communication_channel">@foreach(['email','sms','phone','none'] as $channel)<option value="{{ $channel }}" @selected($value('preferred_communication_channel','email')===$channel)>{{ ucfirst($channel) }}</option>@endforeach</select></div>
<div><label for="status">Status</label><select id="status" name="status"><option value="active" @selected($value('status')?->value==='active'||$value('status','active')==='active')>Active</option><option value="inactive" @selected($value('status')?->value==='inactive'||$value('status')==='inactive')>Inactive</option></select></div>
<div class="wide"><label for="address">Address</label><textarea id="address" name="address">{{ $value('address') }}</textarea></div>
<div class="wide actions-inline"><button type="submit">Save guardian</button><a class="button secondary" href="{{ route('guardians.index') }}">Cancel</a></div></form>
@endsection
