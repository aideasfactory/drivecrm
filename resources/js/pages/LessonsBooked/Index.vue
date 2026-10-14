<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
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
import { ChevronLeft, ChevronRight, Mail, Phone } from 'lucide-vue-next'
import { show as instructorsShow } from '@/routes/instructors'
import { index, refund } from '@/routes/lessons-booked'
import ManualRefundToggle from '@/components/Shared/ManualRefundToggle.vue'
import { useRole } from '@/composables/useRole'

type PaymentState = 'unpaid' | 'paid' | 'expired' | 'cancelled'

interface BookedLesson {
    id: number
    invoice_sent_at: string | null
    student_name: string
    student_id: number | null
    postcode: string | null
    lesson_date: string | null
    lesson_time: string | null
    email: string | null
    telephone: string | null
    instructor_name: string | null
    instructor_id: number | null
    package: string
    payment_state: PaymentState
    can_mark_refunded: boolean
    manually_refunded_at: string | null
    manually_refunded_by_name: string | null
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Paginator {
    data: BookedLesson[]
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

type Counts = Record<'all' | PaymentState, number>

interface Filters {
    payment: 'all' | PaymentState
}

interface Props {
    bookings: Paginator
    counts: Counts
    filters: Filters
}

const props = defineProps<Props>()

const paymentOptions: { value: Filters['payment']; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'unpaid', label: 'Unpaid' },
    { value: 'paid', label: 'Paid' },
    { value: 'expired', label: 'Expired' },
    { value: 'cancelled', label: 'Cancelled' },
]

const paymentBadges: Record<PaymentState, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    unpaid: { label: 'Unpaid', variant: 'destructive' },
    paid: { label: 'Paid', variant: 'secondary' },
    expired: { label: 'Expired', variant: 'outline' },
    cancelled: { label: 'Cancelled', variant: 'outline' },
}

const applyPayment = (payment: Filters['payment']) => {
    router.get(index.url({ query: { payment } }), {}, { preserveScroll: true, preserveState: true })
}

const goToPage = (url: string | null) => {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
}

const formatInvoiceSent = (iso: string | null) => {
    if (!iso) return '—'
    const date = new Date(iso)
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
        ', ' + date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

const formatLessonDate = (date: string | null, time: string | null) => {
    if (!date) return '—'
    const [year, month, day] = date.split('-').map(Number)
    if (!year || !month || !day) return date
    const label = new Date(year, month - 1, day).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
    return time ? `${label}, ${time}` : label
}

const studentHref = (row: BookedLesson) => {
    if (!row.student_id || !row.instructor_id) return null
    return instructorsShow.url(row.instructor_id, {
        query: { tab: 'student', student: row.student_id, subtab: 'overview' },
    })
}

const { isOwner } = useRole()

const reloadBookings = () => {
    router.reload({ only: ['bookings'] })
}

const breadcrumbs = [{ title: 'Lessons booked' }]
</script>

<template>
    <Head title="Lessons booked" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Lessons booked</h2>
                <p class="text-muted-foreground">
                    Lessons booked by the bookings team. Unpaid rows are highlighted so payment can be chased. Expired bookings ran out of time to pay and their lessons were released.
                </p>
            </div>

            <Card>
                <CardContent class="pt-6">
                    <div class="mb-4 flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-1 rounded-md border p-1">
                            <Button
                                v-for="option in paymentOptions"
                                :key="option.value"
                                :variant="filters.payment === option.value ? 'default' : 'ghost'"
                                size="sm"
                                @click="applyPayment(option.value)"
                            >
                                {{ option.label }}
                                <Badge
                                    :variant="option.value === 'unpaid' ? 'destructive' : 'secondary'"
                                    class="ml-2"
                                >
                                    {{ counts[option.value] }}
                                </Badge>
                            </Button>
                        </div>
                        <p class="ml-auto text-sm text-muted-foreground">
                            Newest invoice first
                        </p>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice sent</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Postcode</TableHead>
                                <TableHead>Lesson date</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Telephone</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Package (hours)</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead>Refunded outside system</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in bookings.data"
                                :key="row.id"
                                :class="{
                                    'bg-destructive/10 hover:bg-destructive/15': row.payment_state === 'unpaid',
                                    'text-muted-foreground': row.payment_state === 'expired' || row.payment_state === 'cancelled',
                                }"
                            >
                                <TableCell class="whitespace-nowrap text-sm">
                                    {{ formatInvoiceSent(row.invoice_sent_at) }}
                                </TableCell>
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="studentHref(row)"
                                        :href="studentHref(row) || ''"
                                        class="hover:text-primary hover:underline"
                                    >
                                        {{ row.student_name }}
                                    </Link>
                                    <span v-else>{{ row.student_name }}</span>
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-sm">
                                    {{ row.postcode ?? '—' }}
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-sm">
                                    {{ formatLessonDate(row.lesson_date, row.lesson_time) }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    <a
                                        v-if="row.email"
                                        :href="`mailto:${row.email}`"
                                        class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                    >
                                        <Mail class="h-3.5 w-3.5" />
                                        {{ row.email }}
                                    </a>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-sm">
                                    <a
                                        v-if="row.telephone"
                                        :href="`tel:${row.telephone}`"
                                        class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                                    >
                                        <Phone class="h-3.5 w-3.5" />
                                        {{ row.telephone }}
                                    </a>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ row.instructor_name ?? '—' }}
                                </TableCell>
                                <TableCell class="text-sm">
                                    {{ row.package }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="paymentBadges[row.payment_state].variant">
                                        {{ paymentBadges[row.payment_state].label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <ManualRefundToggle
                                        :id="`booking-refund-${row.id}`"
                                        :url="refund.url({ order: row.id })"
                                        :refunded-at="row.manually_refunded_at"
                                        :refunded-by-name="row.manually_refunded_by_name"
                                        :subject="`booking #${row.id} for ${row.student_name}`"
                                        :can-edit="isOwner && row.can_mark_refunded"
                                        @updated="reloadBookings"
                                    />
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="bookings.data.length === 0">
                                <TableCell colspan="10" class="py-8 text-center text-muted-foreground">
                                    No lessons booked match this filter.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="bookings.last_page > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ bookings.from ?? 0 }}–{{ bookings.to ?? 0 }} of {{ bookings.total }}
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!bookings.prev_page_url"
                                @click="goToPage(bookings.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <div class="text-sm">
                                Page {{ bookings.current_page }} of {{ bookings.last_page }}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!bookings.next_page_url"
                                @click="goToPage(bookings.next_page_url)"
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
