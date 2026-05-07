/**
 * Capture the WEB_APP_VIA_SERVER device fingerprint for HMRC fraud-prevention
 * headers. Shape mirrors the validation rules in StoreHmrcFingerprintRequest
 * and the column types on `hmrc_client_fingerprints`.
 *
 * See `.claude/hmrc-fraud-headers.md` for the per-field source of truth.
 */
export interface HmrcScreen {
    width: number;
    height: number;
    scaling_factor: number;
    colour_depth: number;
}

export interface HmrcWindowSize {
    width: number;
    height: number;
}

export interface HmrcTimezone {
    iana: string;
    /**
     * Offset in minutes east of UTC (positive ahead of UTC, negative behind).
     * The server formats this as `UTC±hh:mm` for `Gov-Client-Timezone`.
     * Note the sign-flip from JS's `getTimezoneOffset()`, which returns the
     * inverse convention.
     */
    offset_minutes: number;
}

export interface HmrcFingerprint {
    screens: HmrcScreen[];
    window_size: HmrcWindowSize;
    timezone: HmrcTimezone;
    browser_user_agent: string;
}

export const captureHmrcFingerprint = (): HmrcFingerprint => {
    const screens: HmrcScreen[] = [
        {
            width: window.screen.width,
            height: window.screen.height,
            scaling_factor: window.devicePixelRatio || 1,
            colour_depth: window.screen.colorDepth || 24,
        },
    ];

    const windowSize: HmrcWindowSize = {
        width: window.innerWidth,
        height: window.innerHeight,
    };

    const tzInfo = Intl.DateTimeFormat().resolvedOptions();
    const timezone: HmrcTimezone = {
        iana: tzInfo.timeZone || 'Etc/UTC',
        // Date.getTimezoneOffset() returns minutes WEST of UTC (e.g. +60 in
        // London winter, -60 in summer). Flip the sign for HMRC's convention.
        offset_minutes: -new Date().getTimezoneOffset(),
    };

    return {
        screens,
        window_size: windowSize,
        timezone,
        browser_user_agent: navigator.userAgent,
    };
};
