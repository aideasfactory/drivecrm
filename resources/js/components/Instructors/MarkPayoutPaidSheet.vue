<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { AlertTriangle, CheckCircle2, Loader2, Banknote } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import { markPayoutPaid } from '@/actions/App/Http/Controllers/InstructorController'

export interface PayoutToMark {
    id: number
    instructorId: number
    amount: string | null
    lessonDate: string | null
}

const props = defineProps<{
    open: boolean
    payout: PayoutToMark | null
}>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    marked: []
}>()

const reference = ref('')
const error = ref<string | null>(null)
const isSubmitting = ref(false)

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
})

watch(
    () => props.open,
    (open) => {
        if (open) {
            reference.value = ''
            error.value = null
        }
    },
)

const handleSubmit = async () => {
    if (!props.payout) return

    error.value = null

    if (reference.value.trim() === '') {
        error.value = 'Enter the payment reference from Stripe or your bank.'
        return
    }

    isSubmitting.value = true
    try {
        const { data } = await axios.post(
            markPayoutPaid.url({ instructor: props.payout.instructorId, payout: props.payout.id }),
            { reference: reference.value.trim() },
        )
        toast.success(data?.message ?? 'Payout marked as paid manually.')
        isOpen.value = false
        emit('marked')
    } catch (err: any) {
        const errors = err?.response?.data?.errors
        if (errors?.reference) {
            error.value = errors.reference[0]
        } else {
            toast.error(err?.response?.data?.message ?? 'Failed to mark payout as paid.')
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Banknote class="h-5 w-5" />
                    Mark Payout as Paid
                </SheetTitle>
                <SheetDescription>
                    Record a payout you have already paid to the instructor outside the platform.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">
                <div class="rounded-md bg-muted p-3 text-sm">
                    <p v-if="payout?.amount" class="font-medium">{{ payout.amount }}</p>
                    <p v-if="payout?.lessonDate" class="text-muted-foreground">Lesson on {{ payout.lessonDate }}</p>
                </div>

                <div class="flex items-start gap-2 text-sm text-muted-foreground">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>This does not send any money. Only do this once the instructor has been paid.</p>
                </div>

                <div class="space-y-2">
                    <Label for="manual-payment-reference">Payment reference</Label>
                    <Input
                        id="manual-payment-reference"
                        v-model="reference"
                        :disabled="isSubmitting"
                        maxlength="255"
                        placeholder="e.g. tr_1Abc… or bank reference"
                    />
                    <p class="text-xs text-muted-foreground">
                        The Stripe transfer ID or bank payment reference, so the payment can be traced later.
                    </p>
                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" :disabled="isSubmitting" @click="isOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-[140px]">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                        Mark as paid
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
