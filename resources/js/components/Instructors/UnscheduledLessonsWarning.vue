<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { CalendarClock, CalendarPlus, Loader2 } from 'lucide-vue-next'

/**
 * Safety net shown in a booking form when the pupil still has unscheduled
 * lessons (booked, often already paid, with no date yet). The message comes
 * from the server (`errors.unscheduled_lessons`).
 */
defineProps<{
    message: string
    isSubmitting: boolean
}>()

const emit = defineEmits<{
    (e: 'use-unscheduled'): void
    (e: 'book-anyway'): void
}>()
</script>

<template>
    <Alert>
        <CalendarClock />
        <AlertTitle>Unscheduled lessons</AlertTitle>
        <AlertDescription class="flex flex-col gap-3">
            <p>{{ message }}</p>
            <p class="text-muted-foreground">
                Unscheduled lessons are scheduled from the pupil's Lessons tab, with no new charge.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button type="button" @click="emit('use-unscheduled')">
                    <CalendarClock class="mr-2 h-4 w-4" />
                    Use an unscheduled lesson
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="min-w-[220px]"
                    :disabled="isSubmitting"
                    @click="emit('book-anyway')"
                >
                    <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    <CalendarPlus v-else class="mr-2 h-4 w-4" />
                    Book a new lesson anyway
                </Button>
            </div>
        </AlertDescription>
    </Alert>
</template>
