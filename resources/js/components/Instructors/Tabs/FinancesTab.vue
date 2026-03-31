<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
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
    Plus,
    Pencil,
    Trash2,
    ArrowDownCircle,
    ArrowUpCircle,
    Banknote,
    Receipt,
    RefreshCw,
    Loader2,
    Wallet,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import type { InstructorDetail } from '@/types/instructor'

interface InstructorFinance {
    id: number
    type: 'payment' | 'expense'
    description: string
    amount_pence: number
    formatted_amount: string
    is_recurring: boolean
    recurrence_frequency: string | null
    date: string
    notes: string | null
    created_at: string | null
}

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

type FilterType = 'all' | 'payment' | 'expense'

const finances = ref<InstructorFinance[]>([])
const loading = ref(true)
const activeFilter = ref<FilterType>('all')
const isSheetOpen = ref(false)
const isSubmitting = ref(false)
const editingFinance = ref<InstructorFinance | null>(null)

const errors = ref<Record<string, string>>({})

const form = ref({
    type: 'payment' as 'payment' | 'expense',
    description: '',
    amount: '',
    is_recurring: false,
    recurrence_frequency: '' as string,
    date: new Date().toISOString().split('T')[0],
    notes: '',
})

const filteredFinances = computed(() => {
    if (activeFilter.value === 'all') return finances.value
    return finances.value.filter((f) => f.type === activeFilter.value)
})

const summaryCards = computed(() => {
    const all = finances.value
    const totalPayments = all.filter((f) => f.type === 'payment').reduce((sum, f) => sum + f.amount_pence, 0)
    const totalExpenses = all.filter((f) => f.type === 'expense').reduce((sum, f) => sum + f.amount_pence, 0)
    const netBalance = totalPayments - totalExpenses

    return [
        {
            title: 'Total Records',
            value: all.length.toString(),
            icon: Wallet,
        },
        {
            title: 'Total Payments',
            value: formatCurrency(totalPayments),
            icon: ArrowDownCircle,
        },
        {
            title: 'Total Expenses',
            value: formatCurrency(totalExpenses),
            icon: ArrowUpCircle,
        },
        {
            title: 'Net Balance',
            value: formatCurrency(netBalance),
            icon: PoundSterling,
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

const resetForm = () => {
    form.value = {
        type: 'payment',
        description: '',
        amount: '',
        is_recurring: false,
        recurrence_frequency: '',
        date: new Date().toISOString().split('T')[0],
        notes: '',
    }
    errors.value = {}
    editingFinance.value = null
}

const openCreateSheet = () => {
    resetForm()
    isSheetOpen.value = true
}

const openEditSheet = (finance: InstructorFinance) => {
    editingFinance.value = finance
    form.value = {
        type: finance.type,
        description: finance.description,
        amount: (finance.amount_pence / 100).toFixed(2),
        is_recurring: finance.is_recurring,
        recurrence_frequency: finance.recurrence_frequency || '',
        date: finance.date,
        notes: finance.notes || '',
    }
    errors.value = {}
    isSheetOpen.value = true
}

const handleSheetChange = (open: boolean) => {
    if (!open && isSubmitting.value) return
    isSheetOpen.value = open
    if (!open) resetForm()
}

const loadFinances = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructor.id}/finances`)
        finances.value = response.data.finances || []
    } catch {
        toast.error('Failed to load finances')
    } finally {
        loading.value = false
    }
}

const handleSubmit = async () => {
    errors.value = {}
    isSubmitting.value = true

    const amountPence = Math.round(parseFloat(form.value.amount || '0') * 100)

    const payload: Record<string, unknown> = {
        type: form.value.type,
        description: form.value.description,
        amount_pence: amountPence,
        is_recurring: form.value.is_recurring,
        recurrence_frequency: form.value.is_recurring ? form.value.recurrence_frequency : null,
        date: form.value.date,
        notes: form.value.notes || null,
    }

    try {
        if (editingFinance.value) {
            const response = await axios.put(
                `/instructors/${props.instructor.id}/finances/${editingFinance.value.id}`,
                payload,
            )
            const index = finances.value.findIndex((f) => f.id === editingFinance.value!.id)
            if (index !== -1) {
                finances.value[index] = response.data.finance
            }
            toast.success('Finance record updated successfully')
        } else {
            const response = await axios.post(
                `/instructors/${props.instructor.id}/finances`,
                payload,
            )
            finances.value.unshift(response.data.finance)
            toast.success('Finance record created successfully')
        }
        isSheetOpen.value = false
        resetForm()
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {}
            for (const key in validationErrors) {
                errors.value[key] = validationErrors[key][0]
            }
        } else {
            toast.error(error.response?.data?.message || 'Failed to save finance record')
        }
    } finally {
        isSubmitting.value = false
    }
}

const deleteFinance = async (finance: InstructorFinance) => {
    if (!confirm('Are you sure you want to delete this finance record?')) return

    try {
        await axios.delete(`/instructors/${props.instructor.id}/finances/${finance.id}`)
        finances.value = finances.value.filter((f) => f.id !== finance.id)
        toast.success('Finance record deleted successfully')
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to delete finance record')
    }
}

const setFilter = (filter: FilterType) => {
    activeFilter.value = filter
}

onMounted(() => {
    loadFinances()
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
                        <Banknote class="h-5 w-5" />
                        Finances
                    </CardTitle>
                    <div class="flex gap-2">
                        <div class="flex gap-1">
                            <Button
                                :variant="activeFilter === 'all' ? 'default' : 'outline'"
                                size="sm"
                                @click="setFilter('all')"
                            >
                                All
                            </Button>
                            <Button
                                :variant="activeFilter === 'payment' ? 'default' : 'outline'"
                                size="sm"
                                @click="setFilter('payment')"
                                class="gap-1"
                            >
                                <ArrowDownCircle class="h-3.5 w-3.5" />
                                Payments
                            </Button>
                            <Button
                                :variant="activeFilter === 'expense' ? 'default' : 'outline'"
                                size="sm"
                                @click="setFilter('expense')"
                                class="gap-1"
                            >
                                <ArrowUpCircle class="h-3.5 w-3.5" />
                                Expenses
                            </Button>
                        </div>
                        <Button size="sm" @click="openCreateSheet" class="gap-1">
                            <Plus class="h-3.5 w-3.5" />
                            Add
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
                    v-else-if="filteredFinances.length === 0"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <Receipt class="h-10 w-10" />
                    <div class="text-center">
                        <p class="font-medium">
                            {{ activeFilter === 'all' ? 'No finance records yet' : `No ${activeFilter}s found` }}
                        </p>
                        <p class="mt-1 text-sm">
                            Click "Add" to create your first finance record.
                        </p>
                    </div>
                </div>

                <!-- Finances Table -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Type</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead>Recurring</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="finance in filteredFinances" :key="finance.id">
                            <TableCell>
                                <Badge :variant="finance.type === 'payment' ? 'default' : 'destructive'" class="gap-1">
                                    <ArrowDownCircle v-if="finance.type === 'payment'" class="h-3 w-3" />
                                    <ArrowUpCircle v-else class="h-3 w-3" />
                                    {{ finance.type === 'payment' ? 'Payment' : 'Expense' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ finance.description }}
                                <p v-if="finance.notes" class="mt-0.5 text-xs text-muted-foreground truncate max-w-[200px]">
                                    {{ finance.notes }}
                                </p>
                            </TableCell>
                            <TableCell>
                                {{ formatDate(finance.date) }}
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ finance.formatted_amount }}
                            </TableCell>
                            <TableCell>
                                <Badge v-if="finance.is_recurring" variant="outline" class="gap-1">
                                    <RefreshCw class="h-3 w-3" />
                                    {{ finance.recurrence_frequency }}
                                </Badge>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="openEditSheet(finance)">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button variant="ghost" size="sm" @click="deleteFinance(finance)">
                                        <Trash2 class="h-3.5 w-3.5 text-destructive" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Create/Edit Sheet -->
        <Sheet :open="isSheetOpen" @update:open="handleSheetChange">
            <SheetContent side="right" class="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <PoundSterling class="h-5 w-5" />
                        {{ editingFinance ? 'Edit Finance Record' : 'Add Finance Record' }}
                    </SheetTitle>
                    <SheetDescription>
                        {{ editingFinance ? 'Update the details of this finance record.' : 'Record a new payment or expense.' }}
                    </SheetDescription>
                </SheetHeader>

                <form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">
                    <!-- Type -->
                    <div class="space-y-2">
                        <Label for="type">Type *</Label>
                        <select
                            id="type"
                            v-model="form.type"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="payment">Payment</option>
                            <option value="expense">Expense</option>
                        </select>
                        <p v-if="errors.type" class="text-sm text-destructive">{{ errors.type }}</p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description *</Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            placeholder="e.g. Fuel costs, Lesson payment"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.description" class="text-sm text-destructive">{{ errors.description }}</p>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-2">
                        <Label for="amount">Amount (£) *</Label>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.amount_pence" class="text-sm text-destructive">{{ errors.amount_pence }}</p>
                    </div>

                    <!-- Date -->
                    <div class="space-y-2">
                        <Label for="date">Date *</Label>
                        <Input
                            id="date"
                            v-model="form.date"
                            type="date"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.date" class="text-sm text-destructive">{{ errors.date }}</p>
                    </div>

                    <!-- Recurring Toggle -->
                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <Checkbox
                            id="is_recurring"
                            :checked="form.is_recurring"
                            @update:checked="form.is_recurring = !!$event"
                            :disabled="isSubmitting"
                        />
                        <div class="space-y-0.5">
                            <Label for="is_recurring">Recurring</Label>
                            <p class="text-xs text-muted-foreground">
                                Is this a recurring payment/expense?
                            </p>
                        </div>
                    </div>

                    <!-- Recurrence Frequency (shown when recurring) -->
                    <div v-if="form.is_recurring" class="space-y-2">
                        <Label for="recurrence_frequency">Frequency *</Label>
                        <select
                            id="recurrence_frequency"
                            v-model="form.recurrence_frequency"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" disabled>Select frequency</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                        <p v-if="errors.recurrence_frequency" class="text-sm text-destructive">{{ errors.recurrence_frequency }}</p>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            placeholder="Optional notes..."
                            rows="3"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <p v-if="errors.notes" class="text-sm text-destructive">{{ errors.notes }}</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="handleSheetChange(false)"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isSubmitting" class="min-w-[100px]">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            {{ editingFinance ? 'Update' : 'Create' }}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
