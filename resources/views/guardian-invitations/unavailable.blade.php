@extends('layouts.web')
@section('content')
<div class="wrap" style="max-width:680px;padding:3rem 0">
 <div class="eyebrow">Guardian portal invitation</div>
 <h1>This invitation is unavailable</h1>
 <p>The link may be invalid, expired, revoked, or already used. Ask the school administrator to check the invitation status or send a new link.</p>
 <p><a class="button" href="{{ route('login') }}">Sign in</a></p>
</div>
@endsection
