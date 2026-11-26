<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { toast } from '@/components/ui/sonner'
import { Search, Plus, Tag, Copy, Check, Power, PowerOff, Loader2, BarChart3 } from 'lucide-vue-next'
import CreateDiscountCodeSheet from '@/components/DiscountCodes/CreateDiscountCodeSheet.vue'
import axios from 'axios'
import { setActive } from '@/routes/discount-codes'
import { discountCodes as discountCodesReport } from '@/routes/reports'

interface DiscountCodeItem {
    id: string
    code: string | null
    label: string
    type: 'percentage' | 'amount'
    percentage: number | null
    amount_pence: number | null
    formatted_percentage: string
    active: boolean
    orders_count: number
    created_at: string | null
}

interface Props {
    discountCodes: DiscountCodeItem[]
}

const props = defineProps<Props>()

const searchQuery = ref('')
const isCreateSheetOpen = ref(false)
const togglingId = ref<string | null>(null)
const copiedId = ref<string | null>(null)

const filteredCodes = computed(() => {
    if (!searchQuery.value) {
        return props.discountCodes
    }

    const query = searchQuery.value.toLowerCase()
    return props.discountCodes.filter(
        (code) =>
            code.label.toLowerCase().includes(query) ||
            (code.code ?? '').toLowerCase().includes(query) ||
            code.id.toLowerCase().includes(query),
    )
})

const handleCreated = () => {
    router.reload()
}

const toggleActive = async (code: DiscountCodeItem) => {
    togglingId.value = code.id

    try {
        await axios.patch(setActive.url(code.id), { active: !code.active })
        toast.success(code.active ? 'Discount code deactivated' : 'Discount code activated')
        router.reload({ only: ['discountCodes'] })
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to update discount code')
    } finally {
        togglingId.value = null
    }
}

const reportUrl = (code: DiscountCodeItem): string =>
    discountCodesReport.url({ query: { code: code.code || code.label } })

const copyUrl = async (code: DiscountCodeItem) => {
    const url = `${window.location.origin}/onboarding?discount=${code.id}`
    await navigator.clipboard.writeText(url)
    copiedId.value = code.id
    toast.success('Discount URL copied to clipboard!')
    setTimeout(() => {
        copiedId.value = null
    }, 2000)
}

const breadcrumbs = [{ title: 'Discount Codes' }]
</script>

<template>
    <Head title="Discount Codes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Discount Codes</h2>
                <p class="text-muted-foreground">
                    Codes learners enter at the last step of the booking form. Codes are never deleted,
                    deactivate one to stop it being used.
                </p>
            </div>

            <!-- Search and Create Button -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search discount codes..."
                        class="pl-9"
                    />
                </div>
                <Button
                    @click="isCreateSheetOpen = true"
                    class="cursor-pointer"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Create Discount Code
                </Button>
            </div>

            <!-- Discount Codes Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Label</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>Uses</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right"
                                    >Actions</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="code in filteredCodes"
                                :key="code.id"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Tag
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <span v-if="code.code" class="font-mono font-semibold">{{
                                            code.code
                                        }}</span>
                                        <span v-else class="text-sm text-muted-foreground">Link only</span>
                                    </div>
                                </TableCell>
                                <TableCell>{{ code.label }}</TableCell>
                                <TableCell>
                                    <Badge variant="secondary">
                                        {{ code.formatted_percentage }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Link
                                        v-if="code.orders_count > 0"
                                        :href="reportUrl(code)"
                                        class="inline-flex items-center gap-1 text-primary hover:underline"
                                    >
                                        <BarChart3 class="h-3.5 w-3.5" />
                                        {{ code.orders_count }}
                                    </Link>
                                    <span v-else class="text-muted-foreground">0</span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            code.active
                                                ? 'default'
                                                : 'destructive'
                                        "
                                    >
                                        {{
                                            code.active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">{{
                                        code.created_at
                                    }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="copyUrl(code)"
                                            class="cursor-pointer"
                                            title="Copy discount link"
                                        >
                                            <Check
                                                v-if="copiedId === code.id"
                                                class="h-4 w-4 text-green-500"
                                            />
                                            <Copy v-else class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="togglingId === code.id"
                                            @click="toggleActive(code)"
                                            class="cursor-pointer min-w-[120px]"
                                        >
                                            <Loader2 v-if="togglingId === code.id" class="mr-1 h-4 w-4 animate-spin" />
                                            <PowerOff v-else-if="code.active" class="mr-1 h-4 w-4" />
                                            <Power v-else class="mr-1 h-4 w-4" />
                                            {{ code.active ? 'Deactivate' : 'Activate' }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredCodes.length === 0">
                                <TableCell colspan="7" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No discount codes found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Create Discount Code Sheet -->
        <CreateDiscountCodeSheet
            v-model:open="isCreateSheetOpen"
            @discount-code-created="handleCreated"
        />

    </AppLayout>
</template>
