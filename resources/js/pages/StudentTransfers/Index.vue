<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { toast } from '@/components/ui/sonner'
import TransferSearchSelect, {
    type TransferSearchOption,
} from '@/components/StudentTransfers/TransferSearchSelect.vue'
import { ArrowRightLeft, CheckCircle2, Loader2, X } from 'lucide-vue-next'

interface StudentOption extends TransferSearchOption {
    current_instructor_id: number | null
    current_instructor_name: string | null
}

interface ReasonOption {
    value: string
    label: string
}

interface Props {
    hasStudents: boolean
    hasInstructors: boolean
    reasons: ReasonOption[]
}

const props = defineProps<Props>()

const selectedStudent = ref<StudentOption | null>(null)
const selectedInstructor = ref<TransferSearchOption | null>(null)
const selectedReason = ref<string>('')
const notes = ref('')
const submitting = ref(false)
const lastTransferMessage = ref<string | null>(null)

const canSubmit = computed(
    () =>
        selectedStudent.value !== null &&
        selectedInstructor.value !== null &&
        selectedReason.value !== '' &&
        !submitting.value,
)

const handleStudentSelected = (option: TransferSearchOption | null) => {
    selectedStudent.value = option as StudentOption | null

    if (
        option &&
        selectedInstructor.value &&
        selectedInstructor.value.id ===
            (option as StudentOption).current_instructor_id
    ) {
        selectedInstructor.value = null
    }
}

const handleSubmit = () => {
    if (!canSubmit.value) return

    submitting.value = true

    router.post(
        '/student-transfers',
        {
            student_id: selectedStudent.value!.id,
            destination_instructor_id: selectedInstructor.value!.id,
            reason: selectedReason.value,
            notes: notes.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props.flash as { success?: string }) ?? {}
                const message = flash.success ?? 'Transfer complete.'
                toast.success(message)
                lastTransferMessage.value = message
                selectedStudent.value = null
                selectedInstructor.value = null
                selectedReason.value = ''
                notes.value = ''
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0]
                toast.error(
                    typeof firstError === 'string'
                        ? firstError
                        : 'Failed to transfer student',
                )
            },
            onFinish: () => {
                submitting.value = false
            },
        },
    )
}

const breadcrumbs = [{ title: 'Transfer Student' }]
</script>

<template>
    <Head title="Transfer Student" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <ArrowRightLeft class="h-8 w-8" />
                    Transfer Student
                </h2>
                <p class="text-muted-foreground max-w-2xl">
                    Move a student from one instructor to another. Past lessons stay
                    with the original instructor. Future lessons are reassigned to
                    the new instructor's diary at their existing dates and times — any
                    clashes are flagged in the receiving instructor's email so they
                    can rebook.
                </p>
            </div>

            <div
                v-if="lastTransferMessage"
                class="flex max-w-2xl items-start gap-3 rounded-md border border-green-200 bg-green-50 p-4 text-green-900 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-100"
            >
                <CheckCircle2 class="mt-0.5 h-5 w-5 flex-shrink-0" />
                <div class="flex-1">
                    <p class="font-semibold">Transfer complete</p>
                    <p class="mt-1 text-sm">{{ lastTransferMessage }}</p>
                </div>
                <button
                    type="button"
                    @click="lastTransferMessage = null"
                    class="cursor-pointer rounded-md p-1 text-green-700 hover:bg-green-100 dark:text-green-300 dark:hover:bg-green-900/40"
                    aria-label="Dismiss"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <Card class="max-w-2xl">
                <CardContent class="pt-6">
                    <form
                        @submit.prevent="handleSubmit"
                        class="flex flex-col gap-5"
                    >
                        <div class="flex flex-col gap-2">
                            <Label for="student">Student</Label>
                            <TransferSearchSelect
                                input-id="student"
                                endpoint="/student-transfers/search-students"
                                results-key="students"
                                placeholder="Search by name, email or phone..."
                                :model-value="selectedStudent"
                                @update:model-value="handleStudentSelected"
                            />
                            <p
                                v-if="selectedStudent"
                                class="text-xs text-muted-foreground"
                            >
                                Current instructor:
                                <span class="font-medium text-foreground">
                                    {{ selectedStudent.current_instructor_name ?? 'None' }}
                                </span>
                            </p>
                            <p
                                v-else
                                class="text-xs text-muted-foreground"
                            >
                                Only students with a current instructor can be found.
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="destination">Transfer to instructor</Label>
                            <TransferSearchSelect
                                input-id="destination"
                                endpoint="/student-transfers/search-instructors"
                                results-key="instructors"
                                placeholder="Search by name, email or phone..."
                                v-model="selectedInstructor"
                                :exclude-id="selectedStudent?.current_instructor_id"
                            />
                            <p class="text-xs text-muted-foreground">
                                Only instructors who have completed Stripe onboarding
                                can be found.
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="reason">Reason for transfer</Label>
                            <select
                                id="reason"
                                v-model="selectedReason"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="" disabled>
                                    Select a reason...
                                </option>
                                <option
                                    v-for="reason in props.reasons"
                                    :key="reason.value"
                                    :value="reason.value"
                                >
                                    {{ reason.label }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                Recorded in the audit trail along with the staff
                                member who actioned the transfer.
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="notes">Why? (optional)</Label>
                            <Textarea
                                id="notes"
                                v-model="notes"
                                rows="3"
                                maxlength="1000"
                                placeholder="Add any extra detail about why this transfer is happening..."
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button
                                type="submit"
                                :disabled="!canSubmit"
                                class="cursor-pointer min-w-[160px]"
                            >
                                <Loader2
                                    v-if="submitting"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <ArrowRightLeft v-else class="mr-2 h-4 w-4" />
                                {{ submitting ? 'Transferring...' : 'Transfer' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div
                v-if="!props.hasStudents"
                class="rounded-md border border-dashed p-8 text-center max-w-2xl"
            >
                <p class="text-muted-foreground">
                    No students with a current instructor are available to transfer.
                </p>
            </div>

            <div
                v-else-if="!props.hasInstructors"
                class="rounded-md border border-dashed p-8 text-center max-w-2xl"
            >
                <p class="text-muted-foreground">
                    No instructors are currently set up to receive transfers. An
                    instructor must complete Stripe onboarding before students can be
                    transferred to them.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
