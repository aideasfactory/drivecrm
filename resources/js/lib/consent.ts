export type ConsentType = 'ad_storage' | 'ad_user_data' | 'ad_personalization' | 'analytics_storage';

export type ConsentState = Partial<Record<ConsentType, 'granted' | 'denied'>>;

/**
 * Current Google Consent Mode state, read from the dataLayer. Usercentrics'
 * Google Consent Mode integration calls gtag('consent', 'default' | 'update', {...}),
 * and gtag pushes its `arguments` object, so later entries win.
 */
export function googleConsentState(): ConsentState {
    const state: ConsentState = {};

    for (const entry of (window as any).dataLayer ?? []) {
        if (entry && typeof entry === 'object' && entry[0] === 'consent' && entry[2] && typeof entry[2] === 'object') {
            Object.assign(state, entry[2]);
        }
    }

    return state;
}

export function hasConsentSignal(): boolean {
    return Object.keys(googleConsentState()).length > 0;
}

export function isConsentGranted(type: ConsentType): boolean {
    return googleConsentState()[type] === 'granted';
}

const USERCENTRICS_EVENTS = ['UC_CONSENT', 'UC_UI_CMP_EVENT', 'ucEvent'];

/**
 * Call `check` whenever consent may have changed: on Usercentrics window
 * events (deferred a tick so the gtag consent update lands first) and on a
 * slow poll, since event names differ between Usercentrics versions and
 * admin settings. Returns a function that stops listening.
 */
export function watchConsent(check: () => void, pollMs = 1000): () => void {
    const deferredCheck = (): void => {
        window.setTimeout(check, 0);
    };

    USERCENTRICS_EVENTS.forEach((name) => window.addEventListener(name, deferredCheck));
    const interval = window.setInterval(check, pollMs);

    return () => {
        USERCENTRICS_EVENTS.forEach((name) => window.removeEventListener(name, deferredCheck));
        window.clearInterval(interval);
    };
}
