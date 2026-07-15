<?php

declare(strict_types=1);

namespace Modules\Academics\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Application\AcademicYearService;
use Modules\Academics\Domain\Enums\AcademicYearStatus;
use Modules\Academics\Infrastructure\Models\AcademicYear;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class AcademicYearServiceTest extends TestCase
{
    use RefreshDatabase;

    private function organizationId(): string
    {
        return (string) Organization::create(['name' => 'School', 'code' => 'school-'.uniqid(), 'type' => 'school'])->id;
    }

    public function test_at_most_one_academic_year_is_current_at_a_time(): void
    {
        $service = $this->app->make(AcademicYearService::class);

        $organizationId = $this->organizationId();
        $yearOne = $service->create(['organization_id' => $organizationId, 'name' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-01']);
        $yearTwo = $service->create(['organization_id' => $organizationId, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);

        $service->setCurrent($yearOne);
        $this->assertTrue($yearOne->fresh()->is_current);

        $service->setCurrent($yearTwo);

        $this->assertFalse($yearOne->fresh()->is_current);
        $this->assertTrue($yearTwo->fresh()->is_current);
        $this->assertSame(1, AcademicYear::query()->where('is_current', true)->count());
    }

    public function test_a_newly_created_year_defaults_to_upcoming_and_not_current(): void
    {
        $service = $this->app->make(AcademicYearService::class);

        $year = $service->create(['organization_id' => $this->organizationId(), 'name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-01']);

        $this->assertSame(AcademicYearStatus::Upcoming, $year->status);
        $this->assertFalse($year->is_current);
    }

    public function test_closing_the_current_year_clears_its_current_flag(): void
    {
        $service = $this->app->make(AcademicYearService::class);
        $year = $service->create(['organization_id' => $this->organizationId(), 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-01']);
        $service->setCurrent($year);

        $closed = $service->close($year);

        $this->assertSame(AcademicYearStatus::Closed, $closed->status);
        $this->assertFalse($closed->is_current);
        $this->assertNull($service->current());
    }
}
