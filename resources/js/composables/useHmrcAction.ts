import axios from 'axios';
import { ref } from 'vue';
import { captureHmrcFingerprint } from '@/lib/hmrcFingerprint';

/**
 * Wraps the "capture fingerprint then run an HMRC action" flow used by
 * Phase 2's Validate-fraud-headers and (later) Phase 3/4 submission buttons.
 *
 * The composable is deliberately thin: the *server* is the source of truth
 * for which fields are required and what's stale. We just push a fresh
 * fingerprint then call the action.
 */
export const useHmrcAction = () => {
    const running = ref(false);
    const error = ref<string | null>(null);

    const refreshFingerprint = async (): Promise<void> => {
        const payload = captureHmrcFingerprint();
        await axios.post('/hmrc/fingerprint', payload);
    };

    const run = async <T>(action: () => Promise<T>): Promise<T | null> => {
        running.value = true;
        error.value = null;
        try {
            await refreshFingerprint();
            return await action();
        } catch (e: unknown) {
            const fallback = 'Something went wrong talking to HMRC.';
            if (axios.isAxiosError(e)) {
                error.value = (e.response?.data as { message?: string } | undefined)?.message ?? fallback;
            } else if (e instanceof Error) {
                error.value = e.message;
            } else {
                error.value = fallback;
            }
            return null;
        } finally {
            running.value = false;
        }
    };

    return { run, refreshFingerprint, running, error };
};
