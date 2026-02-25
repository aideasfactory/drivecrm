<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import { Activity, Loader2, Save } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'passed', label: 'Passed' },
    { value: 'failed', label: 'Failed' },
    { value: 'completed', label: 'Completed' },
]

const props = defineProps<{
    studentId: number
    currentStatus: string
    inactiveReason: string | null
}>()

const emit = defineEmits<{
    (e: 'updated', status: string, reason: string | null): void
}>()

const status = ref(props.currentStatus)
const reason = ref(props.inactiveReason || '')
const isConfirmOpen = ref(false)
const isSubmitting = ref(false)

const openConfirmDialog = () => {
    isConfirmOpen.value = true
}

const handleUpdate = async () => {
    isSubmitting.value = true
    try {
        await axios.patch(`/students/${props.studentId}/status`, {
            status: status.value,
            inactive_reason: reason.value || null,
        })
        toast({ title: 'Student status updated successfully' })
        isConfirmOpen.value = false
        emit('updated', status.value, reason.value || null)
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to update status'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex items-center gap-2">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <Activity class="h-5 w-5" />
                Student Status
            </h3>
        </div>

        <div class="space-y-4">
            <div class="space-y-2">
                <Label for="student_status">Current Status</Label>
                <select
                    id="student_status"
                    v-model="status"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <option
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
            </div>

            <div class="space-y-2">
                <Label for="status_notes">Status Notes</Label>
                <textarea
                    id="status_notes"
                    v-model="reason"
                    placeholder="Add notes about this status change..."
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none"
                />
            </div>

            <Button @click="openConfirmDialog" class="w-full">
                <Save class="mr-2 h-4 w-4" />
                Update Status
            </Button>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="isConfirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Update Student Status?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to change the student status
                        to
                        <strong class="font-semibold text-foreground">
                            {{
                                statusOptions.find(
                                    (o) => o.value === status,
                                )?.label
                            }} </strong
                        >?
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isConfirmOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleUpdate"
                        :disabled="isSubmitting"
                        class="min-w-[120px]"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save
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
