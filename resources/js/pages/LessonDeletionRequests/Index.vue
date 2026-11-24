<script setup lang="ts">
import { Deferred, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    CalendarX,
    CheckCircle2,
    History,
    Loader2,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { index as instructorsIndex } from '@/routes/instructors';
import { approve, reject } from '@/routes/lesson-deletion-requests';

interface DeletionRequestRow {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    status_label: string;
    lesson_id: number | null;
    lesson_exists: boolean;
    instructor_id: number | null;
    instructor_name: string | null;
    student_id: number | null;
    student_name: string | null;
    lesson_date: string | null;
    lesson_start_time: string | null;
    lesson_end_time: string | null;
    payment_mode: string | null;
    reason: string;
    requested_at: string | null;
    decided_at: string | null;
    decided_by: string | null;
    blocked_reason: string | null;
}

interface Props {
    pending: DeletionRequestRow[];
    history?: DeletionRequestRow[];
}

const props = defineProps<Props>();

const approveTarget = ref<DeletionRequestRow | null>(null);
const approving = ref(false);
const rejectingId = ref<number | null>(null);

const approvableCount = computed(
    () => props.pending.filter((row) => !row.blocked_reason).length,
);

const formatDate = (date: string | null): string =>
    date
        ? new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

const formatDateTime = (value: string | null): string =>
    value
        ? new Date(value).toLocaleString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

const formatTimeRange = (row: DeletionRequestRow): string => {
    if (!row.lesson_start_time) return '';

    return row.lesson_end_time
        ? `${row.lesson_start_time}–${row.lesson_end_time}`
        : row.lesson_start_time;
};

const pupilLink = (row: DeletionRequestRow): string | null =>
    row.student_id && row.instructor_id
        ? `/instructors/${row.instructor_id}?tab=student&student=${row.student_id}&subtab=lessons`
        : null;

const statusVariant = (
    status: DeletionRequestRow['status'],
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'approved') return 'destructive';
    if (status === 'rejected') return 'secondary';

    return 'outline';
};

const reloadLists = (): void => {
    router.reload({ only: ['pending', 'history', 'lessonDeletionRequests'] });
};

const errorMessage = (error: any, fallback: string): string =>
    error.response?.data?.message || fallback;

const handleApprove = async (): Promise<void> => {
    if (!approveTarget.value) return;

    approving.value = true;

    try {
        const response = await axios.post(approve.url(approveTarget.value.id));
        toast.success(response.data.message);
        approveTarget.value = null;
        reloadLists();
    } catch (error: any) {
        toast.error(errorMessage(error, 'Failed to approve the request'));
        reloadLists();
    } finally {
        approving.value = false;
    }
};

const handleReject = async (row: DeletionRequestRow): Promise<void> => {
    rejectingId.value = row.id;

    try {
        const response = await axios.post(reject.url(row.id));
        toast.success(response.data.message);
        reloadLists();
    } catch (error: any) {
        toast.error(errorMessage(error, 'Failed to reject the request'));
        reloadLists();
    } finally {
        rejectingId.value = null;
    }
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Instructors', href: instructorsIndex().url },
            { title: 'Lesson Deletion Requests' },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Lesson Deletion Requests
                </h1>
                <p class="text-sm text-muted-foreground">
                    Instructors ask to delete past lessons logged by mistake,
                    such as duplicates or lessons that never happened. Approving
                    permanently deletes the lesson. Rejecting keeps it. Only
                    past lessons that are unpaid, not signed off and have no
                    payout can be deleted; each one is checked again when you
                    approve.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarX class="h-5 w-5" />
                        Pending requests
                        <Badge
                            :variant="
                                pending.length > 0 ? 'destructive' : 'secondary'
                            "
                            >{{ pending.length }}</Badge
                        >
                    </CardTitle>
                    <CardDescription v-if="pending.length > approvableCount">
                        {{ pending.length - approvableCount }} can no longer be
                        deleted and should be rejected.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table v-if="pending.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Pupil</TableHead>
                                <TableHead>Lesson</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Requested</TableHead>
                                <TableHead class="text-right"
                                    >Actions</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in pending" :key="row.id">
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="row.instructor_id"
                                        :href="`/instructors/${row.instructor_id}`"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{
                                            row.instructor_name ??
                                            'Unknown instructor'
                                        }}
                                    </Link>
                                    <span v-else>{{
                                        row.instructor_name ??
                                        'Unknown instructor'
                                    }}</span>
                                </TableCell>
                                <TableCell>
                                    <Link
                                        v-if="pupilLink(row)"
                                        :href="pupilLink(row)!"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{
                                            row.student_name ?? 'Unknown pupil'
                                        }}
                                    </Link>
                                    <span v-else>{{
                                        row.student_name ?? 'Unknown pupil'
                                    }}</span>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium">
                                        {{ formatDate(row.lesson_date) }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ formatTimeRange(row) }}
                                    </div>
                                    <div v-if="row.payment_mode" class="mt-1">
                                        <Badge
                                            variant="outline"
                                            class="capitalize"
                                            >{{ row.payment_mode }}</Badge
                                        >
                                    </div>
                                </TableCell>
                                <TableCell class="max-w-sm">
                                    <p class="text-sm whitespace-pre-line">
                                        {{ row.reason }}
                                    </p>
                                    <div
                                        v-if="row.blocked_reason"
                                        class="mt-2 flex items-start gap-1.5 text-sm text-destructive"
                                    >
                                        <AlertTriangle
                                            class="mt-0.5 h-4 w-4 shrink-0"
                                        />
                                        <span>{{ row.blocked_reason }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm whitespace-nowrap">{{
                                    formatDateTime(row.requested_at)
                                }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            class="min-w-[96px]"
                                            :disabled="
                                                !!row.blocked_reason ||
                                                rejectingId === row.id
                                            "
                                            @click="approveTarget = row"
                                        >
                                            <CheckCircle2 class="h-4 w-4" />
                                            Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="min-w-[88px]"
                                            :disabled="rejectingId !== null"
                                            @click="handleReject(row)"
                                        >
                                            <Loader2
                                                v-if="rejectingId === row.id"
                                                class="h-4 w-4 animate-spin"
                                            />
                                            <XCircle v-else class="h-4 w-4" />
                                            Reject
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-12 text-center"
                    >
                        <CheckCircle2
                            class="h-12 w-12 text-muted-foreground/50"
                        />
                        <p
                            class="mt-4 text-lg font-medium text-muted-foreground"
                        >
                            No pending requests
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground/70">
                            Requests appear here when an instructor asks to
                            delete a past lesson.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <History class="h-5 w-5" />
                        Recent decisions
                    </CardTitle>
                    <CardDescription
                        >The last 100 approved, rejected or withdrawn
                        requests.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Deferred data="history">
                        <template #fallback>
                            <div class="flex flex-col gap-2">
                                <Skeleton class="h-10 w-full" />
                                <Skeleton class="h-10 w-full" />
                                <Skeleton class="h-10 w-full" />
                            </div>
                        </template>

                        <Table v-if="history && history.length > 0">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Instructor</TableHead>
                                    <TableHead>Pupil</TableHead>
                                    <TableHead>Lesson</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead>Requested</TableHead>
                                    <TableHead>Outcome</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in history" :key="row.id">
                                    <TableCell class="font-medium">{{
                                        row.instructor_name ??
                                        'Unknown instructor'
                                    }}</TableCell>
                                    <TableCell>{{
                                        row.student_name ?? 'Unknown pupil'
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ formatDate(row.lesson_date) }}
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ formatTimeRange(row) }}
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="max-w-sm text-sm whitespace-pre-line"
                                        >{{ row.reason }}</TableCell
                                    >
                                    <TableCell
                                        class="text-sm whitespace-nowrap"
                                        >{{
                                            formatDateTime(row.requested_at)
                                        }}</TableCell
                                    >
                                    <TableCell>
                                        <Badge
                                            :variant="statusVariant(row.status)"
                                            >{{ row.status_label }}</Badge
                                        >
                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ formatDateTime(row.decided_at) }}
                                            <span v-if="row.decided_by">
                                                by {{ row.decided_by }}</span
                                            >
                                            <span
                                                v-else-if="
                                                    row.status === 'cancelled'
                                                "
                                            >
                                                by the instructor</span
                                            >
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p
                            v-else
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No decisions yet.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>
        </div>

        <Dialog
            :open="approveTarget !== null"
            @update:open="
                (value) => !value && !approving && (approveTarget = null)
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Trash2 class="h-5 w-5" />
                        Permanently delete this lesson?
                    </DialogTitle>
                    <DialogDescription>
                        {{ approveTarget?.student_name ?? 'The pupil' }}'s
                        lesson with
                        {{
                            approveTarget?.instructor_name ?? 'the instructor'
                        }}
                        on
                        {{ formatDate(approveTarget?.lesson_date ?? null) }}
                        <template v-if="approveTarget?.lesson_start_time"
                            >at {{ approveTarget?.lesson_start_time }}</template
                        >
                        will be permanently deleted, along with its unpaid
                        payment record and its past diary slot. This can't be
                        undone. The pupil is not emailed; the instructor gets a
                        push notification.
                    </DialogDescription>
                </DialogHeader>
                <div
                    v-if="approveTarget"
                    class="rounded-md border bg-muted/40 p-3 text-sm"
                >
                    <p class="font-medium">Instructor's reason</p>
                    <p class="mt-1 whitespace-pre-line text-muted-foreground">
                        {{ approveTarget.reason }}
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="approving"
                        @click="approveTarget = null"
                        >Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        class="min-w-[160px]"
                        :disabled="approving"
                        @click="handleApprove"
                    >
                        <Loader2
                            v-if="approving"
                            class="h-4 w-4 animate-spin"
                        />
                        <Trash2 v-else class="h-4 w-4" />
                        Approve and delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
