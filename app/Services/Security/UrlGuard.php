<?php

namespace App\Services\Security;

use App\Exceptions\UnsafeUrlException;

/**
 * SSRF defence for merchant-supplied webhook URLs.
 * Resolves A + AAAA, rejects private/reserved ranges, returns pinned IPv4 or IPv6.
 */
class UrlGuard
{
    /** @return array{host:string,port:int,ip:string,ips:list<string>} */
    public function resolve(string $url): array
    {
        $p = parse_url($url);
        $lax = config('luvupay.webhooks.allow_insecure_targets') && app()->environment('local', 'testing');

        if (! $p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            throw new UnsafeUrlException('Malformed webhook URL.');
        }
        $scheme = strtolower($p['scheme'] ?? '');
        if ($scheme !== 'https' && ! ($lax && $scheme === 'http')) {
            throw new UnsafeUrlException('Webhook URL must use https.');
        }
        $host = strtolower($p['host']);
        // Strip brackets from IPv6 literals
        $host = trim($host, '[]');
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));

        $ips = $this->resolveAll($host);
        if ($ips === []) {
            throw new UnsafeUrlException("Cannot resolve {$host}.");
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip) && ! $lax) {
                throw new UnsafeUrlException("{$host} resolves to a non-public address ({$ip}).");
            }
        }

        // Prefer IPv4 for pin when both exist (wider client support); else first public IP
        $pin = $ips[0];
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $pin = $ip;
                break;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $pin, 'ips' => $ips];
    }

    /** @return list<string> */
    private function resolveAll(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        // A records
        $a = @gethostbynamel($host) ?: [];
        foreach ($a as $ip) {
            $ips[] = $ip;
        }
        // AAAA via dns_get_record when available
        if (function_exists('dns_get_record')) {
            try {
                $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
                foreach ($aaaa as $row) {
                    if (! empty($row['ipv6'])) {
                        $ips[] = $row['ipv6'];
                    }
                }
            } catch (\Throwable $e) {
                // ignore DNS errors; A records may still be enough
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // NO_PRIV_RANGE / NO_RES_RANGE apply to IPv6 in PHP as well
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return false;
    }
}
