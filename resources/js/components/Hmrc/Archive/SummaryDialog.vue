<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Loader2, Archive } from 'lucide-vue-next';

interface Counts {
    finances: number;
    mileage_logs: number;
    receipts: number;
    submissions: number;
}

interface Props {
    open: boolean;
    taxYearStart: number;
}

interface Emits {
    (e: 'close'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const loading = ref(false);
const counts = ref<Counts | null>(null);
const submitting = ref(false);

const taxYearLabel = (start: number) => `${start}/${String(start + 1).slice(-2)}`;

const fetchSummary = async () => {
    loading.value = true;
    counts.value = null;
    try {
        const response = await axios.get<Counts>('/hmrc/archive/summary', {
            params: { tax_year_start: props.taxYearStart },
        });
        counts.value = response.data;
    } catch {
        counts.value = null;
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.open, props.taxYearStart],
    () => {
        if (props.open) fetchSummary();
    },
    { immediate: true },
);

const onOpenChange = (open: boolean) => {
    if (!open && !submitting.value) emit('close');
};

const handleConfirm = () => {
    submitting.value = true;
    router.post(
        '/hmrc/archive',
        { tax_year_start: props.taxYearStart },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
                emit('close');
            },
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Archive class="h-5 w-5" />
                    Generate {{ taxYearLabel(taxYearStart) }} archive
                </DialogTitle>
                <DialogDescription>
                    Your archive will include:
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                <Loader2 class="h-4 w-4 animate-spin" />
                Counting your records…
            </div>

            <ul v-else-if="counts" class="space-y-1 text-sm">
                <li>• {{ counts.finances.toLocaleString() }} payment / expense rows</li>
                <li>• {{ counts.mileage_logs.toLocaleString() }} mileage entries</li>
                <li>• {{ counts.receipts.toLocaleString() }} receipt files</li>
                <li>• {{ counts.submissions }} HMRC quarterly submission(s)</li>
                <li class="text-muted-foreground pt-2">Plus a summary PDF cover sheet.</li>
            </ul>

            <p v-else class="text-sm text-destructive">Could not load summary. Try again in a moment.</p>

            <p class="text-xs text-muted-foreground">
                Building the ZIP runs asynchronously. We will email you a 24-hour download link when it's ready.
            </p>

            <DialogFooter>
                <Button variant="outline" @click="onOpenChange(false)" :disabled="submitting" class="cursor-pointer">
                    Cancel
                </Button>
                <Button @click="handleConfirm" :disabled="submitting || loading" class="cursor-pointer min-w-[140px]">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ submitting ? 'Queuing…' : 'Generate' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
