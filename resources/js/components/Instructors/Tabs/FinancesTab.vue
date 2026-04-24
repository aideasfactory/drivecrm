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
    Paperclip,
    FileText,
    Image as ImageIcon,
    Car,
    X,
    Route,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import type { InstructorDetail } from '@/types/instructor'

interface FinanceReceipt {
    url: string | null
    original_name: string | null
    mime_type: string | null
    size_bytes: number | null
}

interface InstructorFinance {
    id: number
    type: 'payment' | 'expense'
    category: string
    category_label: string | null
    payment_method: string | null
    payment_method_label: string | null
    description: string
    amount_pence: number
    formatted_amount: string
    is_recurring: boolean
    recurrence_frequency: string | null
    date: string
    notes: string | null
    receipt: FinanceReceipt | null
    created_at: string | null
}

interface MileageLog {
    id: number
    date: string
    start_mileage: number
    end_mileage: number
    miles: number
    type: 'business' | 'personal'
    type_label: string
    notes: string | null
    created_at: string | null
}

interface FinanceConfig {
    expense_categories: Record<string, string>
    payment_categories: Record<string, string>
    payment_methods: Record<string, string>
    mileage_types: Record<string, string>
    receipt: {
        max_size_kb: number
        allowed_mimes: string[]
    }
}

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

type FilterType = 'all' | 'payment' | 'expense' | 'mileage'

const finances = ref<InstructorFinance[]>([])
const mileageLogs = ref<MileageLog[]>([])
const config = ref<FinanceConfig | null>(null)
const loading = ref(true)
const mileageLoading = ref(true)
const activeFilter = ref<FilterType>('all')
const isSheetOpen = ref(false)
const isMileageSheetOpen = ref(false)
const isSubmitting = ref(false)
const editingFinance = ref<InstructorFinance | null>(null)
const editingMileage = ref<MileageLog | null>(null)

const errors = ref<Record<string, string>>({})
const mileageErrors = ref<Record<string, string>>({})

const form = ref({
    type: 'payment' as 'payment' | 'expense',
    category: 'none',
    payment_method: '' as string,
    description: '',
    amount: '',
    is_recurring: false,
    recurrence_frequency: '' as string,
    date: new Date().toISOString().split('T')[0],
    notes: '',
})
const receiptFile = ref<File | null>(null)
const removeExistingReceipt = ref(false)

const mileageForm = ref({
    date: new Date().toISOString().split('T')[0],
    start_mileage: '',
    end_mileage: '',
    type: 'business' as 'business' | 'personal',
    notes: '',
})

const activeCategories = computed<Record<string, string>>(() => {
    if (!config.value) return {}
    return form.value.type === 'payment'
        ? config.value.payment_categories
        : config.value.expense_categories
})

const filteredFinances = computed(() => {
    if (activeFilter.value === 'all') return finances.value
    if (activeFilter.value === 'mileage') return []
    return finances.value.filter((f) => f.type === activeFilter.value)
})

const summaryCards = computed(() => {
    if (activeFilter.value === 'mileage') {
        const business = mileageLogs.value.filter((m) => m.type === 'business').reduce((s, m) => s + m.miles, 0)
        const personal = mileageLogs.value.filter((m) => m.type === 'personal').reduce((s, m) => s + m.miles, 0)
        return [
            { title: 'Total Trips', value: mileageLogs.value.length.toString(), icon: Route },
            { title: 'Business Miles', value: business.toLocaleString(), icon: Car },
            { title: 'Personal Miles', value: personal.toLocaleString(), icon: Car },
            { title: 'Total Miles', value: (business + personal).toLocaleString(), icon: Route },
        ]
    }

    const all = finances.value
    const totalPayments = all.filter((f) => f.type === 'payment').reduce((sum, f) => sum + f.amount_pence, 0)
    const totalExpenses = all.filter((f) => f.type === 'expense').reduce((sum, f) => sum + f.amount_pence, 0)
    const netBalance = totalPayments - totalExpenses

    return [
        { title: 'Total Records', value: all.length.toString(), icon: Wallet },
        { title: 'Total Payments', value: formatCurrency(totalPayments), icon: ArrowDownCircle },
        { title: 'Total Expenses', value: formatCurrency(totalExpenses), icon: ArrowUpCircle },
        { title: 'Net Balance', value: formatCurrency(netBalance), icon: PoundSterling },
    ]
})

const formatCurrency = (pence: number): string => {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(pence / 100)
}

const formatDate = (dateString: string | null): string => {
    if (!dateString) return '—'
    const date = new Date(dateString + 'T00:00:00')
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const resetForm = () => {
    form.value = {
        type: 'payment',
        category: 'none',
        payment_method: '',
        description: '',
        amount: '',
        is_recurring: false,
        recurrence_frequency: '',
        date: new Date().toISOString().split('T')[0],
        notes: '',
    }
    receiptFile.value = null
    removeExistingReceipt.value = false
    errors.value = {}
    editingFinance.value = null
}

const resetMileageForm = () => {
    mileageForm.value = {
        date: new Date().toISOString().split('T')[0],
        start_mileage: '',
        end_mileage: '',
        type: 'business',
        notes: '',
    }
    mileageErrors.value = {}
    editingMileage.value = null
}

const openCreateSheet = () => {
    if (activeFilter.value === 'mileage') {
        resetMileageForm()
        isMileageSheetOpen.value = true
    } else {
        resetForm()
        // Pre-fill type from active filter to match the user's current view.
        if (activeFilter.value === 'payment' || activeFilter.value === 'expense') {
            form.value.type = activeFilter.value
        }
        isSheetOpen.value = true
    }
}

const openEditSheet = (finance: InstructorFinance) => {
    editingFinance.value = finance
    form.value = {
        type: finance.type,
        category: finance.category || 'none',
        payment_method: finance.payment_method || '',
        description: finance.description,
        amount: (finance.amount_pence / 100).toFixed(2),
        is_recurring: finance.is_recurring,
        recurrence_frequency: finance.recurrence_frequency || '',
        date: finance.date,
        notes: finance.notes || '',
    }
    receiptFile.value = null
    removeExistingReceipt.value = false
    errors.value = {}
    isSheetOpen.value = true
}

const openMileageEditSheet = (log: MileageLog) => {
    editingMileage.value = log
    mileageForm.value = {
        date: log.date,
        start_mileage: log.start_mileage.toString(),
        end_mileage: log.end_mileage.toString(),
        type: log.type,
        notes: log.notes || '',
    }
    mileageErrors.value = {}
    isMileageSheetOpen.value = true
}

const handleSheetChange = (open: boolean) => {
    if (!open && isSubmitting.value) return
    isSheetOpen.value = open
    if (!open) resetForm()
}

const handleMileageSheetChange = (open: boolean) => {
    if (!open && isSubmitting.value) return
    isMileageSheetOpen.value = open
    if (!open) resetMileageForm()
}

const onReceiptSelected = (event: Event) => {
    const input = event.target as HTMLInputElement
    receiptFile.value = input.files?.[0] || null
    removeExistingReceipt.value = false
}

const loadFinances = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructor.id}/finances`)
        finances.value = response.data.finances || []
        config.value = response.data.config || null
    } catch {
        toast.error('Failed to load finances')
    } finally {
        loading.value = false
    }
}

const loadMileage = async () => {
    mileageLoading.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructor.id}/mileage`)
        mileageLogs.value = response.data.mileage || []
    } catch {
        toast.error('Failed to load mileage logs')
    } finally {
        mileageLoading.value = false
    }
}

const uploadReceipt = async (financeId: number): Promise<InstructorFinance | null> => {
    if (!receiptFile.value) return null
    const formData = new FormData()
    formData.append('receipt', receiptFile.value)
    const response = await axios.post(
        `/instructors/${props.instructor.id}/finances/${financeId}/receipt`,
        formData,
    )
    return response.data.finance
}

const deleteReceiptRemote = async (financeId: number): Promise<InstructorFinance | null> => {
    const response = await axios.delete(
        `/instructors/${props.instructor.id}/finances/${financeId}/receipt`,
    )
    return response.data.finance
}

const handleSubmit = async () => {
    errors.value = {}
    isSubmitting.value = true

    const amountPence = Math.round(parseFloat(form.value.amount || '0') * 100)

    const payload: Record<string, unknown> = {
        type: form.value.type,
        category: form.value.category || 'none',
        payment_method: form.value.payment_method || null,
        description: form.value.description,
        amount_pence: amountPence,
        is_recurring: form.value.is_recurring,
        recurrence_frequency: form.value.is_recurring ? form.value.recurrence_frequency : null,
        date: form.value.date,
        notes: form.value.notes || null,
    }

    try {
        let saved: InstructorFinance
        if (editingFinance.value) {
            const response = await axios.put(
                `/instructors/${props.instructor.id}/finances/${editingFinance.value.id}`,
                payload,
            )
            saved = response.data.finance
        } else {
            const response = await axios.post(
                `/instructors/${props.instructor.id}/finances`,
                payload,
            )
            saved = response.data.finance
        }

        // Receipt: remove existing first if requested, then upload new if provided.
        try {
            if (editingFinance.value && removeExistingReceipt.value && !receiptFile.value) {
                const afterDelete = await deleteReceiptRemote(saved.id)
                if (afterDelete) saved = afterDelete
            }
            if (receiptFile.value) {
                const afterUpload = await uploadReceipt(saved.id)
                if (afterUpload) saved = afterUpload
            }
        } catch (receiptErr: any) {
            const msg = receiptErr.response?.data?.message || 'Receipt upload failed — the record was saved. Try uploading again from the edit view.'
            toast.error(msg)
        }

        if (editingFinance.value) {
            const index = finances.value.findIndex((f) => f.id === editingFinance.value!.id)
            if (index !== -1) finances.value[index] = saved
            toast.success('Finance record updated')
        } else {
            finances.value.unshift(saved)
            toast.success('Finance record created')
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

const handleMileageSubmit = async () => {
    mileageErrors.value = {}
    isSubmitting.value = true

    const payload = {
        date: mileageForm.value.date,
        start_mileage: parseInt(mileageForm.value.start_mileage || '0', 10),
        end_mileage: parseInt(mileageForm.value.end_mileage || '0', 10),
        type: mileageForm.value.type,
        notes: mileageForm.value.notes || null,
    }

    try {
        if (editingMileage.value) {
            const response = await axios.put(
                `/instructors/${props.instructor.id}/mileage/${editingMileage.value.id}`,
                payload,
            )
            const updated = response.data.mileage_log
            const idx = mileageLogs.value.findIndex((m) => m.id === editingMileage.value!.id)
            if (idx !== -1) mileageLogs.value[idx] = updated
            toast.success('Mileage log updated')
        } else {
            const response = await axios.post(
                `/instructors/${props.instructor.id}/mileage`,
                payload,
            )
            mileageLogs.value.unshift(response.data.mileage_log)
            toast.success('Mileage log created')
        }
        isMileageSheetOpen.value = false
        resetMileageForm()
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {}
            for (const key in validationErrors) {
                mileageErrors.value[key] = validationErrors[key][0]
            }
        } else {
            toast.error(error.response?.data?.message || 'Failed to save mileage log')
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
        toast.success('Finance record deleted')
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to delete finance record')
    }
}

const deleteMileage = async (log: MileageLog) => {
    if (!confirm('Are you sure you want to delete this mileage log?')) return

    try {
        await axios.delete(`/instructors/${props.instructor.id}/mileage/${log.id}`)
        mileageLogs.value = mileageLogs.value.filter((m) => m.id !== log.id)
        toast.success('Mileage log deleted')
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to delete mileage log')
    }
}

const setFilter = (filter: FilterType) => {
    activeFilter.value = filter
}

const onTypeChanged = () => {
    // Category list depends on type — reset to 'none' when switching.
    form.value.category = 'none'
}

const receiptIconFor = (mime: string | null) => {
    if (!mime) return FileText
    if (mime.startsWith('image/')) return ImageIcon
    return FileText
}

const fileAccept = computed(() => {
    if (!config.value) return 'application/pdf,image/jpeg,image/png'
    const mimes = config.value.receipt.allowed_mimes
    const parts: string[] = []
    if (mimes.includes('pdf')) parts.push('application/pdf')
    if (mimes.includes('jpg') || mimes.includes('jpeg')) parts.push('image/jpeg')
    if (mimes.includes('png')) parts.push('image/png')
    return parts.join(',')
})

const milesPreview = computed(() => {
    const start = parseInt(mileageForm.value.start_mileage || '0', 10)
    const end = parseInt(mileageForm.value.end_mileage || '0', 10)
    if (!Number.isFinite(start) || !Number.isFinite(end)) return null
    if (end < start) return null
    return end - start
})

onMounted(() => {
    loadFinances()
    loadMileage()
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
                        {{ activeFilter === 'mileage' ? 'Mileage' : 'Finances' }}
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
                            <Button
                                :variant="activeFilter === 'mileage' ? 'default' : 'outline'"
                                size="sm"
                                @click="setFilter('mileage')"
                                class="gap-1"
                            >
                                <Car class="h-3.5 w-3.5" />
                                Mileage
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
                <!-- Finances table (and 'all' view) -->
                <template v-if="activeFilter !== 'mileage'">
                    <div v-if="loading" class="space-y-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton v-for="n in 5" :key="n" class="h-12 w-full" />
                    </div>

                    <div
                        v-else-if="filteredFinances.length === 0"
                        class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
                    >
                        <Receipt class="h-10 w-10" />
                        <div class="text-center">
                            <p class="font-medium">
                                {{ activeFilter === 'all' ? 'No finance records yet' : `No ${activeFilter}s found` }}
                            </p>
                            <p class="mt-1 text-sm">Click "Add" to create your first finance record.</p>
                        </div>
                    </div>

                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead>Recurring</TableHead>
                                <TableHead>Receipt</TableHead>
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
                                    <p v-if="finance.notes" class="mt-0.5 max-w-[200px] truncate text-xs text-muted-foreground">
                                        {{ finance.notes }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <span v-if="finance.category_label && finance.category !== 'none'" class="text-sm">
                                        {{ finance.category_label }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>
                                    <span v-if="finance.payment_method_label" class="text-sm">
                                        {{ finance.payment_method_label }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>{{ formatDate(finance.date) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ finance.formatted_amount }}</TableCell>
                                <TableCell>
                                    <Badge v-if="finance.is_recurring" variant="outline" class="gap-1">
                                        <RefreshCw class="h-3 w-3" />
                                        {{ finance.recurrence_frequency }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>
                                    <a
                                        v-if="finance.receipt && finance.receipt.url"
                                        :href="finance.receipt.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                        :title="finance.receipt.original_name || 'View receipt'"
                                    >
                                        <component :is="receiptIconFor(finance.receipt.mime_type)" class="h-4 w-4" />
                                        <span class="sr-only">View receipt</span>
                                    </a>
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
                </template>

                <!-- Mileage table -->
                <template v-else>
                    <div v-if="mileageLoading" class="space-y-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton v-for="n in 5" :key="n" class="h-12 w-full" />
                    </div>

                    <div
                        v-else-if="mileageLogs.length === 0"
                        class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
                    >
                        <Car class="h-10 w-10" />
                        <div class="text-center">
                            <p class="font-medium">No mileage logs yet</p>
                            <p class="mt-1 text-sm">Click "Add" to record your first trip.</p>
                        </div>
                    </div>

                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">Start</TableHead>
                                <TableHead class="text-right">End</TableHead>
                                <TableHead class="text-right">Miles</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="log in mileageLogs" :key="log.id">
                                <TableCell>{{ formatDate(log.date) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="log.type === 'business' ? 'default' : 'secondary'">
                                        {{ log.type_label }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right font-mono">{{ log.start_mileage.toLocaleString() }}</TableCell>
                                <TableCell class="text-right font-mono">{{ log.end_mileage.toLocaleString() }}</TableCell>
                                <TableCell class="text-right font-medium">{{ log.miles.toLocaleString() }}</TableCell>
                                <TableCell>
                                    <p v-if="log.notes" class="max-w-[240px] truncate text-sm text-muted-foreground">
                                        {{ log.notes }}
                                    </p>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="sm" @click="openMileageEditSheet(log)">
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                        <Button variant="ghost" size="sm" @click="deleteMileage(log)">
                                            <Trash2 class="h-3.5 w-3.5 text-destructive" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>
            </CardContent>
        </Card>

        <!-- Finance Create/Edit Sheet -->
        <Sheet :open="isSheetOpen" @update:open="handleSheetChange">
            <SheetContent side="right" class="sm:max-w-md overflow-y-auto">
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
                            @change="onTypeChanged"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="payment">Payment</option>
                            <option value="expense">Expense</option>
                        </select>
                        <p v-if="errors.type" class="text-sm text-destructive">{{ errors.type }}</p>
                    </div>

                    <!-- Category -->
                    <div class="space-y-2">
                        <Label for="category">Category *</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option v-for="(label, slug) in activeCategories" :key="slug" :value="slug">{{ label }}</option>
                        </select>
                        <p v-if="errors.category" class="text-sm text-destructive">{{ errors.category }}</p>
                    </div>

                    <!-- Payment Method -->
                    <div class="space-y-2">
                        <Label for="payment_method">Payment Method</Label>
                        <select
                            id="payment_method"
                            v-model="form.payment_method"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">— Not specified —</option>
                            <option v-for="(label, slug) in config?.payment_methods || {}" :key="slug" :value="slug">{{ label }}</option>
                        </select>
                        <p v-if="errors.payment_method" class="text-sm text-destructive">{{ errors.payment_method }}</p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description *</Label>
                        <Input id="description" v-model="form.description" placeholder="e.g. Fuel costs, Lesson payment" :disabled="isSubmitting" />
                        <p v-if="errors.description" class="text-sm text-destructive">{{ errors.description }}</p>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-2">
                        <Label for="amount">Amount (£) *</Label>
                        <Input id="amount" v-model="form.amount" type="number" step="0.01" min="0.01" placeholder="0.00" :disabled="isSubmitting" />
                        <p v-if="errors.amount_pence" class="text-sm text-destructive">{{ errors.amount_pence }}</p>
                    </div>

                    <!-- Date -->
                    <div class="space-y-2">
                        <Label for="date">Date *</Label>
                        <Input id="date" v-model="form.date" type="date" :disabled="isSubmitting" />
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
                            <p class="text-xs text-muted-foreground">Is this a recurring payment/expense?</p>
                        </div>
                    </div>

                    <!-- Recurrence Frequency -->
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

                    <!-- Receipt -->
                    <div class="space-y-2 rounded-lg border p-3">
                        <Label for="receipt" class="flex items-center gap-2">
                            <Paperclip class="h-4 w-4" />
                            Receipt
                        </Label>
                        <div v-if="editingFinance?.receipt && !receiptFile && !removeExistingReceipt" class="flex items-center justify-between rounded-md bg-muted p-2 text-sm">
                            <a
                                :href="editingFinance.receipt.url || '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 text-primary hover:underline"
                            >
                                <component :is="receiptIconFor(editingFinance.receipt.mime_type)" class="h-4 w-4" />
                                {{ editingFinance.receipt.original_name || 'View current receipt' }}
                            </a>
                            <Button type="button" variant="ghost" size="sm" @click="removeExistingReceipt = true" :disabled="isSubmitting">
                                <X class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                        <p v-else-if="editingFinance?.receipt && removeExistingReceipt && !receiptFile" class="text-sm text-destructive">
                            Current receipt will be removed on save.
                        </p>
                        <input
                            id="receipt"
                            type="file"
                            :accept="fileAccept"
                            :disabled="isSubmitting"
                            @change="onReceiptSelected"
                            class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                        />
                        <p v-if="receiptFile" class="text-xs text-muted-foreground">
                            {{ receiptFile.name }} ({{ (receiptFile.size / 1024).toFixed(1) }} KB)
                        </p>
                        <p class="text-xs text-muted-foreground">
                            PDF, JPG or PNG. Max {{ config ? Math.round(config.receipt.max_size_kb / 1024) : 10 }} MB.
                        </p>
                        <p v-if="errors.receipt" class="text-sm text-destructive">{{ errors.receipt }}</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-3 pt-4">
                        <Button type="button" variant="outline" @click="handleSheetChange(false)" :disabled="isSubmitting">
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

        <!-- Mileage Create/Edit Sheet -->
        <Sheet :open="isMileageSheetOpen" @update:open="handleMileageSheetChange">
            <SheetContent side="right" class="sm:max-w-md overflow-y-auto">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <Car class="h-5 w-5" />
                        {{ editingMileage ? 'Edit Mileage Log' : 'Add Mileage Log' }}
                    </SheetTitle>
                    <SheetDescription>
                        {{ editingMileage ? 'Update this trip record.' : 'Record a business or personal trip.' }}
                    </SheetDescription>
                </SheetHeader>

                <form @submit.prevent="handleMileageSubmit" class="mt-6 space-y-6 px-6 py-4">
                    <!-- Date -->
                    <div class="space-y-2">
                        <Label for="mileage_date">Date *</Label>
                        <Input id="mileage_date" v-model="mileageForm.date" type="date" :disabled="isSubmitting" />
                        <p v-if="mileageErrors.date" class="text-sm text-destructive">{{ mileageErrors.date }}</p>
                    </div>

                    <!-- Type -->
                    <div class="space-y-2">
                        <Label for="mileage_type">Type *</Label>
                        <select
                            id="mileage_type"
                            v-model="mileageForm.type"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option v-for="(label, slug) in config?.mileage_types || {}" :key="slug" :value="slug">{{ label }}</option>
                        </select>
                        <p v-if="mileageErrors.type" class="text-sm text-destructive">{{ mileageErrors.type }}</p>
                    </div>

                    <!-- Start / End mileage -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <Label for="start_mileage">Start *</Label>
                            <Input id="start_mileage" v-model="mileageForm.start_mileage" type="number" min="0" placeholder="0" :disabled="isSubmitting" />
                            <p v-if="mileageErrors.start_mileage" class="text-sm text-destructive">{{ mileageErrors.start_mileage }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="end_mileage">End *</Label>
                            <Input id="end_mileage" v-model="mileageForm.end_mileage" type="number" min="0" placeholder="0" :disabled="isSubmitting" />
                            <p v-if="mileageErrors.end_mileage" class="text-sm text-destructive">{{ mileageErrors.end_mileage }}</p>
                        </div>
                    </div>

                    <div v-if="milesPreview !== null" class="rounded-lg bg-muted p-3 text-sm">
                        <span class="text-muted-foreground">Miles: </span>
                        <span class="font-medium">{{ milesPreview.toLocaleString() }}</span>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="mileage_notes">Notes</Label>
                        <textarea
                            id="mileage_notes"
                            v-model="mileageForm.notes"
                            placeholder="Optional notes..."
                            rows="3"
                            :disabled="isSubmitting"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <p v-if="mileageErrors.notes" class="text-sm text-destructive">{{ mileageErrors.notes }}</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-3 pt-4">
                        <Button type="button" variant="outline" @click="handleMileageSheetChange(false)" :disabled="isSubmitting">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isSubmitting" class="min-w-[100px]">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            {{ editingMileage ? 'Update' : 'Create' }}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
