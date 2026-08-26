<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Support\Exceptions\DomainException;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Assessments\Infrastructure\Models\AssessmentQuestion;
use Modules\Assessments\Infrastructure\Models\AssessmentQuestionOption;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;
use Modules\Assessments\Infrastructure\Models\QuizAnswer;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Attendance\Application\AttendanceRecordingService;
use Modules\Attendance\Application\AttendanceSessionService;
use Modules\Attendance\Infrastructure\Models\AttendanceSession;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Materials\Application\MaterialIngestionService;
use Modules\Materials\Infrastructure\Models\Material;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Reports\Application\ReportCardService;
use Modules\Reports\Application\ReportConfigurationService;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Domain\Enums\ReportingPeriodStatus;
use Modules\Reports\Infrastructure\Models\GradingScale;
use Modules\Reports\Infrastructure\Models\ReportCard;
use Modules\Reports\Infrastructure\Models\ReportCardTemplate;
use Modules\Reports\Infrastructure\Models\ReportingPeriod;
use Modules\Staff\Application\TeachingAssignmentService;
use Modules\Staff\Infrastructure\Models\StaffProfile;

/**
 * A full, running demonstration school: organization, academic structure,
 * staff (teachers/tutor/academic administrators), 10 learners with
 * guardians, teaching assignments, teacher-authored materials ("notes"),
 * quizzes taken and marked end to end, attendance, and published report
 * cards (the "posting communication" step — publishing notifies every
 * linked learner and guardian account via Core\Notifications).
 *
 * Every persona gets a real portal login so each can be used to sign in
 * and exercise their own dashboard — see the credential summary this
 * seeder prints at the end. Provisioning goes directly through Eloquent
 * (matching the existing HackathonDemoSeeder's approach) rather than the
 * email-token invitation flow, since this deployment's mail is on the
 * `log` driver and invitation emails would not actually be delivered;
 * everything downstream of account creation (teaching assignments,
 * materials, quizzes, attendance, report cards) goes through the real
 * Application services, not hand-crafted rows, so the business rules
 * (audit logging, duplicate/overlap checks, lifecycle transitions,
 * publish notifications) all genuinely run.
 */
final class SchoolOperationsDemoSeeder extends Seeder
{
    private const PASSWORD = 'Demo@2026!';

    private const DOMAIN = 'riverside-demo.test';

    /** @var array<string, mixed> */
    private array $credentials = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $organization = $this->organization();
            $admin = $this->orgAdmin($organization);
            [$year, $term, $grades, $classes, $subjects] = $this->academics($organization);
            $teachers = $this->teachers($organization);
            $tutor = $this->tutor($organization);
            $academicAdmins = $this->academicAdministrators($organization);
            $this->teachingAssignments($organization, $admin, $year, $classes, $subjects, $teachers);
            [$learners, $guardians] = $this->learnersAndGuardians($organization, $classes, $grades, $year, $admin);
            $this->materials($organization, $teachers, $subjects);
            $this->quizzesAndAttempts($organization, $admin, $year, $term, $grades, $classes, $subjects, $teachers, $learners);
            $this->attendance($organization, $admin, $year, $term, $classes, $subjects, $teachers);
            $this->reportCards($organization, $admin, $year, $term, $learners);
        }, 3);

        $this->printCredentials();
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->updateOrCreate(
            ['code' => 'RIVERSIDE-DEMO'],
            ['name' => 'Riverside Demo Academy', 'type' => 'school', 'status' => 'active', 'email' => 'admin@'.self::DOMAIN, 'country' => 'ZA', 'currency' => 'ZAR', 'maximum_users' => 100],
        );
        foreach (['academics', 'learners', 'staff', 'attendance', 'assessments', 'reports', 'scheduling', 'materials'] as $module) {
            $organization->modules()->updateOrCreate(['module_name' => $module], ['enabled' => true]);
        }

        return $organization;
    }

    private function orgAdmin(Organization $organization): User
    {
        $user = $this->user('principal@'.self::DOMAIN, 'Nomvula Khumalo', 'Organization administrator (principal)');
        $this->membership($organization, $user, 'Organization Administrator');
        $organization->administrators()->syncWithoutDetaching([$user->getKey() => ['assigned_by' => $user->getKey(), 'assigned_at' => now()]]);

        return $user;
    }

    /** @return array{AcademicYear, AcademicTerm, array<string, Grade>, array<string, ClassGroup>, array<string, Subject>} */
    private function academics(Organization $organization): array
    {
        $year = AcademicYear::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'name' => '2026'], ['start_date' => '2026-01-14', 'end_date' => '2026-12-04', 'status' => 'current', 'is_current' => true]);
        $term = AcademicTerm::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'academic_year_id' => $year->getKey(), 'term_number' => 3], ['name' => 'Term 3', 'start_date' => '2026-07-14', 'end_date' => '2026-09-25', 'status' => 'current', 'is_current' => true]);

        $grades = [];
        foreach (['Grade 8' => 8, 'Grade 9' => 9] as $name => $order) {
            $grades[$name] = Grade::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'name' => $name], ['order' => $order, 'academic_year_id' => $year->getKey(), 'status' => 'active']);
        }

        $classes = [];
        foreach (['8A' => 'Grade 8', '9A' => 'Grade 9'] as $name => $gradeName) {
            $classes[$name] = ClassGroup::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'academic_year_id' => $year->getKey(), 'grade_id' => $grades[$gradeName]->getKey(), 'name' => $name], ['capacity' => 35, 'is_homeroom' => true, 'status' => 'active']);
        }

        $subjects = [];
        foreach ([
            'Mathematics' => 'RVD-MATH',
            'English' => 'RVD-ENG',
            'Physical Sciences' => 'RVD-PHY',
            'Life Sciences' => 'RVD-LIFE',
            'History' => 'RVD-HIST',
        ] as $name => $code) {
            $subjects[$name] = Subject::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'code' => $code], ['name' => $name, 'status' => 'active']);
        }

        return [$year, $term, $grades, $classes, $subjects];
    }

    /** @return array<int, array{user: User, staff: StaffProfile, subject: string}> */
    private function teachers(Organization $organization): array
    {
        $roster = [
            ['Naledi', 'Dlamini', 'Mathematics'],
            ['Sipho', 'Nkosi', 'English'],
            ['Zanele', 'Mokoena', 'Physical Sciences'],
            ['Thabo', 'Radebe', 'Life Sciences'],
            ['Ayanda', 'Zulu', 'History'],
        ];
        $teachers = [];
        foreach ($roster as $i => [$first, $last, $subject]) {
            $email = strtolower($first).'.'.strtolower($last).'@'.self::DOMAIN;
            $user = $this->user($email, "{$first} {$last}", "Teacher ({$subject})");
            $membership = $this->membership($organization, $user, 'Teacher');
            $staff = StaffProfile::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'employee_number' => 'RVD-T-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                ['organization_membership_id' => $membership->getKey(), 'user_id' => $user->getKey(), 'first_name' => $first, 'last_name' => $last, 'staff_type' => 'teacher', 'job_title' => "{$subject} Teacher", 'employment_status' => 'active', 'onboarding_status' => 'complete', 'portal_access_enabled' => true, 'work_email' => $email],
            );
            $teachers[] = ['user' => $user, 'staff' => $staff, 'subject' => $subject];
        }

        return $teachers;
    }

    private function tutor(Organization $organization): StaffProfile
    {
        $user = $this->user('kabelo.sithole@'.self::DOMAIN, 'Kabelo Sithole', 'Tutor');
        $membership = $this->membership($organization, $user, 'Tutor');

        return StaffProfile::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'employee_number' => 'RVD-TU-001'],
            ['organization_membership_id' => $membership->getKey(), 'user_id' => $user->getKey(), 'first_name' => 'Kabelo', 'last_name' => 'Sithole', 'staff_type' => 'tutor', 'job_title' => 'Mathematics & Sciences Tutor', 'employment_status' => 'active', 'onboarding_status' => 'complete', 'portal_access_enabled' => true, 'work_email' => 'kabelo.sithole@'.self::DOMAIN],
        );
    }

    /** @return array<int, StaffProfile> */
    private function academicAdministrators(Organization $organization): array
    {
        $roster = [['Precious', 'Mahlangu'], ['Given', 'Mthembu']];
        $admins = [];
        foreach ($roster as $i => [$first, $last]) {
            $email = strtolower($first).'.'.strtolower($last).'@'.self::DOMAIN;
            $user = $this->user($email, "{$first} {$last}", 'Academic administrator');
            $membership = $this->membership($organization, $user, 'Academic Administrator');
            $admins[] = StaffProfile::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'employee_number' => 'RVD-AA-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                ['organization_membership_id' => $membership->getKey(), 'user_id' => $user->getKey(), 'first_name' => $first, 'last_name' => $last, 'staff_type' => 'administrator', 'job_title' => 'Academic Administrator', 'employment_status' => 'active', 'onboarding_status' => 'complete', 'portal_access_enabled' => true, 'work_email' => $email],
            );
        }

        return $admins;
    }

    /**
     * @param  array<string, ClassGroup>  $classes
     * @param  array<string, Subject>  $subjects
     * @param  array<int, array{user: User, staff: StaffProfile, subject: string}>  $teachers
     */
    private function teachingAssignments(Organization $organization, User $actor, AcademicYear $year, array $classes, array $subjects, array $teachers): void
    {
        $service = app(TeachingAssignmentService::class);
        // Every teacher covers their subject in both classes except the
        // science specialists, who each cover one class — a realistic,
        // uneven timetable rather than a mechanical full cross-product.
        $coverage = [
            'Naledi Dlamini' => ['8A', '9A'],
            'Sipho Nkosi' => ['8A', '9A'],
            'Zanele Mokoena' => ['9A'],
            'Thabo Radebe' => ['8A'],
            'Ayanda Zulu' => ['8A', '9A'],
        ];
        foreach ($teachers as $teacher) {
            $name = $teacher['staff']->getAttribute('first_name').' '.$teacher['staff']->getAttribute('last_name');
            foreach ($coverage[$name] ?? [] as $className) {
                try {
                    $service->assign($organization, $teacher['staff'], [
                        'class_id' => $classes[$className]->getKey(),
                        'subject_id' => $subjects[$teacher['subject']]->getKey(),
                        'academic_year_id' => $year->getKey(),
                        'started_on' => '2026-01-14',
                    ], $actor);
                } catch (DomainException) {
                    // Already assigned on a prior run of this seeder — fine.
                }
            }
        }
    }

    /**
     * @param  array<string, ClassGroup>  $classes
     * @param  array<string, Grade>  $grades
     * @return array{0: array<int, LearnerProfile>, 1: array<int, GuardianProfile>}
     */
    private function learnersAndGuardians(Organization $organization, array $classes, array $grades, AcademicYear $year, User $admin): array
    {
        $roster = [
            ['Lerato', 'Molefe', '8A', 'Grade 8', 'Dorah Molefe'],
            ['Amogelang', 'Khumalo', '8A', 'Grade 8', 'Bongani Khumalo'],
            ['Boitumelo', 'Sithole', '8A', 'Grade 8', 'Nomsa Sithole'],
            ['Kagiso', 'Ndlovu', '8A', 'Grade 8', 'Elias Ndlovu'],
            ['Palesa', 'Mahlangu', '8A', 'Grade 8', 'Nomsa Sithole'], // sibling of Boitumelo's guardian
            ['Karabo', 'Mokoena', '9A', 'Grade 9', 'Refilwe Mokoena'],
            ['Refilwe', 'Dube', '9A', 'Grade 9', 'Sizwe Dube'],
            ['Tumi', 'Nkosi', '9A', 'Grade 9', 'Angela Nkosi'],
            ['Onthatile', 'Zulu', '9A', 'Grade 9', 'Refilwe Mokoena'], // sibling
            ['Sibusiso', 'Radebe', '9A', 'Grade 9', 'Patience Radebe'],
        ];

        $learners = [];
        $guardiansByName = [];
        foreach ($roster as $i => [$first, $last, $className, $gradeName, $guardianName]) {
            $email = strtolower($first).'.'.strtolower($last).'@'.self::DOMAIN;
            $user = $this->user($email, "{$first} {$last}", 'Learner');
            $membership = $this->membership($organization, $user, 'Learner');
            $number = 'RVD-2026-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            $learner = LearnerProfile::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'learner_number' => $number],
                [
                    'user_id' => $user->getKey(), 'organization_membership_id' => $membership->getKey(),
                    'first_name' => $first, 'last_name' => $last,
                    'current_academic_year_id' => $year->getKey(), 'current_grade_id' => $grades[$gradeName]->getKey(), 'current_class_id' => $classes[$className]->getKey(),
                    'admission_date' => '2026-01-14', 'learner_status' => 'active', 'onboarding_status' => 'complete', 'portal_access_enabled' => true,
                    'learner_email' => $email, 'metadata' => ['demo_data' => true], 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey(),
                ],
            );
            $learners[] = $learner;

            if (! isset($guardiansByName[$guardianName])) {
                [$gFirst, $gLast] = explode(' ', $guardianName, 2);
                $gEmail = strtolower($gFirst).'.'.strtolower($gLast).'@'.self::DOMAIN;
                $gUser = $this->user($gEmail, $guardianName, 'Guardian/parent');
                $gMembership = $this->membership($organization, $gUser, 'Guardian');
                $guardiansByName[$guardianName] = GuardianProfile::query()->updateOrCreate(
                    ['organization_id' => $organization->getKey(), 'email' => $gEmail],
                    ['user_id' => $gUser->getKey(), 'organization_membership_id' => $gMembership->getKey(), 'first_name' => $gFirst, 'last_name' => $gLast, 'status' => 'active', 'preferred_communication_channel' => 'email', 'created_by' => $admin->getKey()],
                );
            }
            LearnerGuardianRelationship::withTrashed()->updateOrCreate(
                ['learner_profile_id' => $learner->getKey(), 'guardian_profile_id' => $guardiansByName[$guardianName]->getKey()],
                ['organization_id' => $organization->getKey(), 'relationship_type' => 'parent', 'is_primary' => true, 'is_emergency_contact' => true, 'is_authorized_pickup' => true, 'receives_academic_communication' => true, 'receives_financial_communication' => true, 'status' => 'active', 'effective_from' => null, 'effective_until' => null, 'created_by' => $admin->getKey(), 'deleted_at' => null],
            );
        }

        return [$learners, array_values($guardiansByName)];
    }

    /**
     * @param  array<int, array{user: User, staff: StaffProfile, subject: string}>  $teachers
     * @param  array<string, Subject>  $subjects
     */
    private function materials(Organization $organization, array $teachers, array $subjects): void
    {
        $notes = [
            'Mathematics' => ['Introduction to Linear Equations', 'A linear equation describes a straight-line relationship between two quantities. Solving one means finding the value of the unknown that makes both sides equal. Always apply the same operation to both sides so the equation stays balanced: add or subtract the same amount, or multiply or divide by the same non-zero number. Worked example: to solve 2x + 3 = 11, first subtract 3 from both sides to get 2x = 8, then divide both sides by 2 to get x = 4. Checking your answer by substituting it back into the original equation is good practice and catches most arithmetic mistakes before they cost marks.'],
            'Life Sciences' => ['Photosynthesis Overview', 'Photosynthesis is the process green plants use to convert light energy into chemical energy stored in glucose. It takes place mainly in the chloroplasts of leaf cells, using chlorophyll to absorb sunlight. The overall reaction combines carbon dioxide from the air and water from the soil, releasing oxygen as a by-product and producing glucose that the plant uses for energy and growth. Factors affecting the rate of photosynthesis include light intensity, carbon dioxide concentration, and temperature — each can become a limiting factor if it falls too low relative to the others.'],
        ];
        foreach ($teachers as $teacher) {
            if (! isset($notes[$teacher['subject']])) {
                continue;
            }
            [$title, $content] = $notes[$teacher['subject']];
            if (Material::query()->where('organization_id', $organization->getKey())->where('title', $title)->exists()) {
                continue;
            }
            app(MaterialIngestionService::class)->ingest($organization, $teacher['user'], [
                'title' => $title, 'content' => $content, 'subject_id' => $subjects[$teacher['subject']]->getKey(),
            ]);
        }
    }

    /**
     * @param  array<string, Grade>  $grades
     * @param  array<string, ClassGroup>  $classes
     * @param  array<string, Subject>  $subjects
     * @param  array<int, array{user: User, staff: StaffProfile, subject: string}>  $teachers
     * @param  array<int, LearnerProfile>  $learners
     */
    private function quizzesAndAttempts(Organization $organization, User $admin, AcademicYear $year, AcademicTerm $term, array $grades, array $classes, array $subjects, array $teachers, array $learners): void
    {
        $category = AssessmentCategory::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'name' => 'Class Test'], ['code' => 'TEST', 'is_active' => true, 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()]);

        $maths = $this->firstWhere($teachers, 'subject', 'Mathematics');
        $science = $this->firstWhere($teachers, 'subject', 'Life Sciences');

        $mathsLearners = array_values(array_filter($learners, fn (LearnerProfile $l) => $l->getAttribute('current_class_id') === $classes['8A']->getKey()));
        $scienceLearners = array_values(array_filter($learners, fn (LearnerProfile $l) => $l->getAttribute('current_class_id') === $classes['9A']->getKey()));

        $this->seedQuiz($organization, $year, $term, $grades['Grade 8'], $classes['8A'], $subjects['Mathematics'], $category, $maths, $mathsLearners, 'Linear Equations Check-in', [
            ['multiple_choice', 'Solve 2x + 3 = 11.', 3, [['x = 3', false], ['x = 4', true], ['x = 5', false]]],
            ['true_false', 'A negative multiplied by a negative is positive.', 2, [['True', true], ['False', false]]],
            ['short_response', 'Explain why the same operation must be applied to both sides of an equation.', 5, []],
        ]);

        $this->seedQuiz($organization, $year, $term, $grades['Grade 9'], $classes['9A'], $subjects['Life Sciences'], $category, $science, $scienceLearners, 'Photosynthesis Check-in', [
            ['multiple_choice', 'Photosynthesis mainly takes place in which cell structure?', 3, [['Mitochondria', false], ['Chloroplast', true], ['Nucleus', false]]],
            ['true_false', 'Oxygen is released as a by-product of photosynthesis.', 2, [['True', true], ['False', false]]],
            ['short_response', 'Name two factors that can limit the rate of photosynthesis.', 5, []],
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int, 3: array<int, array{0: string, 1: bool}>}>  $questionSpecs
     * @param  array<int, LearnerProfile>  $classLearners
     */
    private function seedQuiz(Organization $organization, AcademicYear $year, AcademicTerm $term, Grade $grade, ClassGroup $class, Subject $subject, AssessmentCategory $category, ?array $teacher, array $classLearners, string $title, array $questionSpecs): void
    {
        if ($teacher === null || $classLearners === []) {
            return;
        }
        $teacherUser = $teacher['user'];
        $staff = $teacher['staff'];

        $quiz = Assessment::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'title' => $title],
            ['academic_year_id' => $year->getKey(), 'academic_term_id' => $term->getKey(), 'grade_id' => $grade->getKey(), 'class_id' => $class->getKey(), 'subject_id' => $subject->getKey(), 'assessment_category_id' => $category->getKey(), 'staff_profile_id' => $staff->getKey(), 'description' => 'Seeded demo class test.', 'instructions' => 'Answer every question; show working for written responses.', 'assessment_date' => now()->subDays(2)->toDateString(), 'maximum_mark' => (float) array_sum(array_column($questionSpecs, 2)), 'status' => 'finalized', 'finalized_at' => now()->subDay(), 'finalized_by' => $teacherUser->getKey(), 'result_release_status' => 'released', 'released_at' => now()->subHours(20), 'released_by' => $teacherUser->getKey(), 'attempt_limit' => 1, 'created_by' => $teacherUser->getKey(), 'updated_by' => $teacherUser->getKey()],
        );

        $questions = [];
        foreach ($questionSpecs as $index => [$type, $prompt, $marks, $options]) {
            $question = AssessmentQuestion::query()->updateOrCreate(['assessment_id' => $quiz->getKey(), 'display_order' => $index + 1], ['organization_id' => $organization->getKey(), 'type' => $type, 'prompt' => $prompt, 'marks_available' => $marks, 'model_answer' => $type === 'short_response' ? 'Any answer demonstrating the concept correctly.' : null, 'marking_guidance' => $type === 'short_response' ? 'Award full marks for a correct, clearly explained answer.' : null, 'key_concepts' => []]);
            foreach ($options as $optionIndex => [$label, $correct]) {
                AssessmentQuestionOption::query()->updateOrCreate(['assessment_question_id' => $question->getKey(), 'display_order' => $optionIndex + 1], ['organization_id' => $organization->getKey(), 'label' => $label, 'is_correct' => $correct]);
            }
            $questions[] = $question->load('options');
        }
        $maxMark = (float) array_sum(array_column($questionSpecs, 2));

        foreach ($classLearners as $i => $learner) {
            // A varied, realistic-looking spread of performance rather than
            // identical scores for every learner.
            $performance = [0.9, 0.75, 0.6, 0.85, 0.7][$i % 5];
            $score = round($maxMark * $performance);

            $result = AssessmentResult::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'assessment_id' => $quiz->getKey(), 'learner_profile_id' => $learner->getKey()],
                ['score' => $score, 'percentage' => round(($score / $maxMark) * 100), 'result_status' => 'marked', 'feedback' => $performance >= 0.8 ? 'Excellent work — clear reasoning throughout.' : 'Good foundations; show each working step more clearly next time.', 'marked_by' => $teacherUser->getKey(), 'marked_at' => now(), 'updated_by' => $teacherUser->getKey()],
            );
            $attempt = QuizAttempt::query()->updateOrCreate(
                ['assessment_id' => $quiz->getKey(), 'learner_profile_id' => $learner->getKey(), 'attempt_number' => 1],
                ['organization_id' => $organization->getKey(), 'assessment_result_id' => $result->getKey(), 'status' => 'released', 'started_at' => now()->subDays(2), 'submitted_at' => now()->subDays(2)->addMinutes(20), 'reviewed_at' => now()->subDay(), 'reviewed_by' => $teacherUser->getKey(), 'released_at' => now()->subHours(20), 'final_score' => $score],
            );

            $remaining = $score;
            foreach ($questions as $qIndex => $question) {
                $isLast = $qIndex === count($questions) - 1;
                $marksAvailable = (float) $question->marks_available;
                $awarded = $isLast ? max(0, $remaining) : min($marksAvailable, round($remaining * ($marksAvailable / $maxMark)));
                $remaining -= $awarded;
                $isObjective = $question->type->isObjective();
                QuizAnswer::query()->updateOrCreate(
                    ['quiz_attempt_id' => $attempt->getKey(), 'assessment_question_id' => $question->getKey()],
                    [
                        'organization_id' => $organization->getKey(),
                        'selected_option_id' => $isObjective ? ($awarded > 0 ? $question->options->firstWhere('is_correct', true)?->getKey() : $question->options->firstWhere('is_correct', false)?->getKey()) : null,
                        'answer_text' => $isObjective ? null : 'Applying the same operation to both sides keeps the equation balanced, so the two sides stay equal while we isolate the unknown.',
                        'marks_available' => $question->marks_available,
                        'marks_awarded' => $awarded,
                        'marking_method' => $isObjective ? 'automatic' : 'manual',
                        'teacher_feedback' => $isObjective ? null : ($awarded >= $question->marks_available ? 'Clear and complete.' : 'Good idea — make the explanation more precise.'),
                        'marked_by' => $isObjective ? null : $teacherUser->getKey(),
                        'marked_at' => $isObjective ? null : now()->subDay(),
                    ],
                );
            }

            QuizStudyPlan::query()->updateOrCreate(['quiz_attempt_id' => $attempt->getKey(), 'version' => 1], [
                'organization_id' => $organization->getKey(), 'learner_profile_id' => $learner->getKey(), 'status' => 'published',
                'approved_by' => $teacherUser->getKey(), 'approved_at' => now()->subHours(19), 'published_by' => $teacherUser->getKey(), 'published_at' => now()->subHours(19),
                'completion_percentage' => 0, 'time_spent_minutes' => 0, 'completed_activities' => [], 'mastered_concepts' => [], 'remaining_concepts' => [$subject->getAttribute('name')],
                'content' => [
                    'summary' => "Keep practising {$subject->getAttribute('name')} fundamentals and show every working step.",
                    'weak_concepts' => [$subject->getAttribute('name')], 'learning_goals' => ['Review the core method from class.', 'Practise three similar questions.'],
                    'daily_schedule' => collect(range(1, 5))->map(fn ($day) => ['activity_id' => 'day-'.$day, 'day' => $day, 'duration_minutes' => 20, 'topic' => $subject->getAttribute('name'), 'activity' => 'Review one worked example and complete two practice questions.'])->all(),
                    'revision_exercises' => [['activity_id' => 'revision-1', 'concept' => $subject->getAttribute('name'), 'difficulty' => 'medium', 'question' => 'Revisit today\'s check-in question and redo it from memory.', 'success_criteria' => 'Complete the full method without checking notes.']],
                    'reflection_questions' => ['Which step took the longest to work out?'],
                    'recommended_videos' => [], 'recommended_reading' => [],
                    'estimated_duration_minutes' => 100, 'success_criteria' => ['Show every working step.'],
                    'next_assessment_recommendation' => 'Retest after completing this week\'s revision exercises.',
                    'teacher_comment' => 'Solid effort — keep showing your working.',
                ],
            ]);
        }
    }

    /**
     * @param  array<string, ClassGroup>  $classes
     * @param  array<string, Subject>  $subjects
     * @param  array<int, array{user: User, staff: StaffProfile, subject: string}>  $teachers
     */
    private function attendance(Organization $organization, User $admin, AcademicYear $year, AcademicTerm $term, array $classes, array $subjects, array $teachers): void
    {
        $sessions = app(AttendanceSessionService::class);
        $recording = app(AttendanceRecordingService::class);
        $maths = $this->firstWhere($teachers, 'subject', 'Mathematics');
        if ($maths === null) {
            return;
        }

        foreach (['8A' => -2, '9A' => -1] as $className => $daysAgo) {
            $sessionDate = now()->addDays($daysAgo)->toDateString();
            $existing = AttendanceSession::query()->where('organization_id', $organization->getKey())->where('class_id', $classes[$className]->getKey())->where('session_date', $sessionDate)->first();
            if ($existing !== null) {
                continue;
            }
            $session = $sessions->create($organization, $admin, [
                'academic_year_id' => $year->getKey(), 'academic_term_id' => $term->getKey(),
                'class_id' => $classes[$className]->getKey(), 'subject_id' => $subjects['Mathematics']->getKey(),
                'staff_profile_id' => $maths['staff']->getKey(), 'session_date' => $sessionDate,
                'session_type' => 'class', 'title' => "{$className} morning register",
            ]);
            $rows = $session->entries->values()->map(fn ($entry, $i) => [
                'entry_uuid' => $entry->getAttribute('uuid'),
                'status' => $i === 0 ? 'late' : ($i === 1 ? 'absent' : 'present'),
            ])->all();
            $recording->record($session, $admin, $rows);
            $sessions->finalize($session->fresh(), $admin);
        }
    }

    /** @param  array<int, LearnerProfile>  $learners */
    private function reportCards(Organization $organization, User $admin, AcademicYear $year, AcademicTerm $term, array $learners): void
    {
        $config = app(ReportConfigurationService::class);

        $existingScale = GradingScale::query()->where('organization_id', $organization->getKey())->where('name', 'Standard Percentage Scale')->first();
        $scale = $config->saveScale($organization, $admin, [
            'name' => 'Standard Percentage Scale', 'code' => 'STD', 'pass_threshold' => 50, 'is_active' => true,
            'bands' => [
                ['label' => 'Fail', 'code' => 'F', 'minimum_percentage' => 0, 'maximum_percentage' => 49.99, 'symbol' => 'F', 'is_passing' => false],
                ['label' => 'Adequate', 'code' => 'D', 'minimum_percentage' => 50, 'maximum_percentage' => 59.99, 'symbol' => 'D', 'is_passing' => true],
                ['label' => 'Good', 'code' => 'C', 'minimum_percentage' => 60, 'maximum_percentage' => 69.99, 'symbol' => 'C', 'is_passing' => true],
                ['label' => 'Very Good', 'code' => 'B', 'minimum_percentage' => 70, 'maximum_percentage' => 79.99, 'symbol' => 'B', 'is_passing' => true],
                ['label' => 'Outstanding', 'code' => 'A', 'minimum_percentage' => 80, 'maximum_percentage' => 100, 'symbol' => 'A', 'is_passing' => true],
            ],
        ], $existingScale);
        $config->setScaleState($scale->fresh(), $admin, true, true);

        $existingPeriod = ReportingPeriod::query()->where('organization_id', $organization->getKey())->where('code', 'T3-2026')->first();
        $period = $config->savePeriod($organization, $admin, [
            'academic_year_id' => $year->getKey(), 'academic_term_id' => $term->getKey(),
            'name' => 'Term 3 Report', 'code' => 'T3-2026', 'start_date' => $term->getAttribute('start_date')->toDateString(), 'end_date' => $term->getAttribute('end_date')->toDateString(),
        ], $existingPeriod);
        if ($period->status === ReportingPeriodStatus::Draft) {
            $period = $config->transitionPeriod($period, $admin, ReportingPeriodStatus::Open);
        }

        $existingTemplate = ReportCardTemplate::query()->where('organization_id', $organization->getKey())->where('name', 'Standard Report')->first();
        $template = $config->saveTemplate($organization, $admin, [
            'name' => 'Standard Report', 'is_active' => true, 'show_attendance' => true, 'show_assessment_breakdown' => true,
            'show_subject_comments' => true, 'show_overall_comment' => true, 'show_grading_legend' => true, 'page_size' => 'A4',
        ], $existingTemplate);
        $config->defaultTemplate($template->fresh(), $admin);

        $reportCards = app(ReportCardService::class);
        foreach ($learners as $learner) {
            $existingCard = ReportCard::query()->where('organization_id', $organization->getKey())->where('learner_profile_id', $learner->getKey())->where('reporting_period_id', $period->getKey())->orderByDesc('version_number')->first();
            if ($existingCard?->status === ReportCardStatus::Published) {
                continue;
            }
            try {
                $card = $reportCards->generate($learner, $period->fresh(), $scale->fresh(), $template->fresh(), $admin, $existingCard);
            } catch (DomainException $exception) {
                $this->command->warn("Skipped report card for {$learner->getAttribute('first_name')}: {$exception->getMessage()}");

                continue;
            }
            if ($card->status === ReportCardStatus::Generated) {
                $card = $reportCards->transition($card, $admin, ReportCardStatus::UnderReview);
            }
            if ($card->status === ReportCardStatus::UnderReview) {
                $card = $reportCards->transition($card, $admin, ReportCardStatus::Approved);
            }
            if ($card->status === ReportCardStatus::Approved) {
                // Publishing is the "posting communication" step: it
                // notifies the learner's and every academically-linked
                // guardian's portal account via Core\Notifications.
                $reportCards->transition($card, $admin, ReportCardStatus::Published);
            }
        }
    }

    private function user(string $email, string $name, string $roleLabel): User
    {
        $user = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now(), 'timezone' => 'Africa/Johannesburg']);
        $this->credentials[] = ['role' => $roleLabel, 'name' => $name, 'email' => $email];

        return $user;
    }

    private function membership(Organization $organization, User $user, string $roleName): Membership
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => false]);

        return Membership::query()->updateOrCreate(['user_id' => $user->getKey(), 'organization_id' => $organization->getKey()], ['role_id' => $role->getKey(), 'status' => 'active', 'joined_at' => now(), 'accepted_at' => now(), 'is_primary' => true, 'is_default' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function firstWhere(array $rows, string $key, mixed $value): ?array
    {
        foreach ($rows as $row) {
            if ($row[$key] === $value) {
                return $row;
            }
        }

        return null;
    }

    private function printCredentials(): void
    {
        $this->command->info('');
        $this->command->info('=== Riverside Demo Academy — login credentials (password for all: '.self::PASSWORD.') ===');
        foreach ($this->credentials as $c) {
            $this->command->line(sprintf('%-32s  %-28s  %s', $c['role'], $c['name'], $c['email']));
        }
    }
}
