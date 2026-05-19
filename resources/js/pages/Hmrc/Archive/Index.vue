<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/sonner';
import { Archive, Download, RefreshCw, Mail, Loader2, AlertCircle, FileBox } from 'lucide-vue-next';
import ArchiveSummaryDialog from '@/components/Hmrc/Archive/SummaryDialog.vue';

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

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

const props = defineProps<{
    archives: ArchiveRow[];
    taxYears: TaxYearOption[];
    retentionYears: number;
    signedUrlTtlHours: number;
}>();

const page = usePage<PageProps>();
watch(
    () => page.props.flash?.success,
    (v) => {
        if (v) toast.success(v);
    },
    { immediate: true },
);
watch(
    () => page.props.flash?.error,
    (v) => {
        if (v) toast.error(v);
    },
    { immediate: true },
);

const summaryOpen = ref(false);
const summaryYear = ref<number | null>(null);

const formatDateTime = (iso: string | null): string => (iso ? new Date(iso).toLocaleString() : '—');
const formatBytes = (bytes: number | null): string => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
};

const statusBadge = (status: ArchiveRow['status']): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'ready') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'expired') return 'outline';
    return 'secondary';
};

const statusLabel = (status: ArchiveRow['status']): string => {
    if (status === 'queued') return 'Queued';
    if (status === 'building') return 'Building…';
    if (status === 'ready') return 'Ready';
    if (status === 'failed') return 'Failed';
    return 'Expired';
};

const archivedYears = computed(() => new Set(props.archives.map((a) => a.tax_year_start)));
const yearsAvailableForGeneration = computed(() =>
    props.taxYears.filter((y) => !archivedYears.value.has(y.tax_year_start)),
);

const openSummary = (year: number) => {
    summaryYear.value = year;
    summaryOpen.value = true;
};

const downloadArchive = (archive: ArchiveRow) => {
    window.location.href = `/hmrc/archive/${archive.id}/download`;
};

const regenerate = (archive: ArchiveRow) => {
    if (!confirm(`Regenerate the ${archive.tax_year_label} archive? The existing file will be replaced.`)) {
        return;
    }
    router.post(`/hmrc/archive/${archive.id}/regenerate`, {}, { preserveScroll: true });
};

const emailLink = (archive: ArchiveRow) => {
    router.post(`/hmrc/archive/${archive.id}/email-link`, {}, { preserveScroll: true });
};

const refresh = () => router.reload({ only: ['archives'] });

const breadcrumbs = [
    { title: 'HMRC / Tax', href: '/hmrc' },
    { title: 'Year-end archives' },
];
</script>

<template>
    <Head title="Year-end archives" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <h2 class="text-3xl font-bold flex items-center gap-3">
                        <Archive class="h-8 w-8" />
                        Year-end archives
                    </h2>
                    <p class="text-muted-foreground max-w-2xl">
                        Download a ZIP of an entire tax year — finance records, mileage, receipts, HMRC submission payloads,
                        plus a cover-sheet PDF. Built for your accountant or as an HMRC enquiry-response pack.
                        Archives are retained for {{ retentionYears }} years.
                    </p>
                </div>
                <Button variant="outline" @click="refresh" class="cursor-pointer">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Refresh
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Generate a new archive</CardTitle>
                    <CardDescription>
                        Tax years run 6 April to 5 April. The current tax year shows as "in progress" and can still be
                        generated, but the figures may change as new lessons, expenses, or HMRC submissions land.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="yearsAvailableForGeneration.length === 0" class="text-sm text-muted-foreground">
                        Every tax year on file already has an archive.
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <Button
                            v-for="year in yearsAvailableForGeneration"
                            :key="year.tax_year_start"
                            variant="outline"
                            size="sm"
                            @click="openSummary(year.tax_year_start)"
                            class="cursor-pointer"
                        >
                            {{ year.label }}
                            <Badge v-if="year.status === 'in_progress'" variant="secondary" class="ml-2">
                                in progress
                            </Badge>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Your archives</CardTitle>
                </CardHeader>
                <CardContent>
                    <Alert v-if="archives.length === 0">
                        <FileBox class="h-4 w-4" />
                        <AlertTitle>No archives yet</AlertTitle>
                        <AlertDescription>
                            Generate one above when you are ready to hand a tax year over to your accountant.
                        </AlertDescription>
                    </Alert>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tax year</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Generated</TableHead>
                                <TableHead>Size</TableHead>
                                <TableHead>Contents</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="a in archives" :key="a.id">
                                <TableCell class="font-medium">{{ a.tax_year_label }}</TableCell>
                                <TableCell>
                                    <Badge :variant="statusBadge(a.status)">{{ statusLabel(a.status) }}</Badge>
                                    <span v-if="a.status === 'failed' && a.error_message" class="ml-2 text-xs text-destructive">
                                        {{ a.error_message }}
                                    </span>
                                </TableCell>
                                <TableCell>{{ formatDateTime(a.generated_at) }}</TableCell>
                                <TableCell>{{ formatBytes(a.file_size_bytes) }}</TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    <template v-if="a.counts">
                                        {{ a.counts.finances ?? 0 }} finance, {{ a.counts.mileage_logs ?? 0 }} mileage,
                                        {{ a.counts.receipts ?? 0 }} receipts, {{ a.counts.submissions ?? 0 }} submissions
                                    </template>
                                    <template v-else>—</template>
                                </TableCell>
                                <TableCell>{{ formatDateTime(a.expires_at) }}</TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        v-if="a.status === 'ready'"
                                        size="sm"
                                        variant="outline"
                                        @click="downloadArchive(a)"
                                        class="cursor-pointer mr-1"
                                    >
                                        <Download class="mr-2 h-4 w-4" />
                                        Download
                                    </Button>
                                    <Button
                                        v-if="a.status === 'ready'"
                                        size="sm"
                                        variant="ghost"
                                        @click="emailLink(a)"
                                        class="cursor-pointer mr-1"
                                        :title="`Re-send the ${signedUrlTtlHours}h download link to your email`"
                                    >
                                        <Mail class="mr-2 h-4 w-4" />
                                        Email link
                                    </Button>
                                    <Button
                                        v-if="['failed', 'expired', 'ready'].includes(a.status)"
                                        size="sm"
                                        variant="ghost"
                                        @click="regenerate(a)"
                                        class="cursor-pointer"
                                    >
                                        <RefreshCw class="mr-2 h-4 w-4" />
                                        Regenerate
                                    </Button>
                                    <span v-if="['queued', 'building'].includes(a.status)" class="text-xs text-muted-foreground">
                                        <Loader2 class="inline h-3 w-3 animate-spin mr-1" />
                                        Refresh to check progress
                                    </span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <ArchiveSummaryDialog
                v-if="summaryYear !== null"
                :open="summaryOpen"
                :tax-year-start="summaryYear"
                @close="summaryOpen = false"
            />
        </div>
    </AppLayout>
</template>
