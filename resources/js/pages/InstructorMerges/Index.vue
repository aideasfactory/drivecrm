<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowRight,
    Ban,
    GitMerge,
    Loader2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TransferSearchSelect, {
    type TransferSearchOption,
} from '@/components/StudentTransfers/TransferSearchSelect.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from '@/components/ui/sonner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index as mergesIndex,
    preview as previewRoute,
    searchInstructors,
    store as storeRoute,
} from '@/routes/instructor-merges';

interface InstructorSummary extends TransferSearchOption {
    status: string;
    pupils_count: number;
    stripe_account_id: string | null;
    stripe_connected: boolean;
    payouts_enabled: boolean;
    created_at: string | null;
}

interface MergeReport {
    tables: Record<string, number>;
    staying_payouts: number;
    staying_orders: number;
    staying_lessons: number;
    calendar_days_moved: number;
    calendar_days_merged: number;
    calendar_days_dropped: number;
    calendar_items_moved: number;
    calendar_empty_slots_removed: number;
    calendar_slots_clashing: number;
    progress_scores_moved: number;
    progress_categories_dropped: number;
    activity_logs: number;
    contacts: number;
    notes: number;
    import_mappings: number;
    retired_email: string;
    kept_login_email: string;
}

interface MergeWarning {
    key: string;
    message: string;
}

interface DuplicatePupil {
    retired_student_id: number;
    kept_student_id: number;
    name: string;
    matched_on: string;
}

interface DiaryClash {
    date: string;
    retired_item_id: number;
    retired_type: string;
    retired_start: string;
    retired_end: string;
    kept_item_id: number;
    kept_type: string;
    kept_start: string;
    kept_end: string;
}

interface MergePreview {
    from: InstructorSummary;
    into: InstructorSummary;
    report: MergeReport | null;
    blockers: string[];
    warnings: MergeWarning[];
    duplicate_pupils: DuplicatePupil[];
    clashes: DiaryClash[];
    warnings_token: string;
}

interface MergeHistoryRow {
    id: number;
    name: string | null;
    original_email: string | null;
    merged_into_id: number | null;
    merged_into_name: string | null;
    merged_into_email: string | null;
    merged_by_name: string | null;
    merged_at: string | null;
}

const props = defineProps<{
    history: MergeHistoryRow[];
}>();

const TABLE_LABELS: Record<string, string> = {
    orders: 'Orders',
    '  …of which paid through Stripe': '…of which paid through Stripe',
    lessons: 'Lessons',
    '  …of which have lesson payments': '…of which have lesson payments',
    students: 'Pupils',
    packages: 'Packages',
    instructor_finances: 'Finance entries',
    mileage_logs: 'Mileage logs',
    locations: 'Coverage areas',
    slot_offers: 'Slot offers',
    vehicles: 'Vehicles',
    student_tests: 'Pupil theory / practical tests',
    'messages.from (user)': 'Messages sent',
    'messages.to (user)': 'Messages received',
    'notes.user_id (user)': 'Notes written by the instructor',
    'orders.booked_by_user_id (user)': 'Orders booked by the instructor',
    'lessons.banked_by_user_id (user)': 'Lessons banked by the instructor',
    'lessons.cancelled_by_user_id (user)': 'Lessons deleted by the instructor',
    'lesson_payments.alternative_payment_confirmed_by (user)':
        'Alternative payments confirmed',
    'support_ticket_archives.participant_id (user)':
        'Archived support conversations',
    'support_ticket_archives.owner_id (user)': 'Support archives (as owner)',
    'resource_watches.user_id (user)': 'Resources watched',
};

const selectedFrom = ref<TransferSearchOption | null>(null);
const selectedInto = ref<TransferSearchOption | null>(null);
const takeEmail = ref(false);

const preview = ref<MergePreview | null>(null);
const previewLoading = ref(false);
const previewError = ref<string | null>(null);
let previewRequest = 0;

const warningsAcknowledged = ref(false);
const confirmOpen = ref(false);
const confirmEmail = ref('');
const submitting = ref(false);

const moveRows = computed(() => {
    const report = preview.value?.report;
    if (!report) return [];

    return [
        ...Object.entries(report.tables).map(([key, count]) => ({
            label: TABLE_LABELS[key] ?? key.trim(),
            count,
            nested: key.startsWith('  '),
        })),
        {
            label: 'Diary items (lessons, blocks, travel)',
            count: report.calendar_items_moved,
            nested: false,
        },
        {
            label: '…onto new days',
            count: report.calendar_days_moved,
            nested: true,
        },
        {
            label: '…merged into days the live account already has',
            count: report.calendar_days_merged,
            nested: true,
        },
        {
            label: 'Progress scores (onto matching tracker items)',
            count: report.progress_scores_moved,
            nested: false,
        },
        {
            label: 'Activity log entries',
            count: report.activity_logs,
            nested: false,
        },
        { label: 'Emergency contacts', count: report.contacts, nested: false },
        {
            label: 'Notes about the instructor',
            count: report.notes,
            nested: false,
        },
        {
            label: 'Import mappings',
            count: report.import_mappings,
            nested: false,
        },
    ];
});

const removedRows = computed(() => {
    const report = preview.value?.report;
    if (!report) return [];

    return [
        {
            label: 'Empty availability slots (+ travel) removed',
            count: report.calendar_empty_slots_removed,
        },
        {
            label: 'Old diary days left empty and dropped',
            count: report.calendar_days_dropped,
        },
        {
            label: 'Old progress tracker categories dropped (scores move first)',
            count: report.progress_categories_dropped,
        },
    ];
});

const stayingRows = computed(() => {
    const report = preview.value?.report;
    if (!report) return [];

    return [
        { label: 'Payouts', count: report.staying_payouts },
        {
            label: 'Orders with a paid-out lesson',
            count: report.staying_orders,
        },
        { label: '…and their lessons', count: report.staying_lessons },
    ];
});

const isBlocked = computed(() => (preview.value?.blockers.length ?? 0) > 0);
const hasWarnings = computed(() => (preview.value?.warnings.length ?? 0) > 0);

const canConfirm = computed(
    () =>
        preview.value !== null &&
        !previewLoading.value &&
        !isBlocked.value &&
        (!hasWarnings.value || warningsAcknowledged.value),
);

const emailMatches = computed(
    () =>
        preview.value?.from.email !== null &&
        confirmEmail.value.trim().toLowerCase() ===
            (preview.value?.from.email ?? '').toLowerCase(),
);

const loadPreview = async () => {
    preview.value = null;
    previewError.value = null;
    warningsAcknowledged.value = false;

    if (!selectedFrom.value || !selectedInto.value) return;

    const requestId = ++previewRequest;
    previewLoading.value = true;

    try {
        const response = await axios.get<MergePreview>(previewRoute.url(), {
            params: {
                from_instructor_id: selectedFrom.value.id,
                into_instructor_id: selectedInto.value.id,
                take_email: takeEmail.value ? 1 : 0,
            },
        });
        if (requestId === previewRequest) preview.value = response.data;
    } catch (error: any) {
        if (requestId !== previewRequest) return;
        const errors = error.response?.data?.errors;
        previewError.value =
            (errors && (Object.values(errors)[0] as string[])[0]) ||
            error.response?.data?.message ||
            'Failed to load the merge preview';
    } finally {
        if (requestId === previewRequest) previewLoading.value = false;
    }
};

watch([selectedFrom, selectedInto, takeEmail], loadPreview);

const openConfirm = () => {
    if (!canConfirm.value) return;
    confirmEmail.value = '';
    confirmOpen.value = true;
};

const handleMerge = () => {
    if (!preview.value || !emailMatches.value) return;

    submitting.value = true;

    router.post(
        storeRoute.url(),
        {
            from_instructor_id: preview.value.from.id,
            into_instructor_id: preview.value.into.id,
            take_email: takeEmail.value,
            acknowledged_warnings_token: warningsAcknowledged.value
                ? preview.value.warnings_token
                : null,
            confirm_email: confirmEmail.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props.flash as { success?: string }) ?? {};
                toast.success(flash.success ?? 'Accounts merged.');
                confirmOpen.value = false;
                selectedFrom.value = null;
                selectedInto.value = null;
                takeEmail.value = false;
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(
                    typeof firstError === 'string'
                        ? firstError
                        : 'Failed to merge accounts',
                );
                confirmOpen.value = false;
                loadPreview();
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

const formatTime = (value: string) => value.slice(0, 5);

const breadcrumbs = [{ title: 'Merge Instructors', href: mergesIndex().url }];
</script>

<template>
    <Head title="Merge Instructors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="flex items-center gap-3 text-3xl font-bold">
                    <GitMerge class="h-8 w-8" />
                    Merge Instructors
                </h2>
                <p class="max-w-3xl text-muted-foreground">
                    Move everything from an old instructor account onto the live
                    one, then deactivate the old account. The old record is kept
                    (archived, cannot log in) with a note of the merge. Payments
                    are never changed. Only the links to the instructor move,
                    and payout history stays with the old account.
                </p>
            </div>

            <Card class="max-w-3xl">
                <CardContent class="flex flex-col gap-5 pt-6">
                    <div
                        class="grid gap-5 md:grid-cols-[1fr_auto_1fr] md:items-start"
                    >
                        <div class="flex flex-col gap-2">
                            <Label for="from-instructor"
                                >Old account (merged and deactivated)</Label
                            >
                            <TransferSearchSelect
                                input-id="from-instructor"
                                :endpoint="searchInstructors.url()"
                                results-key="instructors"
                                placeholder="Search by name, email or phone..."
                                v-model="selectedFrom"
                                :exclude-id="selectedInto?.id"
                            />
                        </div>
                        <ArrowRight
                            class="mx-auto hidden h-5 w-5 text-muted-foreground md:mt-8 md:block"
                        />
                        <div class="flex flex-col gap-2">
                            <Label for="into-instructor"
                                >Live account (kept)</Label
                            >
                            <TransferSearchSelect
                                input-id="into-instructor"
                                :endpoint="searchInstructors.url()"
                                results-key="instructors"
                                placeholder="Search by name, email or phone..."
                                v-model="selectedInto"
                                :exclude-id="selectedFrom?.id"
                            />
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="take-email"
                            :model-value="takeEmail"
                            @update:model-value="takeEmail = $event === true"
                        />
                        <Label
                            for="take-email"
                            class="cursor-pointer text-sm leading-relaxed font-normal"
                        >
                            Give the old account's email to the live login.
                            Leave this off to keep the live account's current
                            email (the instructor logs in as today).
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <div v-if="previewLoading" class="flex max-w-3xl flex-col gap-3">
                <Skeleton class="h-24 w-full" />
                <Skeleton class="h-64 w-full" />
            </div>

            <Alert
                v-else-if="previewError"
                variant="destructive"
                class="max-w-3xl"
            >
                <AlertTriangle />
                <AlertTitle>Preview failed</AlertTitle>
                <AlertDescription>{{ previewError }}</AlertDescription>
            </Alert>

            <template v-else-if="preview">
                <div class="grid max-w-3xl gap-4 md:grid-cols-2">
                    <Card v-for="side in ['from', 'into'] as const" :key="side">
                        <CardHeader>
                            <CardDescription>
                                {{
                                    side === 'from'
                                        ? 'Old account (deactivated)'
                                        : 'Live account (kept)'
                                }}
                            </CardDescription>
                            <CardTitle
                                >{{ preview[side].name }}
                                <span class="text-muted-foreground"
                                    >#{{ preview[side].id }}</span
                                ></CardTitle
                            >
                        </CardHeader>
                        <CardContent class="flex flex-col gap-1 text-sm">
                            <p>{{ preview[side].email }}</p>
                            <p class="text-muted-foreground">
                                {{ preview[side].pupils_count }} pupils · status
                                {{ preview[side].status }}
                            </p>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <Badge
                                    :variant="
                                        preview[side].stripe_account_id
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                >
                                    Stripe:
                                    {{
                                        preview[side].stripe_account_id ??
                                        'not connected'
                                    }}
                                </Badge>
                                <Badge
                                    v-if="preview[side].stripe_account_id"
                                    :variant="
                                        preview[side].payouts_enabled
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                >
                                    Payouts
                                    {{
                                        preview[side].payouts_enabled
                                            ? 'enabled'
                                            : 'disabled'
                                    }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Alert v-if="isBlocked" variant="destructive" class="max-w-3xl">
                    <Ban />
                    <AlertTitle>This merge can't run</AlertTitle>
                    <AlertDescription>
                        <ul class="list-disc space-y-1 pl-4">
                            <li
                                v-for="blocker in preview.blockers"
                                :key="blocker"
                            >
                                {{ blocker }}
                            </li>
                        </ul>
                    </AlertDescription>
                </Alert>

                <Alert v-if="hasWarnings" class="max-w-3xl">
                    <AlertTriangle />
                    <AlertTitle>Check before merging</AlertTitle>
                    <AlertDescription class="flex flex-col gap-3">
                        <ul class="list-disc space-y-1 pl-4">
                            <li
                                v-for="warning in preview.warnings"
                                :key="warning.key"
                            >
                                {{ warning.message }}
                            </li>
                        </ul>
                        <div v-if="!isBlocked" class="flex items-start gap-3">
                            <Checkbox
                                id="acknowledge-warnings"
                                :model-value="warningsAcknowledged"
                                @update:model-value="
                                    warningsAcknowledged = $event === true
                                "
                            />
                            <Label
                                for="acknowledge-warnings"
                                class="cursor-pointer text-sm font-normal"
                            >
                                I've checked these and want to merge anyway.
                            </Label>
                        </div>
                    </AlertDescription>
                </Alert>

                <Card v-if="preview.report" class="max-w-3xl">
                    <CardHeader>
                        <CardTitle>What moves to the live account</CardTitle>
                        <CardDescription>
                            Counts from the database right now. They are worked
                            out again when you confirm.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Record</TableHead>
                                    <TableHead class="text-right"
                                        >Rows</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in moveRows"
                                    :key="row.label"
                                >
                                    <TableCell
                                        :class="
                                            row.nested
                                                ? 'pl-8 text-muted-foreground'
                                                : ''
                                        "
                                        >{{ row.label }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ row.count }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Removed / dropped</TableHead>
                                    <TableHead class="text-right"
                                        >Rows</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in removedRows"
                                    :key="row.label"
                                >
                                    <TableCell>{{ row.label }}</TableCell>
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ row.count }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead
                                        >Stays on the old account (payout
                                        history)</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Rows</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in stayingRows"
                                    :key="row.label"
                                >
                                    <TableCell>{{ row.label }}</TableCell>
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ row.count }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>

                        <div class="rounded-md border p-4 text-sm">
                            <p>
                                Old login email becomes
                                <span class="font-medium">{{
                                    preview.report.retired_email
                                }}</span
                                >, with its password randomised and its app
                                sessions and push token removed.
                            </p>
                            <p class="mt-1">
                                The live account will log in with
                                <span class="font-medium">{{
                                    preview.report.kept_login_email
                                }}</span>
                                (password unchanged).
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="preview.clashes.length" class="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Diary clashes</CardTitle>
                        <CardDescription
                            >Old-account items that overlap the live diary
                            (first 50).</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Old account item</TableHead>
                                    <TableHead>Live account item</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="clash in preview.clashes"
                                    :key="`${clash.retired_item_id}-${clash.kept_item_id}`"
                                >
                                    <TableCell>{{ clash.date }}</TableCell>
                                    <TableCell
                                        >{{ clash.retired_type }}
                                        {{ formatTime(clash.retired_start) }}–{{
                                            formatTime(clash.retired_end)
                                        }}</TableCell
                                    >
                                    <TableCell
                                        >{{ clash.kept_type }}
                                        {{ formatTime(clash.kept_start) }}–{{
                                            formatTime(clash.kept_end)
                                        }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card v-if="preview.duplicate_pupils.length" class="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Possible duplicate pupils</CardTitle>
                        <CardDescription
                            >Both records move to the live account. They are not
                            combined.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pupil</TableHead>
                                    <TableHead>Matched on</TableHead>
                                    <TableHead class="text-right"
                                        >Student ids (old / live)</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="pupil in preview.duplicate_pupils"
                                    :key="pupil.retired_student_id"
                                >
                                    <TableCell>{{
                                        pupil.name || '—'
                                    }}</TableCell>
                                    <TableCell>{{
                                        pupil.matched_on
                                    }}</TableCell>
                                    <TableCell class="text-right tabular-nums"
                                        >#{{ pupil.retired_student_id }} / #{{
                                            pupil.kept_student_id
                                        }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div class="max-w-3xl">
                    <Button
                        variant="destructive"
                        class="min-w-[200px] cursor-pointer"
                        :disabled="!canConfirm"
                        @click="openConfirm"
                    >
                        <GitMerge class="mr-2 h-4 w-4" />
                        Merge accounts…
                    </Button>
                </div>
            </template>

            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>Merge history</CardTitle>
                    <CardDescription
                        >Deactivated accounts and where they were
                        merged.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!props.history.length"
                        class="text-sm text-muted-foreground"
                    >
                        No accounts have been merged yet.
                    </p>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Old account</TableHead>
                                <TableHead>Merged into</TableHead>
                                <TableHead>By</TableHead>
                                <TableHead>When</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in props.history"
                                :key="row.id"
                            >
                                <TableCell>
                                    <div>
                                        {{ row.name ?? '—' }}
                                        <span class="text-muted-foreground"
                                            >#{{ row.id }}</span
                                        >
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.original_email }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div>
                                        {{ row.merged_into_name ?? '—' }}
                                        <span
                                            v-if="row.merged_into_id"
                                            class="text-muted-foreground"
                                            >#{{ row.merged_into_id }}</span
                                        >
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.merged_into_email }}
                                    </div>
                                </TableCell>
                                <TableCell>{{
                                    row.merged_by_name ?? 'Console'
                                }}</TableCell>
                                <TableCell>{{
                                    formatDateTime(row.merged_at)
                                }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="confirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <GitMerge class="h-5 w-5" />
                        Merge {{ preview?.from.name }} into
                        {{ preview?.into.name }}?
                    </DialogTitle>
                    <DialogDescription>
                        This moves the old account's data onto the live account
                        and deactivates the old login in one step. It can't be
                        undone from the CRM. Take a database backup first.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-2">
                    <Label for="confirm-email"
                        >Type the old account's email to confirm</Label
                    >
                    <Input
                        id="confirm-email"
                        v-model="confirmEmail"
                        autocomplete="off"
                        :placeholder="preview?.from.email ?? ''"
                        @keydown.enter.prevent="handleMerge"
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="cursor-pointer"
                        :disabled="submitting"
                        @click="confirmOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        class="min-w-[160px] cursor-pointer"
                        :disabled="!emailMatches || submitting"
                        @click="handleMerge"
                    >
                        <Loader2
                            v-if="submitting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <GitMerge v-else class="mr-2 h-4 w-4" />
                        {{ submitting ? 'Merging...' : 'Merge accounts' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
