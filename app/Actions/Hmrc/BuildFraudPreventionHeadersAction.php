<?php

declare(strict_types=1);

namespace App\Actions\Hmrc;

use App\Models\HmrcClientFingerprint;
use App\Models\HmrcDeviceIdentifier;
use App\Models\User;
use App\Support\Hmrc\FraudHeaderEncoder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BuildFraudPreventionHeadersAction
{
    /**
     * WEB_APP_VIA_SERVER fraud-prevention headers for an outbound HMRC call.
     *
     * Required set: https://developer.service.hmrc.gov.uk/guides/fraud-prevention/connection-method/web-app-via-server/
     * Gov-Client-Browser-Plugins, Gov-Client-Local-IPs and Gov-Client-Browser-Do-Not-Track
     * are not required for this connection method and are not sent.
     * Gov-Vendor-License-IDs is sent only when a license key is configured,
     * because this web app has no license installed on the originating device.
     *
     * @param  array{
     *     ip?: ?string,
     *     port?: ?string,
     *     ip_collected_at?: ?Carbon,
     *     has_mfa?: bool,
     *     mfa_timestamp?: ?Carbon,
     *     mfa?: ?array{type?: string, timestamp?: string, unique_reference?: string},
     * }  $context
     * @return array<string, string>
     */
    public function __invoke(User $user, HmrcClientFingerprint $fingerprint, array $context = []): array
    {
        $headers = [
            'Gov-Client-Connection-Method' => (string) config('hmrc.fraud_headers.connection_method', 'WEB_APP_VIA_SERVER'),
            'Gov-Client-Device-ID' => $this->resolveDeviceId($user),
            'Gov-Client-User-IDs' => $this->govClientUserIds($user),
            'Gov-Client-Timezone' => $this->govClientTimezone($fingerprint),
            'Gov-Vendor-Product-Name' => rawurlencode((string) config('hmrc.fraud_headers.vendor_product_name', 'Drive CRM')),
            'Gov-Vendor-Version' => $this->govVendorVersion(),
        ];

        $userAgent = $this->browserUserAgent($fingerprint);

        if ($userAgent !== null) {
            $headers['Gov-Client-Browser-JS-User-Agent'] = $userAgent;
        }

        $screens = $this->govClientScreens($fingerprint);

        if ($screens !== null) {
            $headers['Gov-Client-Screens'] = $screens;
        }

        $windowSize = $this->govClientWindowSize($fingerprint);

        if ($windowSize !== null) {
            $headers['Gov-Client-Window-Size'] = $windowSize;
        }

        $multiFactor = $this->govClientMultiFactor($user, $context);

        if ($multiFactor !== null) {
            $headers['Gov-Client-Multi-Factor'] = $multiFactor;
        }

        $clientIp = $this->publicIp(is_string($context['ip'] ?? null) ? $context['ip'] : null);

        if ($clientIp !== null) {
            $headers['Gov-Client-Public-IP'] = $clientIp;
            $headers['Gov-Client-Public-IP-Timestamp'] = $this->publicIpTimestamp($context);
        } elseif (is_string($context['ip'] ?? null) && $context['ip'] !== '') {
            Log::warning('HMRC fraud header: client IP is not a public address; check trusted proxies', [
                'user_id' => $user->id,
            ]);
        }

        $port = $this->clientPort($context['port'] ?? null);

        if ($port !== null) {
            $headers['Gov-Client-Public-Port'] = $port;
        } elseif ($clientIp !== null) {
            Log::warning('HMRC fraud header: client public port unavailable. Set HMRC_CLIENT_PUBLIC_PORT_HEADER if a proxy terminates the connection.', [
                'user_id' => $user->id,
            ]);
        }

        $vendorIp = $this->vendorPublicIp();

        if ($vendorIp !== null) {
            $headers['Gov-Vendor-Public-IP'] = $vendorIp;
        } else {
            Log::warning('HMRC fraud header: HMRC_VENDOR_PUBLIC_IP is missing or not a public address', [
                'user_id' => $user->id,
            ]);
        }

        if ($clientIp !== null && $vendorIp !== null) {
            $headers['Gov-Vendor-Forwarded'] = FraudHeaderEncoder::keyValueList([
                'by' => $vendorIp,
                'for' => $clientIp,
            ]);
        }

        $licenseIds = $this->govVendorLicenseIds($user);

        if ($licenseIds !== null) {
            $headers['Gov-Vendor-License-IDs'] = $licenseIds;
        }

        return $headers;
    }

    private function resolveDeviceId(User $user): string
    {
        $identifier = HmrcDeviceIdentifier::query()->where('user_id', $user->id)->first();

        if ($identifier !== null && Str::isUuid((string) $identifier->device_id)) {
            return (string) $identifier->device_id;
        }

        return (string) HmrcDeviceIdentifier::forUser($user, null)->device_id;
    }

    private function browserUserAgent(HmrcClientFingerprint $fingerprint): ?string
    {
        $agent = preg_replace('/[\x00-\x1F\x7F]/', '', (string) $fingerprint->browser_user_agent) ?? '';
        $agent = trim($agent);

        if ($agent === '') {
            return null;
        }

        if (preg_match('/[^\x20-\x7E]/', $agent) === 1) {
            return rawurlencode($agent);
        }

        return $agent;
    }

    private function govClientUserIds(User $user): string
    {
        $key = (string) config('hmrc.fraud_headers.user_id_key', 'drivecrm');
        $pairs = [];
        $email = trim((string) $user->email);

        if ($email !== '') {
            $pairs[$key] = $email;
        }

        $pairs[$key.'-id'] = (string) $user->id;

        return FraudHeaderEncoder::keyValueList($pairs);
    }

    private function govClientTimezone(HmrcClientFingerprint $fingerprint): string
    {
        $offsetMinutes = (int) ($fingerprint->timezone['offset_minutes'] ?? 0);
        $sign = $offsetMinutes >= 0 ? '+' : '-';
        $absolute = abs($offsetMinutes);
        $hours = str_pad((string) intdiv($absolute, 60), 2, '0', STR_PAD_LEFT);
        $minutes = str_pad((string) ($absolute % 60), 2, '0', STR_PAD_LEFT);

        return "UTC{$sign}{$hours}:{$minutes}";
    }

    private function govClientScreens(HmrcClientFingerprint $fingerprint): ?string
    {
        $screens = is_array($fingerprint->screens) ? $fingerprint->screens : [];
        $structures = [];

        foreach ($screens as $screen) {
            if (! is_array($screen)) {
                continue;
            }

            $width = $this->positiveInt($screen['width'] ?? null);
            $height = $this->positiveInt($screen['height'] ?? null);
            $colourDepth = $this->positiveInt($screen['colour_depth'] ?? null);

            if ($width === null || $height === null || $colourDepth === null) {
                continue;
            }

            $structures[] = [
                'width' => $width,
                'height' => $height,
                'scaling-factor' => $this->formatScalingFactor($screen['scaling_factor'] ?? 1),
                'colour-depth' => $colourDepth,
            ];
        }

        if ($structures === []) {
            return null;
        }

        return FraudHeaderEncoder::structureList($structures);
    }

    private function govClientWindowSize(HmrcClientFingerprint $fingerprint): ?string
    {
        $size = is_array($fingerprint->window_size) ? $fingerprint->window_size : [];
        $width = $this->positiveInt($size['width'] ?? null);
        $height = $this->positiveInt($size['height'] ?? null);

        if ($width === null || $height === null) {
            return null;
        }

        return FraudHeaderEncoder::keyValueList([
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * @param  array{
     *     has_mfa?: bool,
     *     mfa_timestamp?: ?Carbon,
     *     mfa?: ?array{type?: string, timestamp?: string, unique_reference?: string},
     * }  $context
     */
    private function govClientMultiFactor(User $user, array $context): ?string
    {
        $mfa = $context['mfa'] ?? null;

        if (is_array($mfa)) {
            $type = $mfa['type'] ?? null;
            $timestamp = $mfa['timestamp'] ?? null;
            $reference = $mfa['unique_reference'] ?? null;

            if (is_string($type) && in_array($type, ['TOTP', 'AUTH_CODE', 'OTHER'], true)
                && is_string($timestamp) && $timestamp !== ''
                && is_string($reference) && $reference !== '') {
                return FraudHeaderEncoder::keyValueList([
                    'type' => $type,
                    'timestamp' => $timestamp,
                    'unique-reference' => $reference,
                ]);
            }
        }

        if (! (bool) ($context['has_mfa'] ?? false)) {
            return null;
        }

        $mfaTimestamp = $context['mfa_timestamp'] ?? null;
        $timestamp = ($mfaTimestamp instanceof Carbon ? $mfaTimestamp : now())
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
        $productKey = (string) config('hmrc.fraud_headers.user_id_key', 'drivecrm');

        return FraudHeaderEncoder::keyValueList([
            'type' => 'TOTP',
            'timestamp' => $timestamp,
            'unique-reference' => hash('sha256', $productKey.':TOTP:'.$user->id),
        ]);
    }

    /**
     * @param  array{ip_collected_at?: ?Carbon}  $context
     */
    private function publicIpTimestamp(array $context): string
    {
        $collectedAt = $context['ip_collected_at'] ?? null;
        $moment = $collectedAt instanceof Carbon ? $collectedAt->copy() : now();

        return $moment->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    private function clientPort(mixed $port): ?string
    {
        if (is_int($port)) {
            $port = (string) $port;
        }

        if (! is_string($port) || ! ctype_digit($port)) {
            return null;
        }

        $value = (int) $port;

        if ($value < 1 || $value > 65535 || $value === 80 || $value === 443) {
            return null;
        }

        return (string) $value;
    }

    private function vendorPublicIp(): ?string
    {
        $configured = config('hmrc.fraud_headers.vendor_public_ip');

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        return $this->publicIp($configured);
    }

    private function govVendorVersion(): string
    {
        $key = (string) config('hmrc.fraud_headers.vendor_version_key', config('hmrc.fraud_headers.user_id_key', 'drivecrm'));
        $version = (string) config('hmrc.fraud_headers.vendor_version', '1.0.0');

        return FraudHeaderEncoder::keyValueList([
            $key => $version,
        ]);
    }

    /**
     * Hashed license key. Omitted when the product has no license to report.
     */
    private function govVendorLicenseIds(User $user): ?string
    {
        $licenseKey = config('hmrc.fraud_headers.vendor_license_key');

        if (! is_string($licenseKey) || $licenseKey === '') {
            return null;
        }

        $productKey = (string) config('hmrc.fraud_headers.user_id_key', 'drivecrm');
        $hash = hash('sha256', $productKey.':license:'.$user->id.':'.$licenseKey);

        return FraudHeaderEncoder::keyValueList([
            $productKey => $hash,
        ]);
    }

    private function publicIp(?string $ip): ?string
    {
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        return $valid === false ? null : $ip;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= 1 ? $int : null;
    }

    private function formatScalingFactor(mixed $factor): string
    {
        $value = is_numeric($factor) ? (float) $factor : 1.0;

        if ($value <= 0) {
            $value = 1.0;
        }

        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '1' : $formatted;
    }
}
