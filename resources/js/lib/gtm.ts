import { router } from '@inertiajs/vue3';

/**
 * Push a custom event onto the GTM dataLayer.
 *
 * Safe to call on pages where GTM isn't loaded (it only loads on the public
 * booking, onboarding and payment-link routes — see app.blade.php): the
 * push lands in a plain array that nothing reads.
 */
export function pushDataLayerEvent(event: string, payload: Record<string, unknown> = {}): void {
    ((window as any).dataLayer = (window as any).dataLayer || []).push({ event, ...payload });
}

export interface PurchaseEvent {
    transaction_id: string;
    value: number;
    currency: string;
    payment_mode: string | null;
    items: Record<string, unknown>[];
}

/**
 * Push the GA4 "purchase" ecommerce event for a paid order. The server only
 * sends the payload on the first render; the localStorage guard also stops
 * a re-fire when Inertia restores the page from history.
 */
export function pushPurchaseEvent(purchase: PurchaseEvent | null | undefined): void {
    if (!purchase) {
        return;
    }

    const storageKey = `gtm_purchase_tracked_${purchase.transaction_id}`;

    try {
        if (window.localStorage.getItem(storageKey)) {
            return;
        }
        window.localStorage.setItem(storageKey, '1');
    } catch {
        // Storage blocked — rely on the server-side once-only guard.
    }

    ((window as any).dataLayer = (window as any).dataLayer || []).push({ ecommerce: null });
    pushDataLayerEvent('purchase', {
        transaction_id: purchase.transaction_id,
        value: purchase.value,
        currency: purchase.currency,
        payment_mode: purchase.payment_mode,
        ecommerce: {
            transaction_id: purchase.transaction_id,
            value: purchase.value,
            currency: purchase.currency,
            items: purchase.items,
        },
    });
}

/**
 * Inertia navigations are XHR-based, so GTM only ever sees the first real
 * page load. Emit a virtual_page_view on every successful visit (including
 * the initial load) so GTM can track the full customer journey with
 * custom-event triggers instead of page-load triggers.
 */
export function initGtmPageTracking(): void {
    router.on('navigate', () => {
        pushDataLayerEvent('virtual_page_view', {
            page_path: window.location.pathname + window.location.search,
            page_location: window.location.href,
            page_title: document.title,
        });
    });
}
