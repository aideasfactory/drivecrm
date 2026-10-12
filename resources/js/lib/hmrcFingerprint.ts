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

const currentScreen = (): HmrcScreen => ({
    width: window.screen.width,
    height: window.screen.height,
    scaling_factor: Math.round((window.devicePixelRatio || 1) * 100) / 100,
    colour_depth: window.screen.colorDepth || 24,
});

/**
 * All monitors when the Window Management API is already granted.
 * Otherwise the screen the window is on. Never prompts for permission.
 */
const collectScreens = async (): Promise<HmrcScreen[]> => {
    const fallback = [currentScreen()];
    const getScreenDetails = (
        window as Window & {
            getScreenDetails?: () => Promise<{
                screens: Array<{
                    width: number;
                    height: number;
                    devicePixelRatio?: number;
                    colorDepth?: number;
                }>;
            }>;
        }
    ).getScreenDetails;

    if (typeof getScreenDetails !== 'function' || !navigator.permissions?.query) {
        return fallback;
    }

    try {
        const permission = await navigator.permissions.query({
            name: 'window-management' as PermissionName,
        });

        if (permission.state !== 'granted') {
            return fallback;
        }

        const details = await getScreenDetails();
        const screens = details.screens
            .filter((screen) => screen.width > 0 && screen.height > 0)
            .map((screen) => ({
                width: screen.width,
                height: screen.height,
                scaling_factor: Math.round((screen.devicePixelRatio || window.devicePixelRatio || 1) * 100) / 100,
                colour_depth: screen.colorDepth || window.screen.colorDepth || 24,
            }));

        return screens.length > 0 ? screens : fallback;
    } catch {
        return fallback;
    }
};

export const captureHmrcFingerprint = async (): Promise<HmrcFingerprint> => {
    const screens = await collectScreens();

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
