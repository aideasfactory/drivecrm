<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { ChevronLeft, ChevronRight, Flag, Mail, Phone, X } from 'lucide-vue-next'
import { show as instructorsShow } from '@/routes/instructors'
import { index } from '@/routes/lessons-booked'
import { clashes as clashesReport } from '@/routes/reports'

type PaymentState = 'unpaid' | 'paid' | 'expired' | 'cancelled'
type Tab = 'staff' | 'all'

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
    booked_at: string | null
    source: string | null
    source_label: string
    booked_by_name: string | null
    /** Lessons in this booking that clash with the diary and await rearranging by the DRIVE team. */
    provisional_lessons_count: number
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
    tab: Tab
    payment: 'all' | PaymentState
    from: string | null
    to: string | null
}

interface Props {
    bookings: Paginator
    counts: Counts
    filters: Filters
}

const props = defineProps<Props>()

const tabOptions: { value: Tab; label: string }[] = [
    { value: 'staff', label: 'Bookings team' },
    { value: 'all', label: 'All bookings' },
]

const isAllTab = computed(() => props.filters.tab === 'all')
const bookedFrom = ref(props.filters.from ?? '')
const bookedTo = ref(props.filters.to ?? '')
const hasDateFilter = computed(() => bookedFrom.value !== '' || bookedTo.value !== '')

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

const applyFilters = (overrides: Partial<Filters> = {}) => {
    const next = { ...props.filters, from: bookedFrom.value || null, to: bookedTo.value || null, ...overrides }
    const query: Record<string, string> = { tab: next.tab, payment: next.payment }

    if (next.tab === 'all') {
        if (next.from) query.from = next.from
        if (next.to) query.to = next.to
    }

    router.get(index.url({ query }), {}, { preserveScroll: true, preserveState: true })
}

const switchTab = (tab: Tab) => {
    bookedFrom.value = ''
    bookedTo.value = ''
    applyFilters({ tab, payment: 'all', from: null, to: null })
}

const clearDates = () => {
    bookedFrom.value = ''
    bookedTo.value = ''
    applyFilters({ from: null, to: null })
}

const goToPage = (url: string | null) => {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
}

const formatDateTime = (iso: string | null) => {
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

const breadcrumbs = [{ title: 'Lessons booked' }]
</script>

<template>
    <Head title="Lessons booked" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Lessons booked</h2>
                <p class="text-muted-foreground">
                    <template v-if="isAllTab">
                        Every booking, whether it came from the website, the bookings team, the CRM or the apps. Unpaid rows are highlighted so payment can be chased.
                    </template>
                    <template v-else>
                        Lessons booked by the bookings team. Unpaid rows are highlighted so payment can be chased.
                    </template>
                    Expired bookings ran out of time to pay and their lessons were released.
                </p>
            </div>

            <div class="flex items-center gap-1 self-start rounded-md border p-1">
                <Button
                    v-for="tab in tabOptions"
                    :key="tab.value"
                    :variant="filters.tab === tab.value ? 'default' : 'ghost'"
                    size="sm"
                    @click="switchTab(tab.value)"
                >
                    {{ tab.label }}
                </Button>
            </div>

            <Card>
                <CardContent class="pt-6">
                    <div class="mb-4 flex flex-wrap items-end gap-4">
                        <div class="flex items-center gap-1 rounded-md border p-1">
                            <Button
                                v-for="option in paymentOptions"
                                :key="option.value"
                                :variant="filters.payment === option.value ? 'default' : 'ghost'"
                                size="sm"
                                @click="applyFilters({ payment: option.value })"
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
                        <template v-if="isAllTab">
                            <div class="flex flex-col gap-1.5">
                                <Label for="booked-from" class="text-xs text-muted-foreground">Booked from</Label>
                                <Input id="booked-from" v-model="bookedFrom" type="date" class="w-40" @change="applyFilters()" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label for="booked-to" class="text-xs text-muted-foreground">Booked to</Label>
                                <Input id="booked-to" v-model="bookedTo" type="date" class="w-40" @change="applyFilters()" />
                            </div>
                            <Button v-if="hasDateFilter" variant="ghost" size="sm" @click="clearDates">
                                <X class="h-4 w-4" />
                                Clear dates
                            </Button>
                        </template>
                        <p class="ml-auto text-sm text-muted-foreground">
                            {{ isAllTab ? 'Newest booking first' : 'Newest invoice first' }}
                        </p>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ isAllTab ? 'Booked' : 'Invoice sent' }}</TableHead>
                                <TableHead v-if="isAllTab">Source</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Postcode</TableHead>
                                <TableHead>Lesson date</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Telephone</TableHead>
                                <TableHead>Instructor</TableHead>
                                <TableHead>Package (hours)</TableHead>
                                <TableHead>Payment</TableHead>
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
                                    {{ formatDateTime(isAllTab ? row.booked_at : row.invoice_sent_at) }}
                                </TableCell>
                                <TableCell v-if="isAllTab" class="whitespace-nowrap text-sm">
                                    <Badge variant="outline">{{ row.source_label }}</Badge>
                                    <div v-if="row.booked_by_name" class="mt-1 text-xs text-muted-foreground">
                                        by {{ row.booked_by_name }}
                                    </div>
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
                                    <Link v-if="row.provisional_lessons_count > 0" :href="clashesReport().url" class="mt-1 block w-fit">
                                        <Badge variant="destructive">
                                            <Flag />
                                            {{ row.provisional_lessons_count }}
                                            {{ row.provisional_lessons_count === 1 ? 'clash' : 'clashes' }} — provisional
                                        </Badge>
                                    </Link>
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
                            </TableRow>
                            <TableRow v-if="bookings.data.length === 0">
                                <TableCell :colspan="isAllTab ? 10 : 9" class="py-8 text-center text-muted-foreground">
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
