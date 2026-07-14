<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Support\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Learners\Application\LearnerNumberService;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_sequential_and_independent_per_organization(): void
    {
        $service = app(LearnerNumberService::class);
        $first = $this->organization('first');
        $second = $this->organization('second');

        $this->assertSame('LRN-000001', $service->next($first));
        $this->assertSame('LRN-000002', $service->next($first));
        $this->assertSame('LRN-000001', $service->next($second));

        $this->assertDatabaseHas('learner_number_sequences', ['organization_id' => $first->id, 'next_number' => 3]);
        $this->assertDatabaseHas('learner_number_sequences', ['organization_id' => $second->id, 'next_number' => 2]);
    }

    public function test_existing_numbers_are_skipped_without_using_profile_counts(): void
    {
        $organization = $this->organization('skip');
        LearnerProfile::factory()->create([
            'organization_id' => $organization->id,
            'learner_number' => 'LRN-000001',
        ]);

        $this->assertSame('LRN-000002', app(LearnerNumberService::class)->next($organization));
        $this->assertDatabaseHas('learner_number_sequences', ['organization_id' => $organization->id, 'next_number' => 3]);
    }

    public function test_prefix_academic_year_and_padding_are_configurable(): void
    {
        $organization = $this->organization('configured');
        $service = app(LearnerNumberService::class);

        $this->assertSame('STU-2026/27-0001', $service->next($organization, 'stu', '2026/27', 4));
        $this->assertSame('STU-2026/27-0002', $service->next($organization, 'STU', '2026/27', 4));
        $this->assertDatabaseHas('learner_number_sequences', [
            'organization_id' => $organization->id,
            'academic_year' => '2026/27',
            'prefix' => 'STU',
            'padding' => 4,
            'next_number' => 3,
        ]);
    }

    public function test_academic_year_sequences_are_independent_within_an_organization(): void
    {
        $organization = $this->organization('years');
        $service = app(LearnerNumberService::class);

        $this->assertSame('LRN-2026-000001', $service->next($organization, academicYear: '2026'));
        $this->assertSame('LRN-2027-000001', $service->next($organization, academicYear: '2027'));
    }

    public function test_manual_numbers_are_trimmed_and_duplicates_are_rejected_per_organization(): void
    {
        $service = app(LearnerNumberService::class);
        $organization = $this->organization('manual');
        $other = $this->organization('manual-other');
        LearnerProfile::factory()->create(['organization_id' => $organization->id, 'learner_number' => 'CUSTOM-1']);

        $this->assertSame('CUSTOM-1', $service->validateManual($other, ' CUSTOM-1 '));

        $this->expectException(DomainException::class);
        $service->validateManual($organization, 'CUSTOM-1');
    }

    private function organization(string $suffix): Organization
    {
        return Organization::create(['name' => "School {$suffix}", 'code' => "school-{$suffix}", 'type' => 'school']);
    }
}
