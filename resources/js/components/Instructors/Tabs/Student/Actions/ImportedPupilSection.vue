<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Banknote, Ban, Loader2 } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import { updateCashPayments } from '@/actions/App/Http/Controllers/PupilController'

const props = defineProps<{
    studentId: number
    isImported: boolean
    cashPaymentsEnabled: boolean
}>()

const emit = defineEmits<{
    updated: []
}>()

const cashEnabled = ref(props.cashPaymentsEnabled)
const isSavingCash = ref(false)

const toggleCashPayments = async () => {
    isSavingCash.value = true

    try {
        const response = await axios.patch(updateCashPayments.url(props.studentId), {
            cash_payments_enabled: !cashEnabled.value,
        })
        cashEnabled.value = response.data.cash_payments_enabled === true
        toast({ title: response.data.message })
        emit('updated')
    } catch (error: any) {
        const message =
            error.response?.data?.errors?.cash_payments_enabled?.[0] ||
            error.response?.data?.message ||
            'Failed to update cash payments'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isSavingCash.value = false
    }
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <Banknote class="h-5 w-5" />
                Cash Payments
                <Badge v-if="isImported" variant="secondary">Imported</Badge>
                <Badge v-if="cashEnabled" variant="secondary">Pays by cash</Badge>
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Let this pupil pay the instructor in cash at each lesson, with no booking or digital fee. Cash
                payments are off until you turn them on. Changing this does not change existing bookings.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <Button
                :variant="cashEnabled ? 'outline' : 'default'"
                :disabled="isSavingCash"
                class="min-w-[220px]"
                @click="toggleCashPayments"
            >
                <Loader2 v-if="isSavingCash" class="mr-2 h-4 w-4 animate-spin" />
                <Ban v-else-if="cashEnabled" class="mr-2 h-4 w-4" />
                <Banknote v-else class="mr-2 h-4 w-4" />
                {{ cashEnabled ? 'Turn off cash payments' : 'Turn on cash payments' }}
            </Button>
        </div>
    </div>
</template>
