<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toast } from '@/components/ui/sonner'
import { ArrowRightLeft, CheckCircle2, Loader2, X } from 'lucide-vue-next'

interface StudentOption {
    id: number
    name: string
    email: string | null
    current_instructor_id: number | null
    current_instructor_name: string | null
}

interface InstructorOption {
    id: number
    name: string
    email: string | null
}

interface Props {
    students: StudentOption[]
    instructors: InstructorOption[]
}

const props = defineProps<Props>()

const selectedStudentId = ref<number | ''>('')
const selectedInstructorId = ref<number | ''>('')
const submitting = ref(false)
const lastTransferMessage = ref<string | null>(null)

const selectedStudent = computed(() =>
    props.students.find((s) => s.id === selectedStudentId.value),
)

const availableInstructors = computed(() => {
    if (!selectedStudent.value) {
        return props.instructors
    }
    return props.instructors.filter(
        (i) => i.id !== selectedStudent.value!.current_instructor_id,
    )
})

const canSubmit = computed(
    () =>
        selectedStudentId.value !== '' &&
        selectedInstructorId.value !== '' &&
        !submitting.value,
)

const handleSubmit = () => {
    if (!canSubmit.value) return

    submitting.value = true

    router.post(
        '/student-transfers',
        {
            student_id: selectedStudentId.value,
            destination_instructor_id: selectedInstructorId.value,
        },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props.flash as { success?: string }) ?? {}
                const message = flash.success ?? 'Transfer complete.'
                toast.success(message)
                lastTransferMessage.value = message
                selectedStudentId.value = ''
                selectedInstructorId.value = ''
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
                            <select
                                id="student"
                                v-model="selectedStudentId"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="" disabled>
                                    Select a student...
                                </option>
                                <option
                                    v-for="student in props.students"
                                    :key="student.id"
                                    :value="student.id"
                                >
                                    {{ student.name }}<template v-if="student.email"> ({{ student.email }})</template>
                                </option>
                            </select>
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
                                Only students with a current instructor are listed.
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="destination">Transfer to instructor</Label>
                            <select
                                id="destination"
                                v-model="selectedInstructorId"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="" disabled>
                                    Select an instructor...
                                </option>
                                <option
                                    v-for="instructor in availableInstructors"
                                    :key="instructor.id"
                                    :value="instructor.id"
                                >
                                    {{ instructor.name }}<template v-if="instructor.email"> ({{ instructor.email }})</template>
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                Only instructors who have completed Stripe onboarding
                                are listed.
                            </p>
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
                v-if="props.students.length === 0"
                class="rounded-md border border-dashed p-8 text-center max-w-2xl"
            >
                <p class="text-muted-foreground">
                    No students with a current instructor are available to transfer.
                </p>
            </div>

            <div
                v-else-if="props.instructors.length === 0"
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
