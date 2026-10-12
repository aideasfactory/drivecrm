<?php

declare(strict_types=1);

namespace App\Support\Hmrc;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Per-request facts the WEB_APP_VIA_SERVER fraud headers cannot collect in the browser.
 */
final class FraudRequestContext
{
    /**
     * @return array{
     *     ip: ?string,
     *     port: ?string,
     *     ip_collected_at: Carbon,
     *     has_mfa: bool,
     *     mfa: ?array{type: string, timestamp: string, unique_reference: string},
     * }
     */
    public static function fromRequest(Request $request): array
    {
        $ip = $request->ip();
        $mfa = self::multiFactor($request);

        return [
            'ip' => is_string($ip) && $ip !== '' ? $ip : null,
            'port' => self::clientPublicPort($request),
            'ip_collected_at' => now(),
            'has_mfa' => $mfa !== null,
            'mfa' => $mfa,
        ];
    }

    /**
     * @return ?array{type: string, timestamp: string, unique_reference: string}
     */
    private static function multiFactor(Request $request): ?array
    {
        $stored = $request->session()->get('hmrc.mfa');

        if (! is_array($stored)) {
            return null;
        }

        $type = $stored['type'] ?? null;
        $timestamp = $stored['timestamp'] ?? null;
        $reference = $stored['unique_reference'] ?? null;

        if (! is_string($type) || ! in_array($type, ['TOTP', 'AUTH_CODE', 'OTHER'], true)) {
            return null;
        }

        if (! is_string($timestamp) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,3})?)?Z$/', $timestamp) !== 1) {
            return null;
        }

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return [
            'type' => $type,
            'timestamp' => $timestamp,
            'unique_reference' => $reference,
        ];
    }

    /**
     * Originating device TCP source port.
     *
     * Behind a proxy, REMOTE_PORT is the proxy's port, not the client's.
     * Use it only when the TCP peer is the client. Otherwise honour the
     * configured edge header (load balancers that can see the client port).
     * Ports 80 and 443 are server ports and must not be sent.
     */
    private static function clientPublicPort(Request $request): ?string
    {
        $headerName = (string) config('hmrc.fraud_headers.client_public_port_header', '');

        if ($headerName !== '') {
            $fromHeader = $request->headers->get($headerName);

            if (is_string($fromHeader) && self::isClientSourcePort($fromHeader)) {
                return $fromHeader;
            }
        }

        $remoteAddress = $request->server('REMOTE_ADDR');
        $clientIp = $request->ip();

        if (! is_string($remoteAddress) || ! is_string($clientIp) || $remoteAddress !== $clientIp) {
            return null;
        }

        $remotePort = $request->server('REMOTE_PORT');

        if (is_int($remotePort)) {
            $remotePort = (string) $remotePort;
        }

        if (! is_string($remotePort) || ! self::isClientSourcePort($remotePort)) {
            return null;
        }

        return $remotePort;
    }

    private static function isClientSourcePort(string $port): bool
    {
        if (! ctype_digit($port)) {
            return false;
        }

        $value = (int) $port;

        if ($value < 1 || $value > 65535) {
            return false;
        }

        return $value !== 80 && $value !== 443;
    }
}
