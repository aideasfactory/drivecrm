<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { toast } from '@/components/ui/sonner';
import { CalendarClock, CheckCircle2, Download, Loader2, Mail, Phone } from 'lucide-vue-next';
import { index as reportsIndex } from '@/routes/reports';
import { confirm as confirmClash, exportMethod as exportClashes } from '@/routes/reports/clashes';

interface ClashRow {
    lesson_id: number;
    student_id: number | null;
    instructor_id: number | null;
    learner_name: string;
    learner_phone: string | null;
    learner_email: string | null;
    instructor_name: string | null;
    lesson_date: string;
    lesson_time: string;
    package: string | null;
    booked_at: string | null;
    booked_by: string;
    handled_by: string;
    awaiting_payment: boolean;
    clashes_with: string[];
}

interface Props {
    report: {
        rows: ClashRow[];
        generated_at: string;
    };
}

defineProps<Props>();

const exportUrl = exportClashes.url();

const confirmTarget = ref<ClashRow | null>(null);
const confirming = ref(false);

const formatDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const handleConfirm = async (): Promise<void> => {
    if (!confirmTarget.value) return;

    confirming.value = true;

    try {
        const response = await axios.post(confirmClash.url(confirmTarget.value.lesson_id));
        toast.success(response.data.message);
        confirmTarget.value = null;
        router.reload({ only: ['report'] });
    } catch (error: any) {
        const message =
            error.response?.data?.errors?.lesson?.[0] || error.response?.data?.message || 'Failed to confirm lesson';
        toast.error(message);
    } finally {
        confirming.value = false;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: reportsIndex().url }, { title: 'Clashes' }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Clashes</h1>
                    <p class="text-sm text-muted-foreground">
                        Provisional lessons booked onto a time already taken in the instructor's diary. Contact the
                        learner and move or cancel the lesson — it drops off this list once it's resolved. Clashes an
                        instructor booked themselves are theirs to rearrange.
                    </p>
                </div>
                <Button v-if="report.rows.length > 0" as="a" :href="exportUrl" variant="outline">
                    <Download class="h-4 w-4" />
                    Download CSV
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarClock class="h-5 w-5" />
                        Provisional lessons ({{ report.rows.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table v-if="report.rows.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Learner</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Clash</TableHead>
                                <TableHead>Clashes With</TableHead>
                                <TableHead>Package</TableHead>
                                <TableHead>Booked</TableHead>
                                <TableHead>Handled By</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in report.rows" :key="row.lesson_id">
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="row.student_id && row.instructor_id"
                                        :href="`/instructors/${row.instructor_id}?tab=student&student=${row.student_id}&subtab=lessons`"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.learner_name }}
                                    </Link>
                                    <span v-else>{{ row.learner_name }}</span>
                                    <div v-if="row.awaiting_payment" class="mt-1">
                                        <Badge variant="outline">Awaiting payment</Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1 text-sm">
                                        <a
                                            v-if="row.learner_phone"
                                            :href="`tel:${row.learner_phone}`"
                                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <Phone class="h-3.5 w-3.5" />
                                            {{ row.learner_phone }}
                                        </a>
                                        <a
                                            v-if="row.learner_email"
                                            :href="`mailto:${row.learner_email}`"
                                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <Mail class="h-3.5 w-3.5" />
                                            {{ row.learner_email }}
                                        </a>
                                        <span v-if="!row.learner_phone && !row.learner_email" class="text-muted-foreground/60">
                                            No contact details
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>{{ row.instructor_name ?? '—' }}</TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ formatDate(row.lesson_date) }}</div>
                                    <div class="text-sm text-muted-foreground">{{ row.lesson_time }}</div>
                                </TableCell>
                                <TableCell>
                                    <div v-if="row.clashes_with.length > 0" class="flex flex-col gap-1 text-sm">
                                        <span v-for="clash in row.clashes_with" :key="clash">{{ clash }}</span>
                                    </div>
                                    <Badge v-else variant="secondary">No longer clashing</Badge>
                                </TableCell>
                                <TableCell class="text-sm">{{ row.package ?? '—' }}</TableCell>
                                <TableCell>
                                    <div class="text-sm">{{ formatDate(row.booked_at) }}</div>
                                    <div class="text-xs text-muted-foreground">by {{ row.booked_by }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="row.handled_by === 'Instructor' ? 'secondary' : 'outline'">{{ row.handled_by }}</Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button size="sm" variant="outline" @click="confirmTarget = row">
                                        <CheckCircle2 class="h-4 w-4" />
                                        Mark confirmed
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle2 class="h-12 w-12 text-muted-foreground/50" />
                        <p class="mt-4 text-lg font-medium text-muted-foreground">No clashes</p>
                        <p class="mt-1 text-sm text-muted-foreground/70">Every booked lesson has its own time.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog :open="confirmTarget !== null" @update:open="(value) => !value && !confirming && (confirmTarget = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <CheckCircle2 class="h-5 w-5" />
                        Keep this lesson where it is?
                    </DialogTitle>
                    <DialogDescription>
                        {{ confirmTarget?.learner_name }}'s lesson on {{ formatDate(confirmTarget?.lesson_date ?? null) }} at
                        {{ confirmTarget?.lesson_time }} stays in the diary as a normal booking and is no longer marked
                        provisional. Only do this if the clash has gone or everyone has agreed. To change the time instead,
                        move the lesson from the instructor's diary.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" :disabled="confirming" @click="confirmTarget = null">Cancel</Button>
                    <Button :disabled="confirming" @click="handleConfirm">
                        <Loader2 v-if="confirming" class="h-4 w-4 animate-spin" />
                        Mark confirmed
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
