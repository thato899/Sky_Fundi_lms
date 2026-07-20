@extends('layouts.web')
@section('title','My report cards')
@section('content')
<style>
.myreports{padding:2rem 0 4rem;max-width:820px}
.rc-card{margin:1rem 0;padding:1.4rem 1.6rem}
.rc-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
.rc-head h3{font-family:var(--display);font-size:1.25rem;margin:0}
.rc-meta{display:flex;gap:.5rem;flex-wrap:wrap;margin:.5rem 0 .9rem}
.rc-table{width:100%;border-collapse:collapse;font-size:.95rem}
.rc-table th,.rc-table td{padding:.55rem .4rem;border-bottom:1px solid var(--line);text-align:left}
.rc-table th{color:var(--muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.05em}
.rc-table td:last-child,.rc-table th:last-child{text-align:right}
.rc-comment{border:1px solid var(--line);border-left:4px solid var(--secondary);border-radius:.7rem;background:color-mix(in srgb,var(--secondary) 5%,#fff);padding:.9rem 1rem;margin-top:.9rem}
</style>
<div class="wrap myreports"><div class="eyebrow">My report cards</div><h1 style="font-size:clamp(1.7rem,3.5vw,2.4rem)">{{ $learner->first_name }} {{ $learner->last_name }}</h1>
<p>Published report cards appear here as soon as your school releases them.</p>
@if($cards->isEmpty())<section class="panel rc-card"><p class="meta">No published report cards yet. Your teacher will publish them at the end of each reporting period.</p></section>
@else @foreach($cards as $card)
<section class="panel rc-card"><div class="rc-head"><h3>{{ $card->period?->name }}</h3>@if($card->overall_average !== null)<span class="chip {{ (float)$card->overall_average >= 50 ? 'success' : 'warn' }}">Overall {{ number_format((float)$card->overall_average,1) }}%</span>@endif</div>
<div class="rc-meta"><span class="chip neutral">{{ $card->academicYear?->name }}</span>@if($card->academicTerm)<span class="chip neutral">{{ $card->academicTerm->name }}</span>@endif<span class="chip neutral">{{ $card->grade?->name }} · {{ $card->classGroup?->name }}</span><span class="chip neutral">Published {{ $card->published_at?->format('j M Y') }}</span></div>
<table class="rc-table"><thead><tr><th>Subject</th><th>Result</th><th>Grade</th></tr></thead><tbody>
@foreach($card->subjects->sortBy('display_order') as $subject)<tr><td>{{ $subject->subject_name_snapshot }}</td><td>{{ $subject->calculated_percentage !== null ? number_format((float)$subject->calculated_percentage,1).'%' : ucwords(str_replace('_',' ',$subject->subject_result_status->value ?? (string) $subject->subject_result_status)) }}</td><td>{{ $subject->grading_band_symbol }} {{ $subject->grading_band_label }}</td></tr>@endforeach
</tbody></table>
<p class="meta" style="margin:.7rem 0 0">Attendance: {{ $card->present_count }} present · {{ $card->absent_count }} absent · {{ $card->late_count }} late across {{ $card->attendance_session_count }} sessions.</p>
@if($card->overall_comment)<div class="rc-comment"><strong>Teacher's overall comment</strong><p style="margin:.3rem 0 0">{{ $card->overall_comment }}</p></div>@endif
</section>
@endforeach @endif
</div>
@endsection
