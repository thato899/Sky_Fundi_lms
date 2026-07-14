<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LearnerMilestoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequence_and_status_history_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('learner_number_sequences', [
            'id', 'organization_id', 'academic_year', 'prefix', 'padding', 'next_number', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('learner_status_histories', [
            'id', 'organization_id', 'learner_profile_id', 'previous_status', 'new_status', 'actor_id', 'reason', 'changed_at',
        ]));
    }

    public function test_migrations_roll_back_and_forward_in_dependency_order(): void
    {
        $history = require dirname(__DIR__, 2).'/database/migrations/2026_07_14_000003_create_learner_status_histories_table.php';
        $sequence = require dirname(__DIR__, 2).'/database/migrations/2026_07_14_000002_create_learner_number_sequences_table.php';

        $history->down();
        $sequence->down();
        $this->assertFalse(Schema::hasTable('learner_status_histories'));
        $this->assertFalse(Schema::hasTable('learner_number_sequences'));

        $sequence->up();
        $history->up();
        $this->assertTrue(Schema::hasTable('learner_number_sequences'));
        $this->assertTrue(Schema::hasTable('learner_status_histories'));
    }
}
