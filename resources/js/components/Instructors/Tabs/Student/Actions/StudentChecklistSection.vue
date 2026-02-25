<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import { ListChecks, Loader2, Check, Calendar } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'

interface ChecklistItem {
    id: number
    student_id: number
    key: string
    label: string
    category: string
    is_checked: boolean
    date: string | null
    notes: string | null
    sort_order: number
    created_at: string
    updated_at: string
}

const props = defineProps<{
    studentId: number
}>()

const items = ref<ChecklistItem[]>([])
const isLoading = ref(true)
const isToggling = ref<number | null>(null)

// Check dialog state
const checkDialogOpen = ref(false)
const checkDialogItem = ref<ChecklistItem | null>(null)
const checkForm = ref({
    date: '',
    notes: '',
})

const groupedItems = computed(() => {
    const groups: Record<string, ChecklistItem[]> = {}
    for (const item of items.value) {
        if (!groups[item.category]) {
            groups[item.category] = []
        }
        groups[item.category].push(item)
    }
    return groups
})

const loadChecklist = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(
            `/students/${props.studentId}/checklist`,
        )
        items.value = response.data.checklist_items || []
    } catch {
        toast({
            title: 'Failed to load checklist',
            variant: 'destructive',
        })
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadChecklist()
})

const handleToggle = (item: ChecklistItem) => {
    if (item.is_checked) {
        handleUncheck(item)
    } else {
        checkDialogItem.value = item
        const today = new Date().toISOString().split('T')[0]
        checkForm.value = { date: today, notes: '' }
        checkDialogOpen.value = true
    }
}

const confirmCheck = async () => {
    if (!checkDialogItem.value) return
    if (!checkForm.value.date) {
        toast({
            title: 'Please select a date',
            variant: 'destructive',
        })
        return
    }

    isToggling.value = checkDialogItem.value.id

    try {
        const response = await axios.patch(
            `/students/${props.studentId}/checklist/${checkDialogItem.value.id}`,
            {
                is_checked: true,
                date: checkForm.value.date,
                notes: checkForm.value.notes || null,
            },
        )

        const index = items.value.findIndex(
            (i) => i.id === checkDialogItem.value!.id,
        )
        if (index !== -1) {
            items.value[index] = response.data.checklist_item
        }

        toast({ title: `${checkDialogItem.value.label} completed` })
        checkDialogOpen.value = false
        checkDialogItem.value = null
    } catch (error: any) {
        const message =
            error.response?.data?.message ||
            'Failed to update checklist item'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isToggling.value = null
    }
}

const handleUncheck = async (item: ChecklistItem) => {
    isToggling.value = item.id

    try {
        const response = await axios.patch(
            `/students/${props.studentId}/checklist/${item.id}`,
            {
                is_checked: false,
                date: null,
                notes: null,
            },
        )

        const index = items.value.findIndex((i) => i.id === item.id)
        if (index !== -1) {
            items.value[index] = response.data.checklist_item
        }

        toast({ title: `${item.label} unchecked` })
    } catch (error: any) {
        const message =
            error.response?.data?.message ||
            'Failed to update checklist item'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isToggling.value = null
    }
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center gap-2">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <ListChecks class="h-5 w-5" />
                Student Checklist
            </h3>
        </div>

        <!-- Loading -->
        <div
            v-if="isLoading"
            class="grid grid-cols-1 gap-8 md:grid-cols-3"
        >
            <div v-for="n in 3" :key="n" class="space-y-3">
                <Skeleton class="h-5 w-24" />
                <Skeleton
                    v-for="m in 3"
                    :key="m"
                    class="h-8 w-full"
                />
            </div>
        </div>

        <!-- Content -->
        <div
            v-else
            class="grid grid-cols-1 gap-8 md:grid-cols-3"
        >
            <div
                v-for="(categoryItems, category) in groupedItems"
                :key="category"
            >
                <h4
                    class="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground"
                >
                    {{ category }}
                </h4>
                <div class="space-y-3">
                    <div
                        v-for="item in categoryItems"
                        :key="item.id"
                    >
                        <label
                            class="flex cursor-pointer items-start gap-3"
                            :class="{
                                'opacity-50':
                                    isToggling === item.id,
                            }"
                        >
                            <input
                                type="checkbox"
                                :checked="item.is_checked"
                                :disabled="isToggling === item.id"
                                @click.prevent="handleToggle(item)"
                                class="mt-0.5 h-5 w-5 shrink-0 cursor-pointer rounded border-input accent-primary"
                            />
                            <div class="flex-1">
                                <span
                                    class="text-sm"
                                    :class="
                                        item.is_checked
                                            ? 'text-muted-foreground line-through'
                                            : 'text-foreground'
                                    "
                                >
                                    {{ item.label }}
                                </span>
                                <div
                                    v-if="
                                        item.is_checked && item.date
                                    "
                                    class="mt-1 flex items-center gap-2"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="text-xs"
                                    >
                                        <Calendar
                                            class="mr-1 h-3 w-3"
                                        />
                                        {{ formatDate(item.date) }}
                                    </Badge>
                                </div>
                                <p
                                    v-if="
                                        item.is_checked && item.notes
                                    "
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ item.notes }}
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Check Dialog -->
        <Dialog v-model:open="checkDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        checkDialogItem?.label
                    }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <p class="text-sm text-muted-foreground">
                        Please enter the date for this checklist item.
                    </p>
                    <div class="space-y-2">
                        <Label for="checklist_date">Date *</Label>
                        <input
                            id="checklist_date"
                            type="date"
                            v-model="checkForm.date"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="checklist_notes"
                            >Notes (Optional)</Label
                        >
                        <textarea
                            id="checklist_notes"
                            v-model="checkForm.notes"
                            placeholder="Add any additional notes..."
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="checkDialogOpen = false"
                        :disabled="isToggling !== null"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="confirmCheck"
                        :disabled="isToggling !== null"
                        class="min-w-[100px]"
                    >
                        <Loader2
                            v-if="isToggling !== null"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Check
                            v-else
                            class="mr-2 h-4 w-4"
                        />
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
