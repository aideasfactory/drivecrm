import { router, usePage } from '@inertiajs/vue3';
import axios, { isAxiosError } from 'axios';
import { ref } from 'vue';

export const SESSION_EXPIRED_MESSAGE =
    'Your session has expired. Please log in again.';

export const isSessionExpired = ref(false);

/**
 * 419 is Laravel's CSRF token mismatch and 401 is an unauthenticated JSON
 * response. Guest pages (onboarding, booking) are skipped because the user
 * never had a CRM session there, so a "log in" prompt would be wrong.
 */
function handleExpiredSession(status: number | undefined): boolean {
    if (status !== 419 && status !== 401) {
        return false;
    }

    const page = usePage<{ auth?: { user?: unknown } }>();

    if (!page.props?.auth?.user) {
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
