<?php

declare(strict_types=1);

namespace Semitexa\Api\Application\Service\Credential;

/** `90d`, `12h`, `30m` → when that lifetime ends; null for anything else. */
final class CredentialLifetime
{
    public static function expiresAt(string $lifetime, ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        if (preg_match('/^([1-9][0-9]{0,4})([dhm])$/', trim($lifetime), $m) !== 1) {
            return null;
        }
        $unit = ['d' => 'days', 'h' => 'hours', 'm' => 'minutes'][$m[2]];

        return ($now ?? new \DateTimeImmutable())->modify("+{$m[1]} {$unit}");
    }
}
