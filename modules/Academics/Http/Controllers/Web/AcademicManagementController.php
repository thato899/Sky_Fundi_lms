<?php

declare(strict_types=1);

namespace Modules\Academics\Http\Controllers\Web;

use Carbon\CarbonInterface;
use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Academics\Application\AcademicTermService;
use Modules\Academics\Application\AcademicYearService;
use Modules\Academics\Application\CalendarService;
use Modules\Academics\Application\ClassService;
use Modules\Academics\Application\CurriculumService;
use Modules\Academics\Application\DepartmentService;
use Modules\Academics\Application\GradeService;
use Modules\Academics\Application\SubjectService;
use Modules\Academics\Application\TimetableService;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\CalendarEntry;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Curriculum;
use Modules\Academics\Infrastructure\Models\Department;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Academics\Infrastructure\Models\TimetablePeriod;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Application\OrganizationService;
use Modules\Organizations\Infrastructure\Models\Organization;

final class AcademicManagementController
{
    private const AREAS = [
        'curricula' => [Curriculum::class, 'academics.curriculum.view', 'academics.curriculum.manage'],
        'grades' => [Grade::class, 'academics.grades.view', 'academics.grades.manage'],
        'classes' => [ClassGroup::class, 'academics.classes.view', 'academics.classes.manage'],
        'departments' => [Department::class, 'academics.departments.view', 'academics.departments.manage'],
        'subjects' => [Subject::class, 'academics.subjects.view', 'academics.subjects.manage'],
        'timetable-periods' => [TimetablePeriod::class, 'academics.timetable.view', 'academics.timetable.manage'],
    ];

    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly OrganizationService $organizations,
        private readonly AcademicYearService $yearService,
        private readonly AcademicTermService $termService,
        private readonly CurriculumService $curriculumService,
        private readonly GradeService $gradeService,
        private readonly ClassService $classService,
        private readonly DepartmentService $departmentService,
        private readonly SubjectService $subjectService,
        private readonly TimetableService $timetableService,
        private readonly CalendarService $calendarService,
    ) {}

    public function index(Request $request): View
    {
        [$organization, $membership] = $this->context($request, 'academics.academic-years.view');
        $id = $organization->getKey();
        $models = [AcademicYear::class, AcademicTerm::class, Curriculum::class, Grade::class, ClassGroup::class, Department::class, Subject::class, TimetablePeriod::class, CalendarEntry::class];
        $counts = [];
        foreach ($models as $model) {
            $counts[] = $model::query()->withoutGlobalScopes()->where('organization_id', $id)->count();
        }

        return view('academics.index', $this->shared($organization, $membership) + ['counts' => array_combine(['academic years', 'terms', 'curricula', 'grades', 'classes', 'departments', 'subjects', 'timetable periods', 'calendar entries'], $counts)]);
    }

    public function years(Request $request): View
    {
        return $this->yearPage($request, 'index');
    }

    public function yearCreate(Request $request): View
    {
        return $this->yearPage($request, 'form');
    }

    public function yearEdit(Request $request, string $year): View
    {
        return $this->yearPage($request, 'form', $year);
    }

    public function yearShow(Request $request, string $year): View
    {
        return $this->yearPage($request, 'show', $year);
    }

    private function yearPage(Request $request, string $page, ?string $yearId = null): View
    {
        [$organization, $membership] = $this->context($request, 'academics.academic-years.view');
        $year = $yearId ? $this->resolve(AcademicYear::class, $organization, $yearId) : null;
        $data = $this->shared($organization, $membership) + ['year' => $year];
        if ($page === 'index') {
            $data['years'] = AcademicYear::query()->where('organization_id', $organization->getKey())->withCount(['terms', 'grades', 'classes'])->orderByDesc('start_date')->paginate(15)->withQueryString();
        } elseif ($page === 'show') {
            $year?->loadCount(['terms', 'grades', 'classes', 'calendarEntries']);
        }

        return view("academics.years.{$page}", $data);
    }

    public function yearStore(Request $request): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.academic-years.manage');
        $data = $request->validate($this->yearRules() + ['organization_id' => ['prohibited']]);
        $year = $this->yearService->create($data + ['organization_id' => $org->getKey()]);

        return redirect()->route('academics.web.years.show', $year)->with('status', 'Academic year created.');
    }

    public function yearUpdate(Request $request, string $year): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.academic-years.manage');
        $record = $this->resolve(AcademicYear::class, $org, $year);
        $this->yearService->update($record, $request->validate($this->yearRules()));

        return redirect()->route('academics.web.years.show', $record)->with('status', 'Academic year updated.');
    }

    public function yearCurrent(Request $r, string $year): RedirectResponse
    {
        return $this->yearAction($r, $year, 'setCurrent', 'Academic year set as current.');
    }

    public function yearClose(Request $r, string $year): RedirectResponse
    {
        return $this->yearAction($r, $year, 'close', 'Academic year closed.');
    }

    public function yearArchive(Request $r, string $year): RedirectResponse
    {
        return $this->yearAction($r, $year, 'archive', 'Academic year archived.');
    }

    private function yearAction(Request $request, string $id, string $method, string $message): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.academic-years.manage');
        $year = $this->resolve(AcademicYear::class, $org, $id);
        $this->yearService->{$method}($year);

        return back()->with('status', $message);
    }

    public function terms(Request $request, string $year): View
    {
        return $this->termPage($request, $year, 'index');
    }

    public function termCreate(Request $request, string $year): View
    {
        return $this->termPage($request, $year, 'form');
    }

    public function termEdit(Request $request, string $year, string $term): View
    {
        return $this->termPage($request, $year, 'form', $term);
    }

    private function termPage(Request $request, string $yearId, string $page, ?string $termId = null): View
    {
        [$org, $membership] = $this->context($request, 'academics.terms.view');
        $year = $this->resolve(AcademicYear::class, $org, $yearId);
        $term = $termId ? $this->nested(AcademicTerm::class, $org, $termId, 'academic_year_id', $year->getKey()) : null;

        return view("academics.terms.{$page}", $this->shared($org, $membership) + ['year' => $year, 'term' => $term, 'terms' => $page === 'index' ? $year->terms()->orderBy('term_number')->paginate(15) : null]);
    }

    public function termStore(Request $request, string $year): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.terms.manage');
        $parent = $this->resolve(AcademicYear::class, $org, $year);
        $this->termService->create($parent, $this->termData($request, $parent));

        return redirect()->route('academics.web.terms.index', $parent)->with('status', 'Academic term created.');
    }

    public function termUpdate(Request $request, string $year, string $term): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.terms.manage');
        $parent = $this->resolve(AcademicYear::class, $org, $year);
        $record = $this->nested(AcademicTerm::class, $org, $term, 'academic_year_id', $parent->getKey());
        $this->termService->update($record, $this->termData($request, $parent));

        return redirect()->route('academics.web.terms.index', $parent)->with('status', 'Academic term updated.');
    }

    public function termCurrent(Request $request, string $year, string $term): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.terms.manage');
        $parent = $this->resolve(AcademicYear::class, $org, $year);
        $this->termService->setCurrent($this->nested(AcademicTerm::class, $org, $term, 'academic_year_id', $parent->getKey()));

        return back()->with('status', 'Academic term set as current.');
    }

    public function catalog(Request $request): View
    {
        [$area, $model, $view] = $this->area($request);
        [$org, $membership] = $this->context($request, $view);
        $query = $model::query()->where('organization_id', $org->getKey());
        $search = trim($request->string('search')->toString());
        $query->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        $relations = match ($area) {
            'grades' => ['curriculum', 'academicYear'], 'classes' => ['grade', 'academicYear'], 'subjects' => ['curriculum', 'department'], default => []
        };
        if ($relations !== []) {
            $query->with($relations);
        }

        $records = $query->orderBy($area === 'grades' || $area === 'timetable-periods' ? 'order' : 'name')->paginate(15)->withQueryString();
        $learnerCounts = $area === 'classes'
            ? LearnerProfile::query()->where('organization_id', $org->getKey())->whereIn('current_class_id', $records->pluck('id'))->selectRaw('current_class_id, count(*) as aggregate')->groupBy('current_class_id')->pluck('aggregate', 'current_class_id')
            : collect();

        return view('academics.catalog.index', $this->shared($org, $membership) + compact('area', 'records', 'learnerCounts'));
    }

    public function catalogCreate(Request $request): View
    {
        return $this->catalogForm($request);
    }

    public function catalogEdit(Request $request, string $record): View
    {
        return $this->catalogForm($request, $record);
    }

    public function catalogShow(Request $request, string $record): View
    {
        [$area, $model, $view] = $this->area($request);
        [$org, $membership] = $this->context($request, $view);

        $item = $this->resolve($model, $org, $record);
        $learnerCount = $area === 'classes' ? LearnerProfile::query()->where('organization_id', $org->getKey())->where('current_class_id', $item->getKey())->count() : null;

        return view('academics.catalog.show', $this->shared($org, $membership) + compact('area', 'learnerCount') + ['record' => $item]);
    }

    private function catalogForm(Request $request, ?string $id = null): View
    {
        [$area, $model, , $manage] = $this->area($request);
        [$org, $membership] = $this->context($request, $manage);

        return view('academics.catalog.form', $this->shared($org, $membership) + compact('area') + ['record' => $id ? $this->resolve($model, $org, $id) : null] + $this->options($org));
    }

    public function catalogStore(Request $request): RedirectResponse
    {
        [$area, , , $manage] = $this->area($request);
        [$org] = $this->context($request, $manage);
        $data = $this->catalogData($request, $area, $org);
        $data['organization_id'] = $org->getKey();
        $record = $this->catalogService($area, null, $data);

        return redirect()->route("academics.web.{$area}.show", $record)->with('status', 'Academic record created.');
    }

    public function catalogUpdate(Request $request, string $record): RedirectResponse
    {
        [$area, $model, , $manage] = $this->area($request);
        [$org] = $this->context($request, $manage);
        $item = $this->resolve($model, $org, $record);
        $data = $this->catalogData($request, $area, $org, $item);
        $item = $this->catalogService($area, $item, $data);

        return redirect()->route("academics.web.{$area}.show", $item)->with('status', 'Academic record updated.');
    }

    private function catalogService(string $area, ?Model $record, array $data): Model
    {
        return match ($area) {
            'curricula' => $this->saveCurriculum($record, $data),
            'grades' => $this->saveGrade($record, $data),
            'classes' => $this->saveClass($record, $data),
            'departments' => $this->saveDepartment($record, $data),
            'subjects' => $this->saveSubject($record, $data),
            'timetable-periods' => $this->savePeriod($record, $data),
            default => abort(404),
        };
    }

    private function saveCurriculum(?Model $record, array $data): Curriculum
    {
        assert($record === null || $record instanceof Curriculum);

        return $record ? $this->curriculumService->update($record, $data) : $this->curriculumService->create($data);
    }

    private function saveGrade(?Model $record, array $data): Grade
    {
        assert($record === null || $record instanceof Grade);

        return $record ? $this->gradeService->update($record, $data) : $this->gradeService->create($data);
    }

    private function saveClass(?Model $record, array $data): ClassGroup
    {
        assert($record === null || $record instanceof ClassGroup);

        return $record ? $this->classService->update($record, $data) : $this->classService->create($data);
    }

    private function saveDepartment(?Model $record, array $data): Department
    {
        assert($record === null || $record instanceof Department);

        return $record ? $this->departmentService->update($record, $data) : $this->departmentService->create($data);
    }

    private function saveSubject(?Model $record, array $data): Subject
    {
        assert($record === null || $record instanceof Subject);

        return $record ? $this->subjectService->update($record, $data) : $this->subjectService->create($data);
    }

    private function savePeriod(?Model $record, array $data): TimetablePeriod
    {
        assert($record === null || $record instanceof TimetablePeriod);

        return $record ? $this->timetableService->updatePeriod($record, $data) : $this->timetableService->createPeriod($data);
    }

    public function curriculumDeactivate(Request $request, string $record): RedirectResponse
    {
        return $this->curriculumState($request, $record, false);
    }

    public function curriculumReactivate(Request $request, string $record): RedirectResponse
    {
        return $this->curriculumState($request, $record, true);
    }

    private function curriculumState(Request $request, string $id, bool $active): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.curriculum.manage');
        $curriculum = $this->resolve(Curriculum::class, $org, $id);
        $this->curriculumService->update($curriculum, ['is_active' => $active]);

        return back()->with('status', $active ? 'Curriculum reactivated.' : 'Curriculum deactivated.');
    }

    public function gradesReorder(Request $request): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.grades.manage');
        $ids = $request->validate(['grade_ids' => ['required', 'array', 'min:1'], 'grade_ids.*' => ['uuid', Rule::exists('academics_grades', 'id')->where('organization_id', $org->getKey())]])['grade_ids'];
        $this->gradeService->reorder($ids);

        return back()->with('status', 'Grades reordered.');
    }

    public function calendar(Request $request, string $year): View
    {
        return $this->calendarPage($request, $year, 'index');
    }

    public function calendarCreate(Request $request, string $year): View
    {
        return $this->calendarPage($request, $year, 'form');
    }

    public function calendarEdit(Request $request, string $year, string $entry): View
    {
        return $this->calendarPage($request, $year, 'form', $entry);
    }

    private function calendarPage(Request $request, string $yearId, string $page, ?string $entryId = null): View
    {
        [$org, $membership] = $this->context($request, 'academics.calendar.view');
        $year = $this->resolve(AcademicYear::class, $org, $yearId);
        $entry = $entryId ? $this->nested(CalendarEntry::class, $org, $entryId, 'academic_year_id', $year->getKey()) : null;

        return view("academics.calendar.{$page}", $this->shared($org, $membership) + compact('year', 'entry') + ['entries' => $page === 'index' ? $year->calendarEntries()->orderBy('start_date')->paginate(15) : null]);
    }

    public function calendarStore(Request $request, string $year): RedirectResponse
    {
        return $this->calendarSave($request, $year);
    }

    public function calendarUpdate(Request $request, string $year, string $entry): RedirectResponse
    {
        return $this->calendarSave($request, $year, $entry);
    }

    private function calendarSave(Request $request, string $yearId, ?string $entryId = null): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.calendar.manage');
        $year = $this->resolve(AcademicYear::class, $org, $yearId);
        $start = $year->getAttribute('start_date');
        $end = $year->getAttribute('end_date');
        assert($start instanceof CarbonInterface && $end instanceof CarbonInterface);
        $data = $request->validate(['type' => ['required', Rule::in(['school_day', 'public_holiday', 'exam_period', 'assessment_period', 'event'])], 'name' => ['required', 'string', 'max:255'], 'start_date' => ['required', 'date', 'after_or_equal:'.$start->toDateString(), 'before_or_equal:'.$end->toDateString()], 'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.$end->toDateString()], 'description' => ['nullable', 'string', 'max:2000']]);
        $entryId ? $this->calendarService->updateEntry($this->nested(CalendarEntry::class, $org, $entryId, 'academic_year_id', $year->getKey()), $data) : $this->calendarService->addEntry($year, $data);

        return redirect()->route('academics.web.calendar.index', $year)->with('status', 'Calendar entry saved.');
    }

    public function calendarDestroy(Request $request, string $year, string $entry): RedirectResponse
    {
        [$org] = $this->context($request, 'academics.calendar.manage');
        $parent = $this->resolve(AcademicYear::class, $org, $year);
        $this->calendarService->removeEntry($this->nested(CalendarEntry::class, $org, $entry, 'academic_year_id', $parent->getKey()));

        return back()->with('status', 'Calendar entry deleted.');
    }

    public function settings(Request $request): View
    {
        [$org, $membership] = $this->context($request, 'academics.academic-years.view');

        return view('academics.settings', $this->shared($org, $membership));
    }

    private function catalogData(Request $request, string $area, Organization $org, ?Model $record = null): array
    {
        $id = $org->getKey();
        $ignore = $record?->getKey();
        $rules = match ($area) {
            'curricula' => ['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:50', Rule::unique('academics_curricula', 'code')->where('organization_id', $id)->ignore($ignore)], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['sometimes', 'boolean']],
            'departments' => ['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:50', Rule::unique('academics_departments', 'code')->where('organization_id', $id)->ignore($ignore)], 'description' => ['nullable', 'string', 'max:2000'], 'colour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'is_active' => ['sometimes', 'boolean']],
            'grades' => ['name' => ['required', 'string', 'max:255'], 'order' => ['required', 'integer', 'min:1'], 'curriculum_id' => ['nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $id)], 'academic_year_id' => ['nullable', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $id)], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])]],
            'classes' => ['name' => ['required', 'string', 'max:255'], 'capacity' => ['nullable', 'integer', 'min:1'], 'academic_year_id' => ['required', 'uuid', Rule::exists('academics_academic_years', 'id')->where('organization_id', $id)], 'grade_id' => ['required', 'uuid', Rule::exists('academics_grades', 'id')->where('organization_id', $id)], 'is_homeroom' => ['sometimes', 'boolean'], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])]],
            'subjects' => ['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:50', Rule::unique('academics_subjects', 'code')->where('organization_id', $id)->ignore($ignore)], 'description' => ['nullable', 'string', 'max:2000'], 'colour' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'curriculum_id' => ['nullable', 'uuid', Rule::exists('academics_curricula', 'id')->where('organization_id', $id)], 'department_id' => ['nullable', 'uuid', Rule::exists('academics_departments', 'id')->where('organization_id', $id)], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])]],
            'timetable-periods' => ['name' => ['required', 'string', 'max:255'], 'day_of_week' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'is_break' => ['sometimes', 'boolean'], 'order' => ['required', 'integer', 'min:1'], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])]],
            default => abort(404),
        };
        $data = Validator::make($request->all(), $rules + ['organization_id' => ['prohibited']])->validate();
        if ($area === 'classes') {
            $grade = Grade::query()->withoutGlobalScopes()->where('organization_id', $id)->findOrFail($data['grade_id']);
            assert($grade instanceof Grade);
            Validator::make($data, ['academic_year_id' => [Rule::in([(string) $grade->getAttribute('academic_year_id')])]], ['academic_year_id.in' => 'The class academic year must match the grade academic year.'])->validate();
        }

        return $data;
    }

    private function yearRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date']];
    }

    private function termData(Request $request, AcademicYear $year): array
    {
        $start = $year->getAttribute('start_date');
        $end = $year->getAttribute('end_date');
        assert($start instanceof CarbonInterface && $end instanceof CarbonInterface);

        return $request->validate(['term_number' => ['required', 'integer', 'min:1', 'max:12'], 'name' => ['required', 'string', 'max:255'], 'start_date' => ['required', 'date', 'after_or_equal:'.$start->toDateString(), 'before_or_equal:'.$end->toDateString()], 'end_date' => ['required', 'date', 'after:start_date', 'before_or_equal:'.$end->toDateString()]]);
    }

    private function options(Organization $org): array
    {
        $id = $org->getKey();

        return ['years' => AcademicYear::query()->where('organization_id', $id)->orderByDesc('start_date')->get(), 'curricula' => Curriculum::query()->where('organization_id', $id)->orderBy('name')->get(), 'grades' => Grade::query()->where('organization_id', $id)->orderBy('order')->get(), 'departments' => Department::query()->where('organization_id', $id)->orderBy('name')->get()];
    }

    private function area(Request $request): array
    {
        $area = (string) $request->route('area');
        abort_unless(isset(self::AREAS[$area]), 404);

        return [$area, ...self::AREAS[$area]];
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function resolve(string $model, Organization $org, string $id): Model
    {
        return $model::query()->withoutGlobalScopes()->where('organization_id', $org->getKey())->findOrFail($id);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function nested(string $model, Organization $org, string $id, string $foreignKey, mixed $parent): Model
    {
        return $model::query()->withoutGlobalScopes()->where('organization_id', $org->getKey())->where($foreignKey, $parent)->findOrFail($id);
    }

    private function context(Request $request, string $permission): array
    {
        $org = $request->attributes->get('organization');
        $membership = $request->attributes->get('organization_membership');
        abort_unless($org instanceof Organization && $membership instanceof Membership, 403);
        abort_unless($this->permissions->allows($membership, $permission), 403);

        return [$org, $membership];
    }

    private function shared(Organization $org, Membership $membership): array
    {
        return ['branding' => $this->organizations->branding($org), 'organization' => $org, 'permissions' => $this->permissions->permissions($membership)];
    }
}
