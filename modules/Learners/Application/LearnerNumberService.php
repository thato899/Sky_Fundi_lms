<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\Support\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Learners\Infrastructure\Models\LearnerNumberSequence;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerNumberService
{
    public function next(
        Organization $organization,
        string $prefix = 'LRN',
        ?string $academicYear = null,
        int $padding = 6,
    ): string {
        $prefix = $this->validatedPrefix($prefix);
        $academicYear = $this->validatedAcademicYear($academicYear);
        if ($padding < 1 || $padding > 12) {
            throw new DomainException('Learner number padding must be between 1 and 12.');
        }

        return DB::transaction(function () use ($organization, $prefix, $academicYear, $padding): string {
            $organizationId = (string) $organization->getKey();
            LearnerNumberSequence::query()->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'academic_year' => $academicYear,
                'prefix' => $prefix,
                'padding' => $padding,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var LearnerNumberSequence $sequence */
            $sequence = LearnerNumberSequence::query()
                ->where('organization_id', $organizationId)
                ->where('academic_year', $academicYear)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->forceFill(['prefix' => $prefix, 'padding' => $padding]);
            $nextNumber = $sequence->getAttribute('next_number');
            assert(is_int($nextNumber));

            do {
                $learnerNumber = $this->format($prefix, $academicYear, $padding, $nextNumber);
                $nextNumber++;
            } while (DB::table((new LearnerProfile)->getTable())
                ->where('organization_id', $organizationId)
                ->where('learner_number', $learnerNumber)
                ->exists());

            $sequence->setAttribute('next_number', $nextNumber)->save();

            return $learnerNumber;
        }, 3);
    }

    public function validateManual(Organization $organization, string $learnerNumber): string
    {
        $learnerNumber = trim($learnerNumber);
        if ($learnerNumber === '' || mb_strlen($learnerNumber) > 255) {
            throw new DomainException('A manual learner number must contain between 1 and 255 characters.');
        }

        if (DB::table((new LearnerProfile)->getTable())
            ->where('organization_id', $organization->getKey())
            ->where('learner_number', $learnerNumber)
            ->exists()) {
            throw new DomainException('The learner number is already in use by this organization.');
        }

        return $learnerNumber;
    }

    private function validatedPrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        if (preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $prefix) !== 1 || mb_strlen($prefix) > 32) {
            throw new DomainException('Learner number prefixes must contain only letters, numbers, and single hyphens.');
        }

        return $prefix;
    }

    private function validatedAcademicYear(?string $academicYear): string
    {
        $academicYear = trim((string) $academicYear);
        if ($academicYear !== '' && (preg_match('/^[A-Za-z0-9]+(?:[-_\/][A-Za-z0-9]+)*$/', $academicYear) !== 1 || mb_strlen($academicYear) > 32)) {
            throw new DomainException('Academic year segments must contain only letters, numbers, hyphens, underscores, or slashes.');
        }

        return $academicYear;
    }

    private function format(string $prefix, string $academicYear, int $padding, int $number): string
    {
        $segments = array_filter([$prefix, $academicYear], fn (string $segment): bool => $segment !== '');

        return implode('-', [...$segments, str_pad((string) $number, $padding, '0', STR_PAD_LEFT)]);
    }
}
