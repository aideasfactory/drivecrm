<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    PoundSterling,
    CheckCircle2,
    Clock,
    AlertTriangle,
    BarChart3,
    CreditCard,
    TrendingUp,
    Banknote,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import type { InstructorDetail, InstructorPayout } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

type FilterType = 'all' | 'paid' | 'pending'

const payouts = ref<InstructorPayout[]>([])
const loading = ref(true)
const activeFilter = ref<FilterType>('all')

const filteredPayouts = computed(() => {
    if (activeFilter.value === 'all') return payouts.value
    return payouts.value.filter((p) => p.status === activeFilter.value)
})

const summaryCards = computed(() => {
    const filtered = filteredPayouts.value
    const totalAmountPence = filtered.reduce((sum, p) => sum + p.amount_pence, 0)
    const paidAmountPence = filtered.filter((p) => p.status === 'paid').reduce((sum, p) => sum + p.amount_pence, 0)
    const pendingAmountPence = filtered.filter((p) => p.status === 'pending').reduce((sum, p) => sum + p.amount_pence, 0)

    return [
        {
            title: 'Total Payouts',
            value: filtered.length.toString(),
            icon: CreditCard,
        },
        {
            title: 'Total Amount',
            value: formatCurrency(totalAmountPence),
            icon: Banknote,
        },
        {
            title: 'Paid',
            value: formatCurrency(paidAmountPence),
            icon: CheckCircle2,
        },
        {
            title: 'Pending',
            value: formatCurrency(pendingAmountPence),
            icon: Clock,
        },
    ]
})

const formatCurrency = (pence: number): string => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(pence / 100)
}

const formatDate = (dateString: string | null): string => {
    if (!dateString) return '—'
    const date = new Date(dateString + 'T00:00:00')
    return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

const formatTime = (start: string | null, end: string | null): string => {
    if (!start) return '—'
    if (!end) return start
    return `${start} – ${end}`
}

const formatDateTime = (isoString: string | null): string => {
    if (!isoString) return '—'
    const date = new Date(isoString)
    return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const statusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'paid':
            return 'default'
        case 'pending':
            return 'secondary'
        case 'failed':
            return 'destructive'
        default:
            return 'outline'
    }
}

const statusIcon = (status: string) => {
    switch (status) {
        case 'paid':
            return CheckCircle2
        case 'pending':
            return Clock
        case 'failed':
            return AlertTriangle
        default:
            return Clock
    }
}

const loadPayouts = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructor.id}/payouts`)
        payouts.value = response.data.payouts || []
    } catch {
        toast.error('Failed to load payouts')
    } finally {
        loading.value = false
    }
}

const setFilter = (filter: FilterType) => {
    activeFilter.value = filter
}

onMounted(() => {
    loadPayouts()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Summary Cards - Loading -->
        <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="n in 4" :key="n">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <Skeleton class="h-4 w-24" />
                    <Skeleton class="h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <Skeleton class="h-8 w-20" />
                </CardContent>
            </Card>
        </div>

        <!-- Summary Cards -->
        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="card in summaryCards" :key="card.title">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ card.title }}
                    </CardTitle>
                    <component :is="card.icon" class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ card.value }}</div>
                </CardContent>
            </Card>
        </div>

        <!-- Filter Buttons + Table -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5" />
                        Payment History
                    </CardTitle>
                    <div class="flex gap-1">
                        <Button
                            :variant="activeFilter === 'all' ? 'default' : 'outline'"
                            size="sm"
                            @click="setFilter('all')"
                        >
                            All
                        </Button>
                        <Button
                            :variant="activeFilter === 'paid' ? 'default' : 'outline'"
                            size="sm"
                            @click="setFilter('paid')"
                            class="gap-1"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Paid
                        </Button>
                        <Button
                            :variant="activeFilter === 'pending' ? 'default' : 'outline'"
                            size="sm"
                            @click="setFilter('pending')"
                            class="gap-1"
                        >
                            <Clock class="h-3.5 w-3.5" />
                            Pending
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <!-- Loading Skeleton -->
                <div v-if="loading" class="space-y-3">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton v-for="n in 5" :key="n" class="h-12 w-full" />
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="filteredPayouts.length === 0"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <BarChart3 class="h-10 w-10" />
                    <div class="text-center">
                        <p class="font-medium">
                            {{ activeFilter === 'all' ? 'No payouts yet' : `No ${activeFilter} payouts` }}
                        </p>
                        <p class="mt-1 text-sm">
                            {{ activeFilter === 'all'
                                ? 'Payouts will appear here once lessons are signed off'
                                : `No payouts with status "${activeFilter}" found` }}
                        </p>
                    </div>
                </div>

                <!-- Payouts Table -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Lesson Date</TableHead>
                            <TableHead>Time</TableHead>
                            <TableHead>Package</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Paid At</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="payout in filteredPayouts" :key="payout.id">
                            <TableCell class="font-medium">
                                {{ payout.student_name || '—' }}
                            </TableCell>
                            <TableCell>
                                {{ formatDate(payout.lesson_date) }}
                            </TableCell>
                            <TableCell>
                                {{ formatTime(payout.lesson_start_time, payout.lesson_end_time) }}
                            </TableCell>
                            <TableCell>
                                {{ payout.package_name || '—' }}
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ payout.formatted_amount }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="statusBadgeVariant(payout.status)" class="gap-1">
                                    <component :is="statusIcon(payout.status)" class="h-3 w-3" />
                                    {{ payout.status.charAt(0).toUpperCase() + payout.status.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(payout.paid_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
