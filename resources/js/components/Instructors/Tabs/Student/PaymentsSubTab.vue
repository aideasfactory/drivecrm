<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
    CreditCard,
    PoundSterling,
    CheckCircle2,
    Clock,
    RotateCcw,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

interface Payment {
    id: number
    lesson_id: number
    lesson_date: string | null
    lesson_time: string | null
    package_name: string
    payment_mode: 'upfront' | 'weekly'
    amount_pence: number
    status: 'due' | 'paid' | 'refunded'
    due_date: string | null
    paid_at: string | null
    created_at: string | null
}

interface Props {
    studentId: number
}

const props = defineProps<Props>()

const payments = ref<Payment[]>([])
const loading = ref(true)

const paidPayments = computed(() => payments.value.filter((p) => p.status === 'paid'))
const duePayments = computed(() => payments.value.filter((p) => p.status === 'due'))
const refundedPayments = computed(() => payments.value.filter((p) => p.status === 'refunded'))

const totalPaidPence = computed(() => paidPayments.value.reduce((sum, p) => sum + p.amount_pence, 0))
const totalDuePence = computed(() => duePayments.value.reduce((sum, p) => sum + p.amount_pence, 0))
const totalRefundedPence = computed(() => refundedPayments.value.reduce((sum, p) => sum + p.amount_pence, 0))

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

const formatDateTime = (dateTimeString: string | null): string => {
    if (!dateTimeString) return '—'
    const date = new Date(dateTimeString)
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
        case 'due':
            return 'outline'
        case 'refunded':
            return 'destructive'
        default:
            return 'secondary'
    }
}

const statusIcon = (status: string) => {
    switch (status) {
        case 'paid':
            return CheckCircle2
        case 'due':
            return Clock
        case 'refunded':
            return RotateCcw
        default:
            return Clock
    }
}

const loadPayments = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/students/${props.studentId}/payments`)
        payments.value = response.data.payments || []
    } catch {
        toast.error('Failed to load payments')
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadPayments()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Summary Cards -->
        <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card v-for="n in 3" :key="n">
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <Skeleton class="h-4 w-4" />
                        <Skeleton class="h-8 w-20" />
                        <Skeleton class="h-4 w-24" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <CheckCircle2 class="h-4 w-4 text-muted-foreground" />
                        <p class="text-2xl font-bold">{{ formatCurrency(totalPaidPence) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ paidPayments.length }} payment{{ paidPayments.length !== 1 ? 's' : '' }} paid
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <Clock class="h-4 w-4 text-muted-foreground" />
                        <p class="text-2xl font-bold">{{ formatCurrency(totalDuePence) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ duePayments.length }} payment{{ duePayments.length !== 1 ? 's' : '' }} due
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <RotateCcw class="h-4 w-4 text-muted-foreground" />
                        <p class="text-2xl font-bold">{{ formatCurrency(totalRefundedPence) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ refundedPayments.length }} payment{{ refundedPayments.length !== 1 ? 's' : '' }} refunded
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Payments Table -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CreditCard class="h-5 w-5" />
                    Payment History
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Loading Skeleton -->
                <div v-if="loading" class="space-y-3">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton v-for="n in 5" :key="n" class="h-12 w-full" />
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="payments.length === 0"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <CreditCard class="h-10 w-10" />
                    <div class="text-center">
                        <p class="font-medium">No payments yet</p>
                        <p class="mt-1 text-sm">Payment records will appear here once lessons are booked</p>
                    </div>
                </div>

                <!-- Payments Table -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Lesson Date</TableHead>
                            <TableHead>Package</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Due Date</TableHead>
                            <TableHead>Paid At</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="payment in payments" :key="payment.id">
                            <TableCell class="font-medium">
                                {{ formatDate(payment.lesson_date) }}
                                <span v-if="payment.lesson_time" class="ml-1 text-muted-foreground text-xs">
                                    {{ payment.lesson_time }}
                                </span>
                            </TableCell>
                            <TableCell>
                                {{ payment.package_name }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary" class="gap-1">
                                    <PoundSterling class="h-3 w-3" />
                                    {{ payment.payment_mode === 'upfront' ? 'Upfront' : 'Weekly' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ formatCurrency(payment.amount_pence) }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="statusBadgeVariant(payment.status)" class="gap-1">
                                    <component
                                        :is="statusIcon(payment.status)"
                                        class="h-3 w-3"
                                    />
                                    {{ payment.status.charAt(0).toUpperCase() + payment.status.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ formatDate(payment.due_date) }}
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(payment.paid_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
