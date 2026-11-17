import { router, usePage } from '@inertiajs/vue3';
import axios, { isAxiosError } from 'axios';
import { ref } from 'vue';

export const SESSION_EXPIRED_MESSAGE =
    'Your session has expired. Please log in again.';

export const isSessionExpired = ref(false);

/**
 * Public checkout pages. Staff are logged in there during a bookings-team
 * booking, but the routes are guest routes, so reloading never reaches the
 * login page and the booking state lives on the enquiry, not the session.
 */
const PUBLIC_PAGE_PREFIXES = ['Onboarding/', 'Booking/', 'PaymentLink/'];

/**
 * 419 is Laravel's CSRF token mismatch and 401 is an unauthenticated JSON
 * response. Pages without a logged-in user are skipped because there is no
 * CRM session to restore, so a "log in" prompt would be wrong.
 */
export function handleExpiredSession(status: number | undefined): boolean {
    if (status !== 419 && status !== 401) {
        return false;
    }

    const page = usePage<{ auth?: { user?: unknown } }>();

    if (
        !page.props?.auth?.user ||
        PUBLIC_PAGE_PREFIXES.some((prefix) =>
            page.component?.startsWith(prefix),
        )
    ) {
        return false;
    }

    isSessionExpired.value = true;

    return true;
}

/**
 * Reloading the current URL lets the `auth` middleware store it as the
 * intended URL and redirect to login, so the user lands back here afterwards.
 */
export function logInAgain(): void {
    window.location.reload();
}

export function initSessionExpiryHandling(): void {
    axios.interceptors.response.use(undefined, (error: unknown) => {
        if (
            isAxiosError(error) &&
            handleExpiredSession(error.response?.status) &&
            error.response?.data &&
            typeof error.response.data === 'object'
        ) {
            (error.response.data as { message?: string }).message =
                SESSION_EXPIRED_MESSAGE;
        }

        return Promise.reject(error);
    });

    router.on('invalid', (event) => {
        if (handleExpiredSession(event.detail.response?.status)) {
            event.preventDefault();
        }
    });
}
