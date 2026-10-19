<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Banknote } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import { cashLessons as cashLessonsRoute } from '@/actions/App/Http/Controllers/InstructorController'

interface CashLesson {
    lesson_id: number
    amount_pence: number
    formatted_amount: string
    student_name: string | null
    package_name: string | null
    lesson_date: string | null
    lesson_start_time: string | null
    lesson_end_time: string | null
    completed_at: string | null
}

const props = defineProps<{
    instructorId: number
}>()

const lessons = ref<CashLesson[]>([])
const loading = ref(true)

/** Cash collected this calendar month, by lesson date. */
const thisMonthTotal = computed(() => {
    const now = new Date()
    const monthPrefix = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`

    return lessons.value
        .filter((lesson) => lesson.lesson_date?.startsWith(monthPrefix))
        .reduce((sum, lesson) => sum + lesson.amount_pence, 0)
})

const allTimeTotal = computed(() => lessons.value.reduce((sum, lesson) => sum + lesson.amount_pence, 0))

const formatCurrency = (pence: number): string => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(pence / 100)
}

const formatDate = (dateString: string | null): string => {
    if (!dateString) return '—'
    return new Date(dateString + 'T00:00:00').toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

const formatTime = (start: string | null, end: string | null): string => {
    if (!start) return '—'
    if (!end) return start
    return `${start} – ${end}`
}

const loadCashLessons = async () => {
    loading.value = true
    try {
        const response = await axios.get(cashLessonsRoute.url(props.instructorId))
        lessons.value = response.data.cash_lessons || []
    } catch {
        toast.error('Failed to load cash lessons')
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadCashLessons()
})
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Banknote class="h-5 w-5" />
                        Cash Lessons
                    </CardTitle>
                    <CardDescription class="mt-1">
                        Signed-off lessons the pupil paid for in cash. These have no payout.
                    </CardDescription>
                </div>
                <div v-if="!loading && lessons.length > 0" class="flex gap-6 text-right">
                    <div>
                        <p class="text-xs text-muted-foreground">This month</p>
                        <p class="text-xl font-bold">{{ formatCurrency(thisMonthTotal) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">All time</p>
                        <p class="text-xl font-bold">{{ formatCurrency(allTimeTotal) }}</p>
                    </div>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-3">
                <Skeleton class="h-10 w-full" />
                <Skeleton v-for="n in 3" :key="n" class="h-12 w-full" />
            </div>

            <p v-else-if="lessons.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                No cash lessons signed off yet.
            </p>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>Student</TableHead>
                        <TableHead>Lesson Date</TableHead>
                        <TableHead>Time</TableHead>
                        <TableHead>Package</TableHead>
                        <TableHead class="text-right">Cash</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="lesson in lessons" :key="lesson.lesson_id">
                        <TableCell class="font-medium">{{ lesson.student_name || '—' }}</TableCell>
                        <TableCell>{{ formatDate(lesson.lesson_date) }}</TableCell>
                        <TableCell>{{ formatTime(lesson.lesson_start_time, lesson.lesson_end_time) }}</TableCell>
                        <TableCell>{{ lesson.package_name || '—' }}</TableCell>
                        <TableCell class="text-right font-medium">{{ lesson.formatted_amount }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
