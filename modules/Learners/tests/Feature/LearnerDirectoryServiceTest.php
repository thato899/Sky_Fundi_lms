<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Learners\Application\LearnerDirectoryService;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class LearnerDirectoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_is_scoped_searchable_filterable_sorted_and_paginated(): void
    {
        $organization = $this->organization('directory-a');
        $other = $this->organization('directory-b');
        LearnerProfile::factory()->create(['organization_id' => $organization->id, 'learner_number' => 'LRN-002', 'first_name' => 'Zanele', 'admission_number' => 'ADM-X']);
        LearnerProfile::factory()->active()->create(['organization_id' => $organization->id, 'learner_number' => 'LRN-001', 'first_name' => 'Amara', 'portal_access_enabled' => true]);
        LearnerProfile::factory()->archived()->create(['organization_id' => $organization->id, 'learner_number' => 'LRN-003']);
        LearnerProfile::factory()->create(['organization_id' => $other->id, 'learner_number' => 'LRN-000', 'first_name' => 'Zanele']);

        $service = app(LearnerDirectoryService::class);
        $search = $service->paginate($organization, ['search' => 'Zanele', 'per_page' => 10]);
        $active = $service->paginate($organization, ['learner_status' => 'active', 'portal_access_enabled' => true]);
        $visible = $service->paginate($organization, ['archived' => false, 'sort' => 'learner_number', 'direction' => 'asc', 'per_page' => 1]);

        $this->assertSame(1, $search->total());
        $this->assertSame('LRN-002', $search->items()[0]->learner_number);
        $this->assertSame(1, $active->total());
        $this->assertSame(2, $visible->total());
        $this->assertSame(1, $visible->perPage());
        $this->assertSame('LRN-001', $visible->items()[0]->learner_number);
        $this->assertTrue($visible->items()[0]->relationLoaded('currentGrade'));
    }

    private function organization(string $code): Organization
    {
        return Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
    }
}
