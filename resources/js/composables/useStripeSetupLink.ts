import axios from 'axios';
import { ref } from 'vue';
import { sendStripeSetupLink } from '@/actions/App/Http/Controllers/InstructorController';
import { toast } from '@/components/ui/toast';

/**
 * Owner action: email an instructor a link to connect Stripe themselves,
 * instead of the owner going through Stripe onboarding on their behalf.
 *
 * @example
 * ```vue
 * const { sendingSetupLink, sendSetupLink } = useStripeSetupLink(() => props.instructor.id);
 * ```
 */
export function useStripeSetupLink(instructorId: () => number) {
    const sendingSetupLink = ref(false);

    const sendSetupLink = async () => {
        if (sendingSetupLink.value) {
            return;
        }

        sendingSetupLink.value = true;

        try {
            const { data } = await axios.post(sendStripeSetupLink.url(instructorId()));
            toast({ title: data?.message ?? 'Stripe setup link sent.' });
        } catch (error: any) {
            const message =
                error?.response?.data?.message ?? 'Failed to send Stripe setup link.';
            toast({ title: message, variant: 'destructive' });
        } finally {
            sendingSetupLink.value = false;
        }
    };

    return { sendingSetupLink, sendSetupLink };
}
