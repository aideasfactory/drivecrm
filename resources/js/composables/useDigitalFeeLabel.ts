import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '@/types';

/**
 * Hourly digital-fee label from config/fees.php, shared on every Inertia page.
 * Example: "£1.80/hour".
 */
export function useDigitalFeeLabel() {
    const page = usePage<AppPageProps>();

    const perHourLabel = computed(() => page.props.digitalFeePerHour ?? '£1.80/hour');

    return { perHourLabel };
}
