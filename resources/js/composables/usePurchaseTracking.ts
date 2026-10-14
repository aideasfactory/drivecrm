import { onMounted } from 'vue';

export type PurchaseEvent = {
    event: 'purchase';
    transaction_id: string;
    value: number;
    currency: string;
    items: { item_name: string; item_id: string; price: number; quantity: number }[];
};

declare global {
    interface Window {
        dataLayer?: unknown[];
    }
}

const storageKey = (transactionId: string): string => `purchase-tracked:${transactionId}`;

const alreadyTracked = (transactionId: string): boolean => {
    try {
        return window.localStorage.getItem(storageKey(transactionId)) !== null;
    } catch {
        return false;
    }
};

const markTracked = (transactionId: string): void => {
    try {
        window.localStorage.setItem(storageKey(transactionId), '1');
    } catch {
        // Storage unavailable (private mode) — the server-side claim still prevents repeats
    }
};

/**
 * Push the GTM `purchase` event for a paid order. The server only sends the
 * payload on the first visit to the success page; the localStorage guard also
 * covers Inertia restoring that first visit's props on back/forward navigation.
 */
export function usePurchaseTracking(purchaseEvent: PurchaseEvent | null | undefined): void {
    onMounted(() => {
        if (!purchaseEvent || alreadyTracked(purchaseEvent.transaction_id)) {
            return;
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(purchaseEvent);
        markTracked(purchaseEvent.transaction_id);
    });
}
