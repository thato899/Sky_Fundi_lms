<?php

declare(strict_types=1);

namespace Modules\Learners\Http\Controllers\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Attendance\Infrastructure\Models\AttendanceEntry;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Scheduling\Infrastructure\Models\ScheduledLesson;

final class LearnerPortalController
{
    public function attendance(Request $request): View
    {
        [$organization, $learner] = $this->learner($request);
        $entries = AttendanceEntry::query()->where('organization_id', $organization->getKey())
            ->where('learner_profile_id', $learner->getKey())->whereHas('session', fn ($query) => $query->where('status', 'finalized'))
            ->with(['session.classGroup', 'session.subject'])->latest()->paginate(20);
        $totals = AttendanceEntry::query()->where('organization_id', $organization->getKey())
            ->where('learner_profile_id', $learner->getKey())->whereHas('session', fn ($query) => $query->where('status', 'finalized'))
            ->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('learner-portal.attendance', compact('learner', 'entries', 'totals'));
    }

    public function timetable(Request $request): View
    {
        [$organization, $learner] = $this->learner($request);
        $lessons = $learner->getAttribute('current_class_id') === null ? collect() : ScheduledLesson::query()
            ->where('organization_id', $organization->getKey())->where('class_id', $learner->getAttribute('current_class_id'))
            ->where('status', 'scheduled')->whereBetween('lesson_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->with(['subject', 'room'])->orderBy('lesson_date')->orderBy('starts_at')->get();

        return view('learner-portal.timetable', compact('learner', 'lessons'));
    }

    /** @return array{Organization, LearnerProfile} */
    private function learner(Request $request): array
    {
        $organization = $request->attributes->get('organization');
        abort_unless($organization instanceof Organization && $request->user() !== null, 403);
        $learner = LearnerProfile::query()->where('organization_id', $organization->getKey())
            ->where('user_id', $request->user()->getAuthIdentifier())->where('portal_access_enabled', true)->firstOrFail();

        return [$organization, $learner];
    }
}
