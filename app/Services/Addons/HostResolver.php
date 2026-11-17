<?php

namespace App\Services\Addons;

/**
 * The addresses (IPv4 and IPv6) a host name points to. A class of its own so that the tests can
 * replace the DNS.
 */
class HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        foreach (is_array($records) ? $records : [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }
        if ($ips === []) {
            $ips = gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($ips));
    }
}
