import { router } from '@inertiajs/vue3';

/**
 * Push a custom event onto the GTM dataLayer.
 *
 * Safe to call on pages where GTM isn't loaded (it only loads on
 * booking/onboarding routes — see app.blade.php): the push lands in a
 * plain array that nothing reads.
 */
export function pushDataLayerEvent(event: string, payload: Record<string, unknown> = {}): void {
    ((window as any).dataLayer = (window as any).dataLayer || []).push({ event, ...payload });
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
