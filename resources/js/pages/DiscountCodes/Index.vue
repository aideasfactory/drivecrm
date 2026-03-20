<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { toast } from '@/components/ui/sonner'
import { Search, Plus, Percent, Trash2, Copy, Check } from 'lucide-vue-next'
import CreateDiscountCodeSheet from '@/components/DiscountCodes/CreateDiscountCodeSheet.vue'
import axios from 'axios'

interface DiscountCodeItem {
    id: string
    label: string
    percentage: number
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
const deleteTarget = ref<DiscountCodeItem | null>(null)
const isDeleteDialogOpen = ref(false)
const deleting = ref(false)
const copiedId = ref<string | null>(null)

const filteredCodes = computed(() => {
    if (!searchQuery.value) {
        return props.discountCodes
    }

    const query = searchQuery.value.toLowerCase()
    return props.discountCodes.filter(
        (code) =>
            code.label.toLowerCase().includes(query) ||
            code.id.toLowerCase().includes(query),
    )
})

const handleCreated = () => {
    router.reload()
}

const confirmDelete = (code: DiscountCodeItem) => {
    deleteTarget.value = code
    isDeleteDialogOpen.value = true
}

const handleDelete = async () => {
    if (!deleteTarget.value) return

    deleting.value = true

    try {
        await axios.delete(`/discount-codes/${deleteTarget.value.id}`)
        toast.success('Discount code deleted successfully!')
        isDeleteDialogOpen.value = false
        deleteTarget.value = null
        router.reload()
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to delete discount code'
        toast.error(message)
    } finally {
        deleting.value = false
    }
}

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
                    Manage UUID-based discount codes for the onboarding flow
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
                                <TableHead>Label</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>UUID</TableHead>
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
                                        <Percent
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <span class="font-semibold">{{
                                            code.label
                                        }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary">
                                        {{ code.formatted_percentage }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <code
                                        class="text-xs text-muted-foreground"
                                        >{{ code.id }}</code
                                    >
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">{{
                                        code.orders_count
                                    }}</span>
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
                                        >
                                            <Check
                                                v-if="copiedId === code.id"
                                                class="h-4 w-4 text-green-500"
                                            />
                                            <Copy v-else class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="confirmDelete(code)"
                                            class="cursor-pointer text-destructive hover:text-destructive"
                                        >
                                            <Trash2 class="h-4 w-4" />
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

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete discount code?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete the discount code
                        <strong>"{{ deleteTarget?.label }}"</strong>. Orders
                        that already used this code will not be affected.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="deleting"
                        @click="isDeleteDialogOpen = false"
                        class="cursor-pointer"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleDelete"
                        :disabled="deleting"
                        class="cursor-pointer"
                    >
                        {{ deleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
