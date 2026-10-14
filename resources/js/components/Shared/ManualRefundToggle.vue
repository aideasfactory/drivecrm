<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { AlertTriangle, Loader2, RotateCcw, Undo2 } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

const props = withDefaults(
    defineProps<{
        /** PATCH endpoint that accepts `{ refunded: boolean }`. */
        url: string
        refundedAt: string | null
        refundedByName: string | null
        /** What is being refunded, used in the confirmation text (e.g. "booking #12"). */
        subject: string
        canEdit?: boolean
        id?: string
    }>(),
    {
        canEdit: false,
        id: undefined,
    },
)

const emit = defineEmits<{
    updated: []
}>()

const isRefunded = computed(() => props.refundedAt !== null)
const confirmOpen = ref(false)
const pendingValue = ref(false)
const isSubmitting = ref(false)
const generatedId = useId()
const checkboxId = computed(() => props.id ?? `manual-refund-${generatedId}`)

const formattedRefundedAt = computed(() => {
    if (!props.refundedAt) return null
    return new Date(props.refundedAt).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})

const requestChange = (value: boolean | 'indeterminate') => {
    if (!props.canEdit || isSubmitting.value) return
    pendingValue.value = value === true
    confirmOpen.value = true
}

const confirmChange = async () => {
    isSubmitting.value = true
    try {
        const { data } = await axios.patch(props.url, { refunded: pendingValue.value })
        toast.success(data?.message ?? (pendingValue.value ? 'Marked as refunded.' : 'Refund mark removed.'))
        confirmOpen.value = false
        emit('updated')
    } catch (err: any) {
        const errors = err?.response?.data?.errors
        toast.error(errors?.refunded?.[0] ?? err?.response?.data?.message ?? 'Failed to update refund status.')
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <div v-if="canEdit" class="flex items-center gap-2">
            <Checkbox
                :id="checkboxId"
                :model-value="isRefunded"
                :disabled="isSubmitting"
                class="cursor-pointer"
                @update:model-value="requestChange"
            />
            <label :for="checkboxId" class="cursor-pointer text-sm leading-none">Refunded</label>
        </div>
        <span v-else-if="isRefunded" class="inline-flex items-center gap-1 text-sm font-medium">
            <RotateCcw class="h-3.5 w-3.5" />
            Refunded
        </span>
        <span v-else class="text-muted-foreground">—</span>

        <p v-if="isRefunded" class="text-xs text-muted-foreground">
            by {{ refundedByName ?? 'a removed user' }}<template v-if="formattedRefundedAt">, {{ formattedRefundedAt }}</template>
        </p>

        <Dialog v-model:open="confirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <RotateCcw v-if="pendingValue" class="h-5 w-5" />
                        <Undo2 v-else class="h-5 w-5" />
                        {{ pendingValue ? 'Mark as refunded?' : 'Remove refund mark?' }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="pendingValue">
                            Record that {{ subject }} has already been refunded outside the system (e.g. in Stripe or by bank transfer). You will be recorded as the staff member who marked it.
                        </template>
                        <template v-else>
                            Untick {{ subject }} as refunded. The change is recorded in the activity log with your name.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div class="flex items-start gap-2 text-sm text-muted-foreground">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>This does not send or reverse any money in Stripe. It only updates the record.</p>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="isSubmitting" @click="confirmOpen = false">Cancel</Button>
                    <Button :disabled="isSubmitting" class="min-w-[160px]" @click="confirmChange">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <RotateCcw v-else-if="pendingValue" class="mr-2 h-4 w-4" />
                        <Undo2 v-else class="mr-2 h-4 w-4" />
                        {{ pendingValue ? 'Mark as refunded' : 'Remove refund mark' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
