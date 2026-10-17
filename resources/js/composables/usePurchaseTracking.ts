import { onBeforeUnmount, onMounted } from 'vue';
import { hasConsentSignal, isConsentGranted, watchConsent } from '@/lib/consent';

export type UserProvidedData = {
    email?: string;
    phone_number?: string;
    address?: { first_name?: string; last_name?: string; postal_code?: string; country: string };
};

export type PurchaseEvent = {
    event: 'purchase';
    transaction_id: string;
    value: number;
    currency: string;
    items: { item_name: string; item_id: string; price: number; quantity: number }[];
    user_data?: UserProvidedData | null;
};

declare global {
    interface Window {
        dataLayer?: unknown[];
    }
}

/** How long to wait for Usercentrics to set the consent default before pushing anyway. */
const CONSENT_SIGNAL_TIMEOUT_MS = 2500;

const purchaseKey = (transactionId: string): string => `purchase-tracked:${transactionId}`;
const adsConsentKey = (transactionId: string): string => `purchase-ads-consented:${transactionId}`;

const isMarked = (key: string): boolean => {
    try {
        return window.localStorage.getItem(key) !== null;
    } catch {
        return false;
    }
};

const mark = (key: string): void => {
    try {
        window.localStorage.setItem(key, '1');
    } catch {
        // Storage unavailable (private mode) — the server-side claim still prevents repeats
    }
};

const push = (payload: Record<string, unknown>): void => {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(payload);
};

/**
 * Push the GTM `purchase` event for a paid order. The server only sends the
 * payload on the first visit to the success page; the localStorage guards also
 * cover Inertia restoring that first visit's props on back/forward navigation.
 *
 * The push waits briefly for the Usercentrics consent default so GTM evaluates
 * the tags with the right consent state. `user_data` (enhanced conversions) is
 * only included once ad_user_data is granted. If ad_storage is still denied at
 * purchase time, a `purchase_consent_granted` event with the same
 * transaction_id follows as soon as the visitor accepts, so the Google Ads
 * conversion tag can fire again (Ads dedupes on transaction_id).
 */
export function usePurchaseTracking(purchaseEvent: PurchaseEvent | null | undefined): void {
    const stopFns: (() => void)[] = [];
    const stopAll = (): void => {
        stopFns.splice(0).forEach((stop) => stop());
    };

    const payloadFor = (purchase: PurchaseEvent, event: string): Record<string, unknown> => {
        const { user_data: userData, ...rest } = purchase;

        return {
            ...rest,
            event,
            ...(userData && isConsentGranted('ad_user_data') ? { user_data: userData } : {}),
        };
    };

    const trackAdsConsent = (purchase: PurchaseEvent): void => {
        const key = adsConsentKey(purchase.transaction_id);

        if (isMarked(key)) {
            return;
        }

        const pushIfGranted = (): boolean => {
            if (!isConsentGranted('ad_storage')) {
                return false;
            }

            push(payloadFor(purchase, 'purchase_consent_granted'));
            mark(key);
            stopAll();

            return true;
        };

        if (!pushIfGranted()) {
            stopFns.push(watchConsent(pushIfGranted));
        }
    };

    const trackPurchase = (purchase: PurchaseEvent): void => {
        const transactionId = purchase.transaction_id;

        if (!isMarked(purchaseKey(transactionId))) {
            push(payloadFor(purchase, 'purchase'));
            mark(purchaseKey(transactionId));

            if (isConsentGranted('ad_storage')) {
                mark(adsConsentKey(transactionId));
            }
        }

        trackAdsConsent(purchase);
    };

    onMounted(() => {
        if (!purchaseEvent) {
            return;
        }

        if (hasConsentSignal()) {
            trackPurchase(purchaseEvent);

            return;
        }

        let started = false;
        const start = (): void => {
            if (started) {
                return;
            }
            started = true;
            stopAll();
            trackPurchase(purchaseEvent);
        };

        stopFns.push(
            watchConsent(() => {
                if (hasConsentSignal()) {
                    start();
                }
            }, 100),
        );
        const timeout = window.setTimeout(start, CONSENT_SIGNAL_TIMEOUT_MS);
        stopFns.push(() => window.clearTimeout(timeout));
    });

    onBeforeUnmount(stopAll);
}
