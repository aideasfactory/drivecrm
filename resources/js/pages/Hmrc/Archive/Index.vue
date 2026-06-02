<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ArchiveIndexPanel from '@/components/Hmrc/Archive/IndexPanel.vue';

interface ArchiveRow {
    id: number;
    tax_year_start: number;
    tax_year_label: string;
    status: 'queued' | 'building' | 'ready' | 'failed' | 'expired';
    file_size_bytes: number | null;
    counts: { finances?: number; mileage_logs?: number; receipts?: number; submissions?: number } | null;
    generated_at: string | null;
    expires_at: string | null;
    queued_at: string | null;
    error_message: string | null;
}

interface TaxYearOption {
    tax_year_start: number;
    label: string;
    status: 'in_progress' | 'complete';
}

const props = defineProps<{
    archives: ArchiveRow[];
    taxYears: TaxYearOption[];
    retentionYears: number;
    signedUrlTtlHours: number;
}>();

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'Year-end archives' },
];
</script>

<template>
    <Head title="Year-end archives" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <ArchiveIndexPanel
                :archives="props.archives"
                :tax-years="props.taxYears"
                :retention-years="props.retentionYears"
                :signed-url-ttl-hours="props.signedUrlTtlHours"
            />
        </div>
    </AppLayout>
</template>
