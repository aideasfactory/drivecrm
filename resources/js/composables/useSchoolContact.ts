import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppPageProps, SchoolContact } from '@/types';

/**
 * School contact details from config/contact.php, shared on every Inertia page.
 */
export function useSchoolContact() {
    const page = usePage<AppPageProps>();

    const contact = computed<SchoolContact>(() => page.props.schoolContact);

    const phoneHref = computed(() => `tel:${contact.value.phone.replace(/[^\d+]/g, '')}`);

    const emailHref = computed(() => `mailto:${contact.value.email}`);

    return { contact, phoneHref, emailHref };
}
