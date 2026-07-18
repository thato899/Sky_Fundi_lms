<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Identity\Infrastructure\Models\Membership;
use Core\Licensing\Infrastructure\Models\License;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Subscriptions\Infrastructure\Models\Subscription;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Academics\Infrastructure\Models\AcademicTerm;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Academics\Infrastructure\Models\ClassGroup;
use Modules\Academics\Infrastructure\Models\Grade;
use Modules\Academics\Infrastructure\Models\Subject;
use Modules\Assessments\Infrastructure\Models\AiGradingRequest;
use Modules\Assessments\Infrastructure\Models\Assessment;
use Modules\Assessments\Infrastructure\Models\AssessmentCategory;
use Modules\Assessments\Infrastructure\Models\AssessmentQuestion;
use Modules\Assessments\Infrastructure\Models\AssessmentQuestionOption;
use Modules\Assessments\Infrastructure\Models\AssessmentResult;
use Modules\Assessments\Infrastructure\Models\QuizAnswer;
use Modules\Assessments\Infrastructure\Models\QuizAttempt;
use Modules\Assessments\Infrastructure\Models\QuizStudyPlan;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Staff\Infrastructure\Models\StaffProfile;

final class HackathonDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('hackathon.demo_password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Set HACKATHON_DEMO_PASSWORD to at least 12 characters before seeding demo users.');
        }

        DB::transaction(function () use ($password): void {
            $organization = Organization::query()->updateOrCreate(['code' => 'UFA-DEMO'], ['name' => 'Ubuntu Future Academy', 'type' => 'school', 'status' => 'active', 'email' => 'admin@ubuntu-future.demo', 'country' => 'ZA', 'currency' => 'ZAR', 'maximum_users' => 50]);
            $admin = $this->user('admin@ubuntu-future.demo', 'Ubuntu Future Administrator', $password);
            $teacher = $this->user('math.teacher@ubuntu-future.demo', 'Naledi Dlamini', $password);
            $learnerUser = $this->user('lerato@ubuntu-future.demo', 'Lerato Molefe', $password);
            $guardianUser = $this->user('thandi@ubuntu-future.demo', 'Thandi Molefe', $password);

            $adminMembership = $this->membership($organization, $admin, 'Organization Administrator');
            $teacherMembership = $this->membership($organization, $teacher, 'Teacher');
            $learnerMembership = $this->membership($organization, $learnerUser, 'Learner');
            $guardianMembership = $this->membership($organization, $guardianUser, 'Guardian');
            $organization->administrators()->syncWithoutDetaching([$admin->getKey() => ['assigned_by' => $admin->getKey(), 'assigned_at' => now()]]);

            foreach (['academics', 'learners', 'staff', 'assessments', 'reports'] as $module) {
                $organization->modules()->updateOrCreate(['module_name' => $module], ['enabled' => true, 'enabled_by' => $admin->getKey()]);
            }

            $year = AcademicYear::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'name' => '2026'], ['start_date' => '2026-01-14', 'end_date' => '2026-12-04', 'status' => 'current', 'is_current' => true]);
            $term = AcademicTerm::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'academic_year_id' => $year->getKey(), 'term_number' => 3], ['name' => 'Term 3', 'start_date' => '2026-07-14', 'end_date' => '2026-09-25', 'status' => 'current', 'is_current' => true]);
            $grade = Grade::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'name' => 'Grade 10'], ['order' => 10, 'academic_year_id' => $year->getKey(), 'status' => 'active']);
            $class = ClassGroup::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'academic_year_id' => $year->getKey(), 'grade_id' => $grade->getKey(), 'name' => '10A'], ['capacity' => 35, 'is_homeroom' => true, 'status' => 'active']);
            $maths = Subject::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'code' => 'UFA-MATH10'], ['name' => 'Mathematics', 'description' => 'Grade 10 Mathematics', 'status' => 'active']);
            Subject::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'code' => 'UFA-PHY10'], ['name' => 'Physical Sciences', 'status' => 'active']);

            $staff = StaffProfile::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'employee_number' => 'UFA-T-001'], ['organization_membership_id' => $teacherMembership->getKey(), 'user_id' => $teacher->getKey(), 'first_name' => 'Naledi', 'last_name' => 'Dlamini', 'staff_type' => 'teacher', 'job_title' => 'Mathematics Teacher', 'employment_status' => 'active', 'onboarding_status' => 'complete', 'portal_access_enabled' => true, 'work_email' => $teacher->email]);
            $learner = LearnerProfile::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'learner_number' => 'UFA-2026-001'], ['user_id' => $learnerUser->getKey(), 'organization_membership_id' => $learnerMembership->getKey(), 'first_name' => 'Lerato', 'last_name' => 'Molefe', 'current_academic_year_id' => $year->getKey(), 'current_grade_id' => $grade->getKey(), 'current_class_id' => $class->getKey(), 'learner_status' => 'active', 'onboarding_status' => 'complete', 'portal_access_enabled' => true, 'metadata' => ['demo_data' => true], 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()]);
            LearnerProfile::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'learner_number' => 'UFA-2026-099'], ['first_name' => 'Amo', 'last_name' => 'Khumalo', 'current_academic_year_id' => $year->getKey(), 'current_grade_id' => $grade->getKey(), 'current_class_id' => $class->getKey(), 'learner_status' => 'active', 'portal_access_enabled' => false, 'metadata' => ['demo_data' => true], 'created_by' => $admin->getKey()]);
            $guardian = GuardianProfile::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'email' => $guardianUser->email], ['user_id' => $guardianUser->getKey(), 'organization_membership_id' => $guardianMembership->getKey(), 'first_name' => 'Thandi', 'last_name' => 'Molefe', 'status' => 'active', 'preferred_communication_channel' => 'email', 'created_by' => $admin->getKey()]);
            GuardianProfile::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'email' => 'unrelated.guardian@ubuntu-future.demo'], ['first_name' => 'Unrelated', 'last_name' => 'Guardian', 'status' => 'active', 'created_by' => $admin->getKey()]);
            LearnerGuardianRelationship::withTrashed()->updateOrCreate(['learner_profile_id' => $learner->getKey(), 'guardian_profile_id' => $guardian->getKey()], ['organization_id' => $organization->getKey(), 'relationship_type' => 'mother', 'is_primary' => true, 'receives_academic_communication' => true, 'status' => 'active', 'effective_from' => null, 'effective_until' => null, 'created_by' => $admin->getKey(), 'deleted_at' => null]);

            $category = AssessmentCategory::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'name' => 'Hackathon Quiz'], ['code' => 'QUIZ', 'is_active' => true, 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()]);
            $quiz = Assessment::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'title' => 'Forces and Linear Equations Check-in'], ['academic_year_id' => $year->getKey(), 'academic_term_id' => $term->getKey(), 'grade_id' => $grade->getKey(), 'class_id' => $class->getKey(), 'subject_id' => $maths->getKey(), 'assessment_category_id' => $category->getKey(), 'staff_profile_id' => $staff->getKey(), 'description' => 'Seeded hackathon demonstration quiz.', 'instructions' => 'Answer every question and show working for written responses.', 'maximum_mark' => 20, 'status' => 'open', 'result_release_status' => 'released', 'attempt_limit' => 1, 'created_by' => $teacher->getKey(), 'updated_by' => $teacher->getKey()]);

            $questions = [
                ['multiple_choice', 'Solve 2x + 3 = 11.', 3, null, null, [['x = 3', false], ['x = 4', true], ['x = 5', false]]],
                ['true_false', 'A negative multiplied by a negative is positive.', 2, 'True', null, [['True', true], ['False', false]]],
                ['short_response', 'Explain why the same operation must be applied to both sides of an equation.', 5, 'It preserves equality because both expressions are changed equally.', 'Award marks for preserving equality, applying the inverse operation, and clear explanation.', []],
                ['long_response', 'A 4 kg object accelerates at 3 m/s². Calculate the force and explain each step.', 10, 'F = ma = 4 × 3 = 12 N.', 'Formula 2; substitution 2; calculation 2; units 2; explanation 2.', []],
            ];
            foreach ($questions as $index => [$type, $prompt, $marks, $model, $guidance, $options]) {
                $question = AssessmentQuestion::query()->updateOrCreate(['assessment_id' => $quiz->getKey(), 'display_order' => $index + 1], ['organization_id' => $organization->getKey(), 'type' => $type, 'prompt' => $prompt, 'marks_available' => $marks, 'model_answer' => $model, 'marking_guidance' => $guidance, 'key_concepts' => []]);
                foreach ($options as $optionIndex => [$label, $correct]) {
                    AssessmentQuestionOption::query()->updateOrCreate(['assessment_question_id' => $question->getKey(), 'display_order' => $optionIndex + 1], ['organization_id' => $organization->getKey(), 'label' => $label, 'is_correct' => $correct]);
                }
            }

            $result = AssessmentResult::query()->updateOrCreate(['organization_id' => $organization->getKey(), 'assessment_id' => $quiz->getKey(), 'learner_profile_id' => $learner->getKey()], ['score' => 15, 'percentage' => 75, 'result_status' => 'marked', 'feedback' => 'Good foundations. Show each substitution step and explain why it is valid.', 'marked_by' => $teacher->getKey(), 'marked_at' => now(), 'updated_by' => $teacher->getKey()]);
            $attempt = QuizAttempt::query()->updateOrCreate(['assessment_id' => $quiz->getKey(), 'learner_profile_id' => $learner->getKey(), 'attempt_number' => 1], ['organization_id' => $organization->getKey(), 'assessment_result_id' => $result->getKey(), 'status' => 'released', 'started_at' => now()->subDay(), 'submitted_at' => now()->subDay()->addMinutes(18), 'reviewed_at' => now()->subHours(18), 'reviewed_by' => $teacher->getKey(), 'released_at' => now()->subHours(16), 'final_score' => 15]);
            foreach ($quiz->questions()->with('options')->get() as $index => $question) {
                $feedback = $index >= 2 ? ['awarded_marks' => $index === 2 ? 4 : 6, 'max_marks' => (float) $question->marks_available, 'criteria' => [['criterion' => 'Core method', 'met' => true, 'marks_awarded' => 2]], 'strengths' => ['Identified the correct principle'], 'improvements' => ['Show the substitution step'], 'misconceptions' => $index === 3 ? ['Confused velocity with acceleration in the explanation'] : [], 'grading_rationale' => 'The response used the correct principle but omitted a complete explanation.', 'confidence' => 0.82, 'requires_teacher_review' => true] : null;
                QuizAnswer::query()->updateOrCreate(['quiz_attempt_id' => $attempt->getKey(), 'assessment_question_id' => $question->getKey()], ['organization_id' => $organization->getKey(), 'selected_option_id' => $question->options->firstWhere('is_correct', true)?->getKey(), 'answer_text' => $index === 2 ? 'It keeps the equation balanced.' : ($index === 3 ? 'F = 4 x 3 = 12 N because acceleration makes force.' : null), 'marks_available' => $question->marks_available, 'ai_suggested_mark' => $feedback['awarded_marks'] ?? null, 'marks_awarded' => [3, 2, 4, 6][$index], 'marking_method' => $index < 2 ? 'automatic' : 'ai_suggested', 'ai_feedback' => $feedback, 'teacher_feedback' => $index >= 2 ? 'Correct direction; add the missing reasoning step.' : 'Correct.', 'marked_by' => $teacher->getKey(), 'marked_at' => now()->subHours(18)]);
            }
            /** @var QuizAnswer|null $written */
            $written = $attempt->answers()->whereNotNull('ai_feedback')->first();
            AiGradingRequest::query()->updateOrCreate(['idempotency_key' => hash('sha256', 'seeded-demo-ai-marking')], ['organization_id' => $organization->getKey(), 'quiz_attempt_id' => $attempt->getKey(), 'quiz_answer_id' => $written?->getKey(), 'request_type' => 'written_marking', 'provider' => 'seeded_demo', 'model' => 'representative-fixture', 'status' => 'completed', 'input_tokens' => 320, 'output_tokens' => 180, 'estimated_cost' => 0.004680, 'completed_at' => now()->subHours(18)]);
            QuizStudyPlan::query()->updateOrCreate(['quiz_attempt_id' => $attempt->getKey(), 'version' => 1], ['organization_id' => $organization->getKey(), 'learner_profile_id' => $learner->getKey(), 'status' => 'published', 'approved_by' => $teacher->getKey(), 'approved_at' => now()->subHours(17), 'published_by' => $teacher->getKey(), 'published_at' => now()->subHours(17), 'completion_percentage' => 35, 'time_spent_minutes' => 45, 'completed_activities' => ['day-1'], 'mastered_concepts' => [], 'remaining_concepts' => ['Substitution', 'Force units'], 'content' => ['summary' => 'Focus on showing algebra and force-calculation steps clearly.', 'weak_concepts' => ['Substitution', 'Force units'], 'learning_goals' => ['Show each substitution step.', 'Use and check correct force units.'], 'daily_schedule' => collect(range(1, 7))->map(fn ($day) => ['activity_id' => 'day-'.$day, 'day' => $day, 'duration_minutes' => 30, 'topic' => $day < 4 ? 'Substitution' : 'Force units', 'activity' => 'Review one worked example and complete three practice questions.'])->all(), 'revision_exercises' => [['activity_id' => 'revision-easy', 'concept' => 'Substitution', 'difficulty' => 'easy', 'question' => 'Substitute x = 4 into 2x + 3.', 'success_criteria' => 'Show substitution and calculate accurately.'], ['activity_id' => 'revision-medium', 'concept' => 'Force units', 'difficulty' => 'medium', 'question' => 'Calculate force for a 2 kg mass accelerating at 3 m/s².', 'success_criteria' => 'Use F = ma and include newtons.'], ['activity_id' => 'revision-challenge', 'concept' => 'Substitution', 'difficulty' => 'challenge', 'question' => 'Verify whether x = 4 solves 3x - 1 = 11 and explain.', 'success_criteria' => 'Substitute, simplify both sides, and conclude.']], 'reflection_questions' => ['Which step do you most often omit?', 'How will you check units next time?'], 'recommended_videos' => [['title' => 'Substitution in linear equations', 'search_topic' => 'grade linear equation substitution worked examples']], 'recommended_reading' => [['title' => 'Force calculation worked examples', 'description' => 'Review formula selection, substitution, and SI units.']], 'estimated_duration_minutes' => 210, 'success_criteria' => ['Show every algebra step.', 'Include correct units in force answers.'], 'next_assessment_recommendation' => 'Retest after completing the medium and challenge exercises.', 'teacher_comment' => 'Show every step and check units before submitting.']]);

            License::query()->updateOrCreate(['licensee_type' => Organization::class, 'licensee_id' => $organization->getKey()], ['license_key' => 'DEMO-UFA-GROWTH-2026', 'tier' => 'professional', 'status' => 'active', 'activation_date' => today(), 'expiry_date' => today()->addYear(), 'renewal_date' => today()->addMonth(), 'max_users' => 25, 'max_learners' => 500, 'max_storage_mb' => 20480, 'enabled_modules' => ['learners', 'staff', 'academics', 'assessments', 'reports'], 'ai_provider' => 'openai', 'support_level' => 'email', 'metadata' => ['demo_data' => true], 'created_by' => $admin->getKey()]);
            Subscription::query()->updateOrCreate(['subscriber_type' => Organization::class, 'subscriber_id' => $organization->getKey()], ['plan' => 'growth', 'billing_cycle' => 'monthly', 'status' => 'active', 'started_at' => today()->startOfMonth(), 'renewal_date' => today()->addMonth(), 'max_users' => 25, 'current_users' => 4, 'max_storage_mb' => 20480, 'current_storage_mb' => 512, 'ai_usage' => ['markings_used' => 1, 'allowance' => 500], 'module_access' => ['quizzes', 'guardian_portal', 'study_plans'], 'metadata' => ['monthly_price' => 1499, 'demo_data' => true]]);
        }, 3);
    }

    private function user(string $email, string $name, string $password): User
    {
        return User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'status' => 'active', 'email_verified_at' => now(), 'timezone' => 'Africa/Johannesburg']);
    }

    private function membership(Organization $organization, User $user, string $roleName): Membership
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['is_system' => false]);

        return Membership::query()->updateOrCreate(['user_id' => $user->getKey(), 'organization_id' => $organization->getKey()], ['role_id' => $role->getKey(), 'status' => 'active', 'joined_at' => now(), 'accepted_at' => now(), 'is_primary' => true, 'is_default' => true]);
    }
}
