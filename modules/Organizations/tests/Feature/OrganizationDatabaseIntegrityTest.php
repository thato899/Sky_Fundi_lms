<?php

declare(strict_types=1);

namespace Modules\Organizations\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Organizations\Infrastructure\Models\Organization;
use Tests\TestCase;

final class OrganizationDatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_code_and_owned_configuration_keys_are_unique(): void
    {
        $organization = $this->organization('unique');
        $other = $this->organization('other');

        $this->expectConstraintViolation(fn () => $this->organization('unique'));
        DB::table('organization_settings')->insert([
            'id' => fake()->uuid(),
            'organization_id' => $organization->id,
            'group' => 'general',
            'key' => 'timezone',
            'value' => json_encode('UTC', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->expectConstraintViolation(fn () => DB::table('organization_settings')->insert([
            'id' => fake()->uuid(),
            'organization_id' => $organization->id,
            'group' => 'general',
            'key' => 'timezone',
            'value' => json_encode('Africa/Johannesburg', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        DB::table('organization_modules')->insert([
            'id' => fake()->uuid(),
            'organization_id' => $organization->id,
            'module_name' => 'academics',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->expectConstraintViolation(fn () => DB::table('organization_modules')->insert([
            'id' => fake()->uuid(),
            'organization_id' => $organization->id,
            'module_name' => 'academics',
            'enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        DB::table('organization_ai_configurations')->insert([
            'id' => fake()->uuid(),
            'organization_id' => $organization->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->expectConstraintViolation(fn () => DB::table('organization_ai_configurations')->insert([
            'id' => fake()->uuid(),
            'organization_id' => $organization->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->assertDatabaseCount('organizations', 2);
        $this->assertDatabaseHas('organizations', ['id' => $other->id, 'code' => 'other']);
    }

    public function test_owned_configuration_rejects_unknown_organizations_without_partial_rows(): void
    {
        $unknown = fake()->uuid();

        foreach (['organization_settings', 'organization_modules', 'organization_ai_configurations'] as $table) {
            $values = [
                'id' => fake()->uuid(),
                'organization_id' => $unknown,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($table === 'organization_settings') {
                $values += ['group' => 'general', 'key' => 'timezone'];
            }
            if ($table === 'organization_modules') {
                $values += ['module_name' => 'academics', 'enabled' => true];
            }

            $this->expectConstraintViolation(fn () => DB::table($table)->insert($values));
            $this->assertDatabaseMissing($table, ['organization_id' => $unknown]);
        }
    }

    private function expectConstraintViolation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected the database constraint to reject the write.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function organization(string $code): Organization
    {
        return Organization::query()->create([
            'name' => ucfirst($code).' Organization',
            'code' => $code,
            'type' => 'school',
            'status' => 'active',
        ]);
    }
}
