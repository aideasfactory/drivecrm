<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Banknote, Loader2, Import, Undo2 } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import { updateImported } from '@/actions/App/Http/Controllers/PupilController'

const props = defineProps<{
    studentId: number
    isImported: boolean
}>()

const emit = defineEmits<{
    updated: [isImported: boolean]
}>()

const imported = ref(props.isImported)
const isSaving = ref(false)

const toggleImported = async () => {
    isSaving.value = true

    try {
        const response = await axios.patch(updateImported.url(props.studentId), {
            imported: !imported.value,
        })
        imported.value = response.data.is_imported === true
        toast({ title: response.data.message })
        emit('updated', imported.value)
    } catch (error: any) {
        const message =
            error.response?.data?.errors?.imported?.[0] ||
            error.response?.data?.message ||
            'Failed to update the imported mark'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <Banknote class="h-5 w-5" />
                Imported Pupil &amp; Cash Payments
                <Badge v-if="imported" variant="secondary">Imported</Badge>
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Imported pupils (for example from GoRoadie) can have lessons booked as cash: they pay the
                instructor at each lesson, with no booking or digital fee. Marking a pupil only allows cash —
                existing bookings are not changed.
            </p>
        </div>

        <Button
            :variant="imported ? 'outline' : 'default'"
            :disabled="isSaving"
            class="min-w-[200px]"
            @click="toggleImported"
        >
            <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
            <Undo2 v-else-if="imported" class="mr-2 h-4 w-4" />
            <Import v-else class="mr-2 h-4 w-4" />
            {{ imported ? 'Remove imported mark' : 'Mark as imported' }}
        </Button>
    </div>
</template>
