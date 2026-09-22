<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { useRole } from '@/composables/useRole'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import { UserMinus, Loader2, AlertTriangle } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    studentId: number
    hasInstructor: boolean
}>()

const { isInstructor } = useRole()

const isDialogOpen = ref(false)
const isRemoving = ref(false)

const handleRemove = async () => {
    isRemoving.value = true
    try {
        await axios.delete(`/students/${props.studentId}/remove`)
        toast({
            title: isInstructor.value
                ? 'Student has been removed from your active pupil list'
                : 'Student has been removed from the instructor',
        })
        isDialogOpen.value = false
        router.visit('/pupils')
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to remove student'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isRemoving.value = false
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex items-center gap-2">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <UserMinus class="h-5 w-5" />
                {{
                    isInstructor ? 'Remove from your pupils' : 'Remove Student'
                }}
            </h3>
        </div>

        <p class="mb-4 text-sm text-muted-foreground">
            <template v-if="isInstructor">
                Remove this student from your active pupil's list. This
                keeps the student record — it only detaches them from
                your account.
            </template>
            <template v-else>
                Remove this student from their instructor. This does not
                delete the student record — it only detaches them from the
                instructor's account.
            </template>
        </p>

        <Button
            variant="destructive"
            @click="isDialogOpen = true"
            :disabled="!hasInstructor"
        >
            <UserMinus class="mr-2 h-4 w-4" />
            {{
                !hasInstructor
                    ? 'No Instructor Assigned'
                    : isInstructor
                      ? 'Remove from my list'
                      : 'Remove Student'
            }}
        </Button>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle
                            class="h-5 w-5 text-destructive"
                        />
                        {{
                            isInstructor
                                ? 'Remove from your pupil list?'
                                : 'Remove Student?'
                        }}
                    </DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        <template v-if="isInstructor">
                            This will remove the student from your active
                            pupil's list. The student record is kept and
                            they are only detached from your account.
                        </template>
                        <template v-else>
                            This will remove the student from their
                            instructor's account. The student record will
                            remain in the system but will no longer be
                            assigned to any instructor.
                        </template>
                    </p>
                    <p class="mt-3 text-sm font-medium text-destructive">
                        This action cannot be easily undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isDialogOpen = false"
                        :disabled="isRemoving"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleRemove"
                        :disabled="isRemoving"
                        class="min-w-[120px]"
                    >
                        <Loader2
                            v-if="isRemoving"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <UserMinus
                            v-else
                            class="mr-2 h-4 w-4"
                        />
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
