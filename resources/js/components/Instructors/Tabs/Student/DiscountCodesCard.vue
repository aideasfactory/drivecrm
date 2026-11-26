<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { toast } from '@/components/ui/sonner'
import { Tag } from 'lucide-vue-next'
import { discounts as studentDiscounts } from '@/routes/students'

interface DiscountedOrder {
    order_id: number
    package: string | null
    discount_code: string | null
    discount_summary: string | null
    discount_pence: number
    total_price_pence: number
    status: 'pending' | 'active' | 'completed' | 'cancelled' | 'expired' | null
    booked_at: string | null
}

interface Props {
    studentId: number
}

const props = defineProps<Props>()

const orders = ref<DiscountedOrder[]>([])
const loading = ref(true)

const formatDate = (date: string | null): string =>
    date ? new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'

const formatCurrency = (pence: number): string =>
    new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(pence / 100)

const statusLabel = (status: DiscountedOrder['status']): string => {
    switch (status) {
        case 'pending':
            return 'Awaiting payment'
        case 'expired':
            return 'Not paid'
        case null:
            return '—'
        default:
            return status.charAt(0).toUpperCase() + status.slice(1)
    }
}

const loadOrders = async () => {
    loading.value = true
    try {
        const response = await axios.get(studentDiscounts.url(props.studentId))
        orders.value = response.data.orders || []
    } catch {
        toast.error('Failed to load discount codes')
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadOrders()
})
</script>

<template>
    <Skeleton v-if="loading" class="h-24 w-full" />
    <Card v-else-if="orders.length > 0">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Tag class="h-5 w-5" />
                Discount Codes Used
            </CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Booked</TableHead>
                        <TableHead>Package</TableHead>
                        <TableHead>Code</TableHead>
                        <TableHead class="text-right">Discount</TableHead>
                        <TableHead class="text-right">Total Charged</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="order in orders" :key="order.order_id">
                        <TableCell class="text-sm">
                            {{ formatDate(order.booked_at) }}
                            <div class="text-xs text-muted-foreground">Booking #{{ order.order_id }}</div>
                        </TableCell>
                        <TableCell class="text-sm">{{ order.package ?? '—' }}</TableCell>
                        <TableCell>
                            <span class="font-mono text-sm font-semibold">{{ order.discount_code }}</span>
                            <div v-if="order.discount_summary" class="text-xs text-muted-foreground">
                                {{ order.discount_summary }}
                            </div>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">-{{ formatCurrency(order.discount_pence) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatCurrency(order.total_price_pence) }}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{{ statusLabel(order.status) }}</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
