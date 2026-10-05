<?php

namespace Ernestdefoe\Connect\Http;

/**
 * Guards every outbound call Connect makes to a URL someone else chose (webhook
 * targets, the rules engine's "call a webhook" action).
 *
 * The URL must be https and its host must resolve only to public addresses, so
 * the forum's queue worker cannot be pointed at itself, the private network or a
 * cloud metadata service. The check runs when a hook is subscribed AND again
 * before every delivery (DNS can change in between), the connection is pinned to
 * the address that was checked, and redirects are never followed.
 */
final class SafeUrl
{
    /**
     * Guzzle options for calling $url safely, or null when it must not be called.
     */
    public static function options(string $url): ?array
    {
        $target = self::check($url);
        if ($target === null) {
            return null;
        }

        $options = ['allow_redirects' => false];

        // Pin the connection to the address we just checked, so a second DNS
        // answer cannot swap in a private one (curl handler only).
        if (! $target['literal'] && defined('CURLOPT_RESOLVE')) {
            $ip = $target['ips'][0];
            $options['curl'] = [CURLOPT_RESOLVE => [sprintf(
                '%s:%d:%s',
                $target['host'],
                $target['port'],
                str_contains($ip, ':') ? '[' . $ip . ']' : $ip
            )]];
        }

        return $options;
    }

    /** Whether $url may be called at all (https, public addresses only). */
    public static function allowed(string $url): bool
    {
        return self::check($url) !== null;
    }

    /** @return array{host: string, port: int, ips: string[], literal: bool}|null */
    private static function check(string $url): ?array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        $host    = strtolower(trim($parts['host'], '[]'));
        $port    = (int) ($parts['port'] ?? 443);
        $literal = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $ips     = $literal ? [$host] : self::resolve($host);

        if (! $ips) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ips' => $ips, 'literal' => $literal];
    }

    /** Every address the host resolves to: the system resolver (hosts file included) plus IPv6. */
    private static function resolve(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isPublic(string $ip): bool
    {
        // Private, loopback, link-local, CGNAT, reserved and documentation ranges,
        // IPv4-mapped IPv6 included.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $bin = inet_pton($ip);

        if (strlen($bin) === 4) {
            return ord($bin[0]) < 224; // multicast and above
        }

        // IPv6 multicast, and NAT64 (64:ff9b::/96), which can reach IPv4 loopback.
        return $bin[0] !== "\xff" && substr($bin, 0, 12) !== "\x00\x64\xff\x9b" . str_repeat("\x00", 8);
    }
}
