<?php

declare(strict_types=1);

namespace Modules\Learners\Tests\Feature;

use Core\Billing\Domain\Enums\InvoiceStatus;
use Core\Billing\Infrastructure\Models\Invoice;
use Core\Identity\Infrastructure\Models\Membership;
use Core\RBAC\Infrastructure\Models\Role;
use Core\Users\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Learners\Database\Seeders\LearnersPermissionSeeder;
use Modules\Learners\Infrastructure\Models\GuardianProfile;
use Modules\Learners\Infrastructure\Models\LearnerGuardianRelationship;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Tests\TestCase;

final class GuardianBillingPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_marked_as_a_financial_contact_can_see_and_pay_invoices(): void
    {
        Http::fake(['*/eng/query/validate' => Http::response('VALID', 200)]);
        [$organization, , $guardian] = $this->linkedGuardian('financial-yes', receivesFinancialCommunication: true);
        $invoice = $this->issuedInvoice($organization);

        $this->actingAs($guardian->user)->withSession(['organization_id' => $organization->id])
            ->get(route('guardians.invoices.index', $guardian->uuid))
            ->assertOk()
            ->assertSee('Pay now');

        $this->post(route('guardians.invoices.pay', ['guardian' => $guardian->uuid, 'invoice' => $invoice->id]))
            ->assertRedirect();
    }

    public function test_guardian_without_the_financial_communication_flag_is_forbidden(): void
    {
        [$organization, , $guardian] = $this->linkedGuardian('financial-no', receivesFinancialCommunication: false);

        $this->actingAs($guardian->user)->withSession(['organization_id' => $organization->id])
            ->get(route('guardians.invoices.index', $guardian->uuid))
            ->assertForbidden();
    }

    public function test_guardian_from_another_organization_cannot_reach_this_organizations_invoices(): void
    {
        [$organization, , $guardian] = $this->linkedGuardian('financial-org-a', receivesFinancialCommunication: true);
        [$otherOrganization] = $this->linkedGuardian('financial-org-b', receivesFinancialCommunication: true);

        // The guardian has no membership/relationship in $otherOrganization
        // at all, so organization-context resolution itself rejects it.
        $this->actingAs($guardian->user)->withSession(['organization_id' => $otherOrganization->id])
            ->get(route('guardians.invoices.index', $guardian->uuid))
            ->assertStatus(403);
    }

    public function test_guardian_cannot_pay_an_invoice_belonging_to_a_different_organization(): void
    {
        [$organization, , $guardian] = $this->linkedGuardian('financial-cross-org-a', receivesFinancialCommunication: true);
        [$otherOrganization] = $this->linkedGuardian('financial-cross-org-b', receivesFinancialCommunication: true);
        $foreignInvoice = $this->issuedInvoice($otherOrganization);

        $this->actingAs($guardian->user)->withSession(['organization_id' => $organization->id])
            ->post(route('guardians.invoices.pay', ['guardian' => $guardian->uuid, 'invoice' => $foreignInvoice->id]))
            ->assertNotFound();
    }

    /**
     * @return array{0: Organization, 1: LearnerProfile, 2: GuardianProfile}
     */
    private function linkedGuardian(string $code, bool $receivesFinancialCommunication): array
    {
        $this->seed(LearnersPermissionSeeder::class);
        $organization = Organization::query()->create(['name' => $code, 'code' => $code, 'type' => 'school']);
        OrganizationModule::query()->create(['organization_id' => $organization->id, 'module_name' => 'learners', 'enabled' => true]);
        $role = Role::query()->firstOrCreate(['name' => 'Guardian'], ['is_system' => false]);
        $guardianUser = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $guardianUser->id, 'role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        $learner = LearnerProfile::factory()->active()->create(['organization_id' => $organization->id]);
        $guardian = GuardianProfile::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $guardianUser->id,
            'first_name' => 'Test',
            'last_name' => 'Guardian',
            'email' => "{$code}@example.test",
            'status' => 'active',
        ]);
        LearnerGuardianRelationship::query()->create([
            'organization_id' => $organization->id,
            'learner_profile_id' => $learner->id,
            'guardian_profile_id' => $guardian->id,
            'relationship_type' => 'parent',
            'status' => 'active',
            'receives_academic_communication' => true,
            'receives_financial_communication' => $receivesFinancialCommunication,
        ]);

        return [$organization, $learner, $guardian];
    }

    private function issuedInvoice(Organization $organization): Invoice
    {
        return Invoice::query()->create([
            'organization_id' => $organization->id,
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->endOfMonth()->toDateString(),
            'status' => InvoiceStatus::Issued,
            'currency' => 'ZAR',
            'subtotal' => 1499.00,
            'total' => 1499.00,
            'due_date' => now()->addDays(7)->toDateString(),
            'issued_at' => now(),
        ]);
    }
}
