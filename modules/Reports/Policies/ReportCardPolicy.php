<?php

declare(strict_types=1);

namespace Modules\Reports\Policies;

use Core\Identity\Application\PermissionResolver;
use Core\Identity\Infrastructure\Models\Membership;
use Core\Users\Infrastructure\Models\User;
use Modules\Reports\Domain\Enums\ReportCardStatus;
use Modules\Reports\Infrastructure\Models\ReportCard;

final class ReportCardPolicy
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function viewAny(User $u): bool
    {
        return $this->allows($u, 'reports.view');
    }

    public function view(User $u, ReportCard $c): bool
    {
        return $this->allows($u, 'reports.view', $c);
    }

    public function generate(User $u): bool
    {
        return $this->allows($u, 'reports.generate');
    }

    public function update(User $u, ReportCard $c): bool
    {
        return ! $this->immutable($c) && $this->allows($u, 'reports.update', $c);
    }

    public function review(User $u, ReportCard $c): bool
    {
        return $c->status === ReportCardStatus::Generated && $this->allows($u, 'reports.review', $c);
    }

    public function approve(User $u, ReportCard $c): bool
    {
        return $c->status === ReportCardStatus::UnderReview && $this->allows($u, 'reports.approve', $c);
    }

    public function publish(User $u, ReportCard $c): bool
    {
        return $c->status === ReportCardStatus::Approved && $this->allows($u, 'reports.publish', $c);
    }

    public function withdraw(User $u, ReportCard $c): bool
    {
        return $c->status === ReportCardStatus::Published && $this->allows($u, 'reports.withdraw', $c);
    }

    public function exportPdf(User $u, ReportCard $c): bool
    {
        return $this->allows($u, 'reports.export_pdf', $c);
    }

    public function exportCsv(User $u): bool
    {
        return $this->allows($u, 'reports.export_csv');
    }

    public function manageComments(User $u, ReportCard $c): bool
    {
        return ! $this->immutable($c) && $this->allows($u, 'reports.manage_comments', $c);
    }

    private function immutable(ReportCard $c): bool
    {
        return in_array($c->status, [ReportCardStatus::Published, ReportCardStatus::Withdrawn], true);
    }

    private function allows(User $u, string $permission, ?ReportCard $c = null): bool
    {
        $m = request()->attributes->get('organization_membership');

        return $m instanceof Membership && $m->getAttribute('user_id') === $u->getKey() && (! $c || $c->organization_id === $m->getAttribute('organization_id')) && $this->permissions->allows($m, $permission);
    }
}
