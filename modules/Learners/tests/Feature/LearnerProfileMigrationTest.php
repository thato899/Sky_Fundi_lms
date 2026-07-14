<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerProfileMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learner_profiles'));
        $this->assertTrue(Schema::hasColumns('learner_profiles', [
            'id', 'uuid', 'organization_id', 'user_id', 'organization_membership_id',
            'learner_number', 'admission_number', 'first_name', 'middle_name',
            'last_name', 'preferred_name', 'date_of_birth', 'profile_photo_path',
            'current_academic_year_id', 'current_grade_id', 'current_class_id',
            'curriculum_id', 'admission_date', 'expected_completion_date',
            'previous_institution', 'language_of_instruction', 'home_language',
            'learning_mode', 'learner_email', 'learner_phone', 'residential_address',
            'city', 'province', 'country', 'postal_code', 'learner_status',
            'academic_status', 'onboarding_status', 'portal_access_enabled',
            'metadata', 'created_by', 'updated_by', 'archived_at', 'created_at',
            'updated_at', 'deleted_at',
        ]));
    }

    public function test_learner_number_uniqueness_is_scoped_to_organization(): void
    {
        $organization = $this->organization('one');
        $this->insertProfile($organization->id, 'LRN-001');

        $this->expectException(QueryException::class);
        $this->insertProfile($organization->id, 'LRN-001');
    }

    public function test_same_learner_number_is_allowed_in_different_organizations(): void
    {
        $this->insertProfile($this->organization('one')->id, 'LRN-001');
        $this->insertProfile($this->organization('two')->id, 'LRN-001');

        $this->assertSame(2, DB::table('learner_profiles')->where('learner_number', 'LRN-001')->count());
    }

    public function test_uuid_is_unique(): void
    {
        $organization = $this->organization('one');
        $uuid = (string) Str::uuid();
        $this->insertProfile($organization->id, 'LRN-001', $uuid);

        $this->expectException(QueryException::class);
        $this->insertProfile($organization->id, 'LRN-002', $uuid);
    }

    public function test_nullable_user_and_membership_links_accept_null(): void
    {
        $organization = $this->organization('one');
        $this->insertProfile($organization->id, 'LRN-001');

        $this->assertDatabaseHas('learner_profiles', [
            'organization_id' => $organization->id,
            'user_id' => null,
            'organization_membership_id' => null,
        ]);
    }

    public function test_rollback_removes_the_table(): void
    {
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_07_14_000001_create_learner_profiles_table.php';

        $migration->down();
        $this->assertFalse(Schema::hasTable('learner_profiles'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('learner_profiles'));
    }

    private function organization(string $suffix): Organization
    {
        return Organization::create([
            'name' => "Organization {$suffix}",
            'code' => "organization-{$suffix}",
            'type' => 'school',
        ]);
    }

    private function insertProfile(string $organizationId, string $learnerNumber, ?string $uuid = null): void
    {
        DB::table('learner_profiles')->insert([
            'id' => (string) Str::uuid(),
            'uuid' => $uuid ?? (string) Str::uuid(),
            'organization_id' => $organizationId,
            'learner_number' => $learnerNumber,
            'first_name' => 'Test',
            'last_name' => 'Learner',
            'learner_status' => 'pending',
            'onboarding_status' => 'pending',
            'portal_access_enabled' => false,
        ]);
    }
}
