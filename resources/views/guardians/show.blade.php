@extends('learners.layout')
@section('title','Guardian profile')
@section('learner-content')
<div class="learner-heading"><div><div class="eyebrow">Guardian profile</div><h1>{{ $guardian->first_name }} {{ $guardian->last_name }}</h1><p>{{ ucfirst($guardian->status->value) }}</p></div><div class="actions-inline">@if(in_array('guardians.update',$permissions,true)&&$guardian->status->value!=='archived')<a class="button secondary" href="{{ route('guardians.edit',$guardian->uuid) }}">Edit</a>@endif</div></div>
<section class="panel"><h2>Contact and access</h2><dl class="detail-list">@foreach([['Email',$guardian->email],['Phone',$guardian->phone],['Preferred channel',ucfirst($guardian->preferred_communication_channel)],['Address',$guardian->address],['Portal identity',$guardian->user_id ? 'Linked through organization membership' : 'Profile only — no login access'],['Invitation state',$guardian->organizationMembership?->status?->value]] as [$label,$value])<div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Not provided' }}</dd></div>@endforeach</dl></section>
@if(in_array('guardians.view_invitations',$permissions,true)||in_array('guardians.invite',$permissions,true))
<section class="panel" style="margin-top:1rem"><h2>Portal invitation</h2>
@php($invitation=$guardian->organizationMembership)
@if($invitation)
<dl class="detail-list"><div><dt>Status</dt><dd>{{ ucfirst($invitation->status->value) }}</dd></div><div><dt>Sent</dt><dd>{{ $invitation->invitation_sent_at?->toDayDateTimeString() ?: 'Not sent' }}</dd></div><div><dt>Expires</dt><dd>{{ $invitation->invitation_expires_at?->toDayDateTimeString() ?: 'Not applicable' }}</dd></div><div><dt>Accepted</dt><dd>{{ $invitation->accepted_at?->toDayDateTimeString() ?: 'Not accepted' }}</dd></div></dl>
@if($invitation->status->value==='invited')<div class="actions-inline">
@if(in_array('guardians.invite',$permissions,true))<form method="POST" action="{{ route('guardians.invitations.resend',[$guardian->uuid,$invitation->id]) }}">@csrf<button type="submit">Resend invitation</button></form>@endif
@if(in_array('guardians.revoke_invitations',$permissions,true))<form method="POST" action="{{ route('guardians.invitations.revoke',[$guardian->uuid,$invitation->id]) }}">@csrf<button type="submit" class="danger">Revoke invitation</button></form>@endif
</div>@endif
@else
@if(in_array('guardians.invite',$permissions,true))<form class="learner-form" method="POST" action="{{ route('guardians.invitations.store',$guardian->uuid) }}">@csrf<label>Email<input type="email" name="email" required value="{{ old('email',$guardian->email) }}"></label><div><button type="submit">Send portal invitation</button></div></form>@else<p>No invitation has been sent.</p>@endif
@endif
</section>
@endif
<section class="panel" style="margin-top:1rem"><h2>Linked learners</h2>@if($guardian->relationships->isEmpty())<p class="empty">This guardian is not linked to a learner.</p>@else<ul>@foreach($guardian->relationships as $relationship)<li><a href="{{ route('learners.show',$relationship->learner->uuid) }}">{{ $relationship->learner->first_name }} {{ $relationship->learner->last_name }}</a> — {{ str_replace('_',' ',$relationship->relationship_type) }}@if($relationship->is_primary), primary contact @endif</li>@endforeach</ul>@endif</section>
@if(in_array('guardians.archive',$permissions,true)&&$guardian->status->value!=='archived')<section class="panel" style="margin-top:1rem"><h2>Archive</h2><form method="POST" action="{{ route('guardians.archive',$guardian->uuid) }}" onsubmit="return confirm('Archive this guardian and deactivate their learner links?')">@csrf<button class="danger">Archive guardian</button></form></section>@endif
<p><a href="{{ route('guardians.index') }}">← Guardian directory</a></p>
@endsection
