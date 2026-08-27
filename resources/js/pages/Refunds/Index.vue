<script setup lang="ts">
import { watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { toast } from '@/components/ui/sonner'
import {
    Banknote,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Loader2,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { show as instructorsShow } from '@/routes/instructors'
import {
    complete as refundsComplete,
    index as refundsIndex,
    process as refundsProcess,
} from '@/routes/refunds'

interface RefundRow {
    id: number
    status: 'pending' | 'completed'
    method: 'stripe' | 'manual' | null
    amount_pence: number
    formatted_amount: string
    reason: string | null
    stripe_refund_id: string | null
    requested_at: string | null
    completed_at: string | null
    paper_trail: string | null
    requested_by: string | null
    processed_by: string | null
    student: {
        id: number | null
        name: string
    }
    instructor: {
        id: number | null
        name: string | null
    }
    lesson: {
        id: number | null
        date: string | null
        time: string | null
    }
    order_id: number | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Paginator {
    data: RefundRow[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
    links: PaginationLink[]
}

interface Totals {
    pending_count: number
    pending_amount_pence: number
    completed_count: number
    completed_amount_pence: number
    requested_count: number
    requested_amount_pence: number
}

interface Filters {
    status: 'all' | 'pending' | 'completed'
}

interface Props {
    refunds: Paginator
    totals: Totals
    filters: Filters
}

interface FlashProps {
    flash?: {
        success?: string | null
        error?: string | null
    }
}

const props = defineProps<Props>()
const page = usePage<FlashProps>()

const processingId = ref<number | null>(null)

watch(
    () => page.props.flash?.success,
    (value) => {
        if (value) {
            toast.success(value)
        }
    },
    { immediate: true },
)

watch(
    () => page.props.flash?.error,
    (value) => {
        if (value) {
            toast.error(value)
        }
    },
    { immediate: true },
)

const statusOptions: { value: Filters['status']; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'completed', label: 'Completed' },
]

const formatPence = (pence: number): string =>
    `£${(pence / 100).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const formatDateTime = (iso: string | null): string => {
    if (!iso) {
        return '—'
    }

    const date = new Date(iso)

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }) + ', ' + date.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatLesson = (row: RefundRow): string => {
    if (!row.lesson.date) {
        return '—'
    }

    const date = new Date(`${row.lesson.date}T00:00:00`)
    const dateLabel = date.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })

    return row.lesson.time ? `${dateLabel} · ${row.lesson.time}` : dateLabel
}

const applyFilter = (status: Filters['status']) => {
    router.get(
        refundsIndex.url(),
        { status },
        { preserveScroll: true, preserveState: true },
    )
}

const goToPage = (url: string | null) => {
    if (!url) {
        return
    }

    router.visit(url, { preserveScroll: true, preserveState: true })
}

const studentHref = (row: RefundRow): string | null => {
    if (!row.student.id || !row.instructor.id) {
        return null
    }

    return instructorsShow.url(row.instructor.id, {
        query: {
            tab: 'student',
            student: row.student.id,
            subtab: 'overview',
        },
    })
}

const submitAction = (row: RefundRow, action: 'process' | 'complete') => {
    processingId.value = row.id

    const url = action === 'process'
        ? refundsProcess.url(row.id)
        : refundsComplete.url(row.id)

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingId.value = null
            },
        },
    )
}

const emptyMessage = computed(() => {
    if (props.filters.status === 'pending') {
        return 'No pending refunds. Cancelled paid lessons will appear here for review.'
    }

    if (props.filters.status === 'completed') {
        return 'No completed refunds yet.'
    }

    return 'No refunds have been requested yet.'
})
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Refunds' }]">
        <Head title="Refunds" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Refunds</h1>
                <p class="text-sm text-muted-foreground">
                    Review refunds requested when a paid lesson is cancelled. Issue them through Stripe or mark them complete after refunding in Stripe by hand. Newest first.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Pending</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ formatPence(totals.pending_amount_pence) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ totals.pending_count }} request{{ totals.pending_count === 1 ? '' : 's' }} awaiting action
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Completed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ formatPence(totals.completed_amount_pence) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ totals.completed_count }} refund{{ totals.completed_count === 1 ? '' : 's' }} recorded
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Requested (running total)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ formatPence(totals.requested_amount_pence) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ totals.requested_count }} request{{ totals.requested_count === 1 ? '' : 's' }} in total
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="option in statusOptions"
                    :key="option.value"
                    :variant="filters.status === option.value ? 'default' : 'outline'"
                    size="sm"
                    @click="applyFilter(option.value)"
                >
                    {{ option.label }}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Banknote class="h-5 w-5" />
                        Refund requests
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Requested</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Lesson</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Paper trail</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in refunds.data" :key="row.id">
                                <TableCell class="whitespace-nowrap text-sm">
                                    {{ formatDateTime(row.requested_at) }}
                                    <p v-if="row.requested_by" class="text-xs text-muted-foreground">
                                        by {{ row.requested_by }}
                                    </p>
                                </TableCell>
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="studentHref(row)"
                                        :href="studentHref(row)!"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.student.name }}
                                    </Link>
                                    <span v-else>{{ row.student.name }}</span>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ formatLesson(row) }}
                                    <p v-if="row.reason" class="max-w-[240px] truncate text-xs text-muted-foreground" :title="row.reason">
                                        {{ row.reason }}
                                    </p>
                                </TableCell>
                                <TableCell class="text-sm">{{ row.instructor.name ?? '—' }}</TableCell>
                                <TableCell class="text-right font-medium">{{ row.formatted_amount }}</TableCell>
                                <TableCell>
                                    <Badge :variant="row.status === 'pending' ? 'destructive' : 'secondary'">
                                        {{ row.status === 'pending' ? 'Pending' : 'Completed' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="max-w-[220px] text-sm">
                                    <span v-if="row.paper_trail">{{ row.paper_trail }}</span>
                                    <span v-else class="text-muted-foreground">Awaiting action</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div v-if="row.status === 'pending'" class="flex justify-end gap-2">
                                        <Button
                                            size="sm"
                                            class="min-w-[120px]"
                                            :disabled="processingId === row.id"
                                            @click="submitAction(row, 'process')"
                                        >
                                            <Loader2 v-if="processingId === row.id" class="mr-2 h-4 w-4 animate-spin" />
                                            <CreditCard v-else class="mr-2 h-4 w-4" />
                                            Refund via Stripe
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="min-w-[140px]"
                                            :disabled="processingId === row.id"
                                            @click="submitAction(row, 'complete')"
                                        >
                                            <CheckCircle2 class="mr-2 h-4 w-4" />
                                            Mark complete
                                        </Button>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">
                                        {{ row.method === 'stripe' ? 'Issued in Stripe' : 'Marked complete' }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="refunds.data.length === 0">
                                <TableCell colspan="8" class="py-8 text-center text-muted-foreground">
                                    {{ emptyMessage }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="refunds.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ refunds.from ?? 0 }}–{{ refunds.to ?? 0 }} of {{ refunds.total }}
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!refunds.prev_page_url"
                                @click="goToPage(refunds.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <div class="text-sm">
                                Page {{ refunds.current_page }} of {{ refunds.last_page }}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!refunds.next_page_url"
                                @click="goToPage(refunds.next_page_url)"
                            >
                                Next
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
