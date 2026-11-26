<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { AlertTriangle, CalendarClock, CalendarX, Clock, History } from 'lucide-vue-next'

export type LessonDecision = 'happened' | 'missed'

export interface TransferLessonRow {
    id: number
    date: string | null
    start_time: string | null
    end_time: string | null
    package_name: string | null
    payment_status: 'paid' | 'cash' | 'due'
    is_practical_test: boolean
}

export interface TransferFutureRow extends TransferLessonRow {
    clashes_with: string | null
    can_unschedule: boolean
}

export interface TransferPreview {
    student: { id: number; name: string }
    source_instructor: { id: number; name: string }
    destination_instructor: { id: number; name: string }
    past_unsigned: TransferLessonRow[]
    future: TransferFutureRow[]
    unscheduled: TransferLessonRow[]
    pending_orders: {
        id: number
        package_name: string | null
        lessons_count: number
        payment_mode: string | null
    }[]
}

const props = defineProps<{
    preview: TransferPreview
    decisions: Record<number, LessonDecision>
}>()

const emit = defineEmits<{
    decide: [lessonId: number, decision: LessonDecision]
}>()

const source = computed(() => props.preview.source_instructor.name)
const destination = computed(() => props.preview.destination_instructor.name)

const freeLessons = computed(() => props.preview.future.filter((l) => l.clashes_with === null))
const clashesToUnschedule = computed(() =>
    props.preview.future.filter((l) => l.clashes_with !== null && l.can_unschedule),
)
const clashesToFlag = computed(() =>
    props.preview.future.filter((l) => l.clashes_with !== null && !l.can_unschedule),
)
const missedCount = computed(
    () => Object.values(props.decisions).filter((d) => d === 'missed').length,
)
const undecidedCount = computed(
    () => props.preview.past_unsigned.filter((l) => !props.decisions[l.id]).length,
)

const nothingToMove = computed(
    () =>
        props.preview.past_unsigned.length === 0 &&
        props.preview.future.length === 0 &&
        props.preview.unscheduled.length === 0 &&
        props.preview.pending_orders.length === 0,
)

const plural = (count: number, singular: string, pluralForm = `${singular}s`) =>
    `${count} ${count === 1 ? singular : pluralForm}`

const summary = computed(() => {
    const inDiary = freeLessons.value.length + clashesToFlag.value.length
    const unscheduled =
        props.preview.unscheduled.length + clashesToUnschedule.value.length + missedCount.value
    const parts = [
        `${plural(inDiary, 'lesson')} into ${destination.value}'s diary`,
        `${plural(unscheduled, 'unscheduled lesson')} for ${destination.value} to book`,
    ]

    if (props.preview.pending_orders.length > 0) {
        parts.push(`${plural(props.preview.pending_orders.length, 'unpaid booking')} cancelled`)
    }

    return parts.join(', ') + '.'
})

const formatLesson = (lesson: TransferLessonRow): string => {
    if (!lesson.date) return 'No date'

    const date = new Date(`${lesson.date}T00:00:00`).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })

    return lesson.start_time ? `${date}, ${lesson.start_time}–${lesson.end_time ?? ''}` : date
}

const paymentLabel: Record<TransferLessonRow['payment_status'], string> = {
    paid: 'Paid',
    cash: 'Cash',
    due: 'Not paid',
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div>
            <h3 class="text-lg font-semibold">Pupil Transfer Check</h3>
            <p class="text-sm text-muted-foreground">
                What moving {{ preview.student.name }} from {{ source }} to {{ destination }}
                will do. Nothing changes until you press Move pupil.
            </p>
        </div>

        <p v-if="nothingToMove" class="text-sm text-muted-foreground">
            {{ preview.student.name }} has no open lessons or bookings with {{ source }}, so only
            the instructor changes.
        </p>

        <section v-if="preview.past_unsigned.length > 0" class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <History class="h-4 w-4 text-amber-600" />
                <h4 class="font-medium">Past lessons not signed off</h4>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ source }} hasn't signed these off. Did each one take place? If it happened, it
                stays with {{ source }} to sign off and be paid. If it was missed, the pupil gets it
                back as an unscheduled lesson to take with {{ destination }}.
            </p>
            <div
                v-for="lesson in preview.past_unsigned"
                :key="lesson.id"
                class="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-medium">{{ formatLesson(lesson) }}</span>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span v-if="lesson.package_name">{{ lesson.package_name }}</span>
                        <Badge variant="outline">{{ paymentLabel[lesson.payment_status] }}</Badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        class="cursor-pointer"
                        :variant="decisions[lesson.id] === 'happened' ? 'default' : 'outline'"
                        @click="emit('decide', lesson.id, 'happened')"
                    >
                        It happened
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="cursor-pointer"
                        :variant="decisions[lesson.id] === 'missed' ? 'default' : 'outline'"
                        @click="emit('decide', lesson.id, 'missed')"
                    >
                        It was missed
                    </Button>
                </div>
            </div>
        </section>

        <section v-if="preview.future.length > 0" class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <CalendarClock class="h-4 w-4 text-muted-foreground" />
                <h4 class="font-medium">Future lessons</h4>
            </div>
            <div
                v-for="lesson in preview.future"
                :key="lesson.id"
                class="flex flex-col gap-1 rounded-md border p-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium">{{ formatLesson(lesson) }}</span>
                    <Badge v-if="lesson.clashes_with === null" variant="secondary">
                        Moves into the diary
                    </Badge>
                    <Badge v-else-if="lesson.can_unschedule" variant="outline">
                        Becomes unscheduled
                    </Badge>
                    <Badge v-else variant="destructive">Moves with a clash</Badge>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span v-if="lesson.is_practical_test">Driving test</span>
                    <span v-else-if="lesson.package_name">{{ lesson.package_name }}</span>
                    <Badge variant="outline">{{ paymentLabel[lesson.payment_status] }}</Badge>
                </div>
                <p v-if="lesson.clashes_with" class="text-xs text-muted-foreground">
                    Clashes with {{ lesson.clashes_with }} in {{ destination }}'s diary.
                    <template v-if="lesson.can_unschedule">
                        It loses its date and {{ destination }} agrees a new time with the pupil.
                    </template>
                    <template v-else>
                        It can't be unscheduled, so it moves at the same time and
                        {{ destination }} is asked to sort out the clash.
                    </template>
                </p>
            </div>
        </section>

        <section v-if="preview.unscheduled.length > 0" class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <h4 class="font-medium">Unscheduled lessons</h4>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ plural(preview.unscheduled.length, 'lesson') }} without a date
                {{ preview.unscheduled.length === 1 ? 'moves' : 'move' }} to {{ destination }}, who
                agrees times with the pupil.
            </p>
        </section>

        <section v-if="preview.pending_orders.length > 0" class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <CalendarX class="h-4 w-4 text-destructive" />
                <h4 class="font-medium">Unpaid bookings</h4>
            </div>
            <p class="text-sm text-muted-foreground">
                These bookings haven't been paid, so they are cancelled and their payment links
                closed. The pupil can book again with {{ destination }}.
            </p>
            <ul class="list-disc pl-5 text-sm">
                <li v-for="order in preview.pending_orders" :key="order.id">
                    {{ order.package_name ?? 'Booking' }} ({{ plural(order.lessons_count, 'lesson') }})
                </li>
            </ul>
        </section>

        <Alert v-if="undecidedCount > 0">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>
                Say whether {{ plural(undecidedCount, 'past lesson') }} took place
            </AlertTitle>
            <AlertDescription>You can move the pupil once every past lesson has an answer.</AlertDescription>
        </Alert>

        <p v-if="!nothingToMove" class="rounded-md bg-muted p-3 text-sm">
            <span class="font-medium">Result:</span> {{ summary }}
        </p>
    </div>
</template>
