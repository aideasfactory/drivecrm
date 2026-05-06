<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Models\HmrcClientFingerprint;
use App\Models\HmrcDeviceIdentifier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BuildFraudPreventionHeadersAction
{
    /**
     * Compose the WEB_APP_VIA_SERVER fraud-prevention header set for an outbound
     * HMRC API call. See `.claude/hmrc-fraud-headers.md` for the source of truth.
     *
     * @param  array{ip?: ?string, port?: ?string, has_mfa?: bool, mfa_timestamp?: ?Carbon}  $context
     * @return array<string, string>
     */
    public function __invoke(User $user, HmrcClientFingerprint $fingerprint, array $context = []): array
    {
        $ip = $context['ip'] ?? null;
        $port = $context['port'] ?? null;
        $hasMfa = (bool) ($context['has_mfa'] ?? false);
        $mfaTimestamp = $context['mfa_timestamp'] ?? null;

        $headers = [
            'Gov-Client-Connection-Method' => (string) config('hmrc.fraud_headers.connection_method', 'WEB_APP_VIA_SERVER'),
            'Gov-Client-Device-ID' => $this->resolveDeviceId($user),
            'Gov-Client-User-IDs' => $this->govClientUserIds($user),
            'Gov-Client-Timezone' => $this->govClientTimezone($fingerprint),
            'Gov-Client-Screens' => $this->govClientScreens($fingerprint),
            'Gov-Client-Window-Size' => $this->govClientWindowSize($fingerprint),
            'Gov-Client-Browser-JS-User-Agent' => (string) $fingerprint->browser_user_agent,
        ];

        if (is_string($ip) && $this->looksPublic($ip)) {
            $headers['Gov-Client-Public-IP'] = $ip;
            $headers['Gov-Client-Public-IP-Timestamp'] = now()->format('Y-m-d\TH:i:s.v\Z');
        } elseif (is_string($ip)) {
            Log::warning('HMRC fraud header: client IP looks private; check TrustProxies', [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);
        }

        if (is_string($port) && $port !== '' && ctype_digit($port)) {
            $headers['Gov-Client-Public-Port'] = $port;
        }

        if ($hasMfa) {
            $headers['Gov-Client-Multi-Factor'] = $this->govClientMultiFactor($user, $mfaTimestamp);
        }

        $vendorPublicIp = (string) config('hmrc.fraud_headers.vendor_public_ip', '');
        if ($vendorPublicIp !== '') {
            $headers['Gov-Vendor-Public-IP'] = $vendorPublicIp;
        }

        $headers['Gov-Vendor-Forwarded'] = $this->govVendorForwarded($vendorPublicIp, $ip);
        $headers['Gov-Vendor-Product-Name'] = rawurlencode((string) config('hmrc.fraud_headers.vendor_product_name', 'Drive CRM'));
        $headers['Gov-Vendor-Version'] = $this->govVendorVersion();

        return $headers;
    }

    private function resolveDeviceId(User $user): string
    {
        $identifier = HmrcDeviceIdentifier::query()->where('user_id', $user->id)->first();

        if ($identifier !== null) {
            return (string) $identifier->device_id;
        }

        // Defensive: should never happen if the user has connected, but
        // mint one rather than throwing — fingerprint capture happens
        // server-side here, not on a callback path.
        return (string) HmrcDeviceIdentifier::forUser($user, null)->device_id;
    }

    private function govClientUserIds(User $user): string
    {
        $key = (string) config('hmrc.fraud_headers.user_id_key', 'drivecrm');

        return $key.'='.rawurlencode((string) $user->id);
    }

    private function govClientTimezone(HmrcClientFingerprint $fingerprint): string
    {
        $offsetMinutes = (int) ($fingerprint->timezone['offset_minutes'] ?? 0);
        $sign = $offsetMinutes >= 0 ? '+' : '-';
        $abs = abs($offsetMinutes);
        $hh = str_pad((string) intdiv($abs, 60), 2, '0', STR_PAD_LEFT);
        $mm = str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT);

        return "UTC{$sign}{$hh}:{$mm}";
    }

    private function govClientScreens(HmrcClientFingerprint $fingerprint): string
    {
        $screens = is_array($fingerprint->screens) ? $fingerprint->screens : [];

        $entries = [];
        foreach ($screens as $screen) {
            if (! is_array($screen)) {
                continue;
            }
            $entries[] = http_build_query([
                'width' => (int) ($screen['width'] ?? 0),
                'height' => (int) ($screen['height'] ?? 0),
                'scaling-factor' => (float) ($screen['scaling_factor'] ?? 1),
                'colour-depth' => (int) ($screen['colour_depth'] ?? 24),
            ]);
        }

        return implode(',', $entries);
    }

    private function govClientWindowSize(HmrcClientFingerprint $fingerprint): string
    {
        $size = is_array($fingerprint->window_size) ? $fingerprint->window_size : [];

        return http_build_query([
            'width' => (int) ($size['width'] ?? 0),
            'height' => (int) ($size['height'] ?? 0),
        ]);
    }

    private function govClientMultiFactor(User $user, ?Carbon $mfaTimestamp): string
    {
        $timestamp = ($mfaTimestamp ?? now())->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $reference = hash('sha256', 'mfa:'.$user->id.':'.$timestamp);

        return http_build_query([
            'type' => 'TOTP',
            'timestamp' => $timestamp,
            'unique-reference' => substr($reference, 0, 32),
        ]);
    }

    private function govVendorForwarded(string $vendorPublicIp, ?string $clientIp): string
    {
        $by = $vendorPublicIp !== '' ? $vendorPublicIp : 'unknown';
        $for = is_string($clientIp) && $this->looksPublic($clientIp) ? $clientIp : 'unknown';

        return 'by='.rawurlencode($by).'&for='.rawurlencode($for);
    }

    private function govVendorVersion(): string
    {
        $key = (string) config('hmrc.fraud_headers.user_id_key', 'drivecrm');
        $version = (string) config('hmrc.fraud_headers.vendor_version', '1.0.0');

        return $key.'='.rawurlencode($version);
    }

    private function looksPublic(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
