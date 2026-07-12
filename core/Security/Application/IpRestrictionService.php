<?php

declare(strict_types=1);

namespace Core\Security\Application;

use Core\Security\Domain\Enums\IpRestrictionType;
use Core\Security\Infrastructure\Models\IpRestriction;
use Illuminate\Support\Facades\Cache;

/**
 * Resolution rule, checked in this order:
 * 1. Any active `deny` entry matching the IP for this scope -> blocked.
 * 2. If at least one active `allow` entry exists for this scope, the
 *    IP must match one of them -> otherwise blocked (allowlist mode).
 * 3. No allow entries defined for this scope -> allowed by default.
 * See core/Security/README.md.
 */
final class IpRestrictionService
{
    private const CACHE_TTL_SECONDS = 60;

    public function isAllowed(string $ip, string $scopeType = 'platform', ?string $scopeId = null): bool
    {
        $restrictions = $this->restrictionsFor($scopeType, $scopeId);

        $denyList = $restrictions->where('type', IpRestrictionType::Deny);
        $allowList = $restrictions->where('type', IpRestrictionType::Allow);

        foreach ($denyList as $restriction) {
            if ($this->matches($ip, $restriction->ip_cidr)) {
                return false;
            }
        }

        if ($allowList->isEmpty()) {
            return true;
        }

        foreach ($allowList as $restriction) {
            if ($this->matches($ip, $restriction->ip_cidr)) {
                return true;
            }
        }

        return false;
    }

    public function add(IpRestrictionType $type, string $ipCidr, string $scopeType = 'platform', ?string $scopeId = null, ?string $description = null): IpRestriction
    {
        $restriction = IpRestriction::create([
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'type' => $type,
            'ip_cidr' => $ipCidr,
            'description' => $description,
            'is_active' => true,
        ]);

        $this->forgetCache($scopeType, $scopeId);

        return $restriction;
    }

    public function remove(IpRestriction $restriction): void
    {
        $this->forgetCache($restriction->scope_type, $restriction->scope_id);

        $restriction->delete();
    }

    private function restrictionsFor(string $scopeType, ?string $scopeId): \Illuminate\Support\Collection
    {
        return Cache::remember(
            "ip-restrictions:{$scopeType}:{$scopeId}",
            self::CACHE_TTL_SECONDS,
            fn () => IpRestriction::query()
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->where('is_active', true)
                ->get(),
        );
    }

    private function forgetCache(string $scopeType, ?string $scopeId): void
    {
        Cache::forget("ip-restrictions:{$scopeType}:{$scopeId}");
    }

    /**
     * Supports a single IP or CIDR notation (e.g. "10.0.0.0/8"). IPv4
     * only for now — IPv6 CIDR matching is future work.
     */
    private function matches(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
