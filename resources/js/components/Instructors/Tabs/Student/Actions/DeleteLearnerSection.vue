<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { AlertTriangle, Loader2, Trash2 } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

const props = withDefaults(
    defineProps<{
        studentId: number
        studentName?: string | null
        compact?: boolean
    }>(),
    {
        compact: false,
    },
)

const emit = defineEmits<{
    deleted: []
}>()

const isDialogOpen = ref(false)
const isDeleting = ref(false)

const handleDelete = async () => {
    isDeleting.value = true

    try {
        await axios.delete(`/students/${props.studentId}`)
        toast.success('Learner profile has been deleted.')
        isDialogOpen.value = false
        emit('deleted')
    } catch (error: unknown) {
        const message =
            (error as { response?: { data?: { message?: string } } })?.response
                ?.data?.message ?? 'Failed to delete learner profile'
        toast.error(message)
    } finally {
        isDeleting.value = false
    }
}
</script>

<template>
    <div>
        <template v-if="!compact">
            <div class="mb-6 flex items-center gap-2">
                <h3 class="flex items-center gap-2 text-lg font-semibold">
                    <Trash2 class="h-5 w-5" />
                    Delete Learner
                </h3>
            </div>

            <p class="mb-4 text-sm text-muted-foreground">
                Remove this learner profile from the CRM. The profile will
                disappear from student lists. Lesson, invoice, and payout
                records are kept so instructor history is not destroyed.
                The learner will no longer be able to sign in.
            </p>
        </template>

        <p v-else class="mb-3 text-sm text-muted-foreground">
            Added this learner by mistake? You can delete the profile.
            Lesson and invoice history is kept if any exists.
        </p>

        <Button
            variant="destructive"
            :class="compact ? 'w-full' : ''"
            :disabled="isDeleting"
            @click="isDialogOpen = true"
        >
            <Trash2 class="mr-2 h-4 w-4" />
            Delete learner
        </Button>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        Delete learner profile?
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="studentName">
                            This will remove
                            <span class="font-medium text-foreground">{{
                                studentName
                            }}</span>
                            from student lists.
                        </template>
                        <template v-else>
                            This will remove the learner from student lists.
                        </template>
                        They will not be able to sign in. Existing lessons
                        and invoices stay on the instructor record.
                    </DialogDescription>
                </DialogHeader>
                <div class="py-2">
                    <p class="text-sm font-medium text-destructive">
                        This cannot be undone from the admin screens.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isDeleting"
                        @click="isDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        class="min-w-[140px]"
                        :disabled="isDeleting"
                        @click="handleDelete"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        {{ isDeleting ? 'Deleting...' : 'Delete learner' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
