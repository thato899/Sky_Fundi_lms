@extends('layouts.web')
@section('content')
<div class="wrap" style="max-width:680px;padding:3rem 0">
 <div class="eyebrow">Guardian portal invitation</div>
 <h1>{{ $organization->name }} invited you</h1>
 <p>Accept this invitation to access the school’s restricted guardian portal. Learner details appear only after acceptance and only when the school has explicitly linked them to your guardian profile.</p>
 <p><strong>Expires:</strong> {{ $membership->invitation_expires_at->toDayDateTimeString() }}</p>
 @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <form method="POST" action="{{ route('guardian-invitations.accept',$token) }}" style="display:grid;gap:1rem">
  @csrf
  @guest<label>Your name <input name="name" value="{{ old('name') }}" autocomplete="name"></label>@endguest
  <label>Password <input type="password" name="password" required autocomplete="{{ auth()->check() ? 'current-password' : 'new-password' }}"></label>
  @guest<label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password"></label>@endguest
  <button class="button" type="submit">Accept invitation</button>
 </form>
 <p class="help">If an account already exists for the invited email, these credentials sign in to that account. This page does not disclose whether an account exists.</p>
</div>
@endsection
