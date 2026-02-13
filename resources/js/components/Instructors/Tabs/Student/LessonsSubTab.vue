<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Separator } from '@/components/ui/separator'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import {
    BookOpen,
    CheckCircle2,
    Clock,
    Ban,
    PoundSterling,
    Loader2,
    ClipboardCheck,
    CalendarDays,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

interface Lesson {
    id: number
    order_id: number
    instructor_id: number
    instructor_name: string | null
    package_name: string
    amount_pence: number
    date: string | null
    start_time: string | null
    end_time: string | null
    status: 'pending' | 'completed' | 'cancelled'
    completed_at: string | null
    payment_status: 'due' | 'paid' | 'refunded' | null
    payment_mode: 'upfront' | 'weekly'
    payout_status: 'pending' | 'paid' | 'failed' | null
    has_payout: boolean
    calendar_date: string | null
}

interface Props {
    studentId: number
}

const props = defineProps<Props>()

// Data state
const lessons = ref<Lesson[]>([])
const loading = ref(true)

// Sign-off sheet state
const isSignOffSheetOpen = ref(false)
const signOffTarget = ref<Lesson | null>(null)
const isSigningOff = ref(false)

// Computed
const pendingLessons = computed(() => lessons.value.filter((l) => l.status === 'pending'))
const completedLessons = computed(() => lessons.value.filter((l) => l.status === 'completed'))

// Formatting helpers
const formatCurrency = (pence: number): string => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(pence / 100)
}

const formatDate = (dateString: string | null): string => {
    if (!dateString) return '—'
    const date = new Date(dateString + 'T00:00:00')
    return date.toLocaleDateString('en-GB', {
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

const statusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'completed':
            return 'default'
        case 'pending':
            return 'secondary'
        case 'cancelled':
            return 'destructive'
        default:
            return 'outline'
    }
}

const statusIcon = (status: string) => {
    switch (status) {
        case 'completed':
            return CheckCircle2
        case 'pending':
            return Clock
        case 'cancelled':
            return Ban
        default:
            return Clock
    }
}

const paymentBadgeVariant = (status: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'paid':
            return 'default'
        case 'due':
            return 'outline'
        case 'refunded':
            return 'destructive'
        default:
            return 'secondary'
    }
}

// API calls
const loadLessons = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/students/${props.studentId}/lessons`)
        lessons.value = response.data.lessons || []
    } catch {
        toast.error('Failed to load lessons')
    } finally {
        loading.value = false
    }
}

// Sign-off flow
const openSignOffSheet = (lesson: Lesson) => {
    signOffTarget.value = lesson
    isSignOffSheetOpen.value = true
}

const handleSignOff = async () => {
    if (!signOffTarget.value) return

    isSigningOff.value = true
    try {
        await axios.post(
            `/students/${props.studentId}/lessons/${signOffTarget.value.id}/sign-off`,
        )

        // Optimistically update the lesson status in the list
        const idx = lessons.value.findIndex((l) => l.id === signOffTarget.value!.id)
        if (idx !== -1) {
            lessons.value[idx] = {
                ...lessons.value[idx],
                status: 'completed',
                completed_at: new Date().toISOString(),
            }
        }

        isSignOffSheetOpen.value = false
        signOffTarget.value = null
        toast.success('Lesson sign-off is being processed')
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to sign off lesson'
        toast.error(message)
    } finally {
        isSigningOff.value = false
    }
}

onMounted(() => {
    loadLessons()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Summary Cards -->
        <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card v-for="n in 3" :key="n">
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <Skeleton class="h-4 w-4" />
                        <Skeleton class="h-8 w-20" />
                        <Skeleton class="h-4 w-24" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                        <p class="text-2xl font-bold">{{ lessons.length }}</p>
                        <p class="text-sm text-muted-foreground">Total Lessons</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <CheckCircle2 class="h-4 w-4 text-muted-foreground" />
                        <p class="text-2xl font-bold">{{ completedLessons.length }}</p>
                        <p class="text-sm text-muted-foreground">Completed</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <Clock class="h-4 w-4 text-muted-foreground" />
                        <p class="text-2xl font-bold">{{ pendingLessons.length }}</p>
                        <p class="text-sm text-muted-foreground">Pending</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Lessons Table -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CalendarDays class="h-5 w-5" />
                    Lessons
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Loading Skeleton -->
                <div v-if="loading" class="space-y-3">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton v-for="n in 5" :key="n" class="h-12 w-full" />
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="lessons.length === 0"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <BookOpen class="h-10 w-10" />
                    <div class="text-center">
                        <p class="font-medium">No lessons yet</p>
                        <p class="mt-1 text-sm">Lessons will appear here once orders are placed</p>
                    </div>
                </div>

                <!-- Lessons Table -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Time</TableHead>
                            <TableHead>Package</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Payment</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="lesson in lessons" :key="lesson.id">
                            <TableCell class="font-medium">
                                {{ formatDate(lesson.date) }}
                            </TableCell>
                            <TableCell>
                                {{ formatTime(lesson.start_time, lesson.end_time) }}
                            </TableCell>
                            <TableCell>
                                {{ lesson.package_name }}
                            </TableCell>
                            <TableCell>
                                {{ formatCurrency(lesson.amount_pence) }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="statusBadgeVariant(lesson.status)" class="gap-1">
                                    <component
                                        :is="statusIcon(lesson.status)"
                                        class="h-3 w-3"
                                    />
                                    {{ lesson.status.charAt(0).toUpperCase() + lesson.status.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="paymentBadgeVariant(lesson.payment_status)" class="gap-1">
                                    <PoundSterling class="h-3 w-3" />
                                    {{ lesson.payment_status
                                        ? lesson.payment_status.charAt(0).toUpperCase() + lesson.payment_status.slice(1)
                                        : '—' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    v-if="lesson.status === 'pending'"
                                    variant="outline"
                                    size="sm"
                                    @click="openSignOffSheet(lesson)"
                                    class="gap-1"
                                >
                                    <ClipboardCheck class="h-4 w-4" />
                                    Sign Off
                                </Button>
                                <span
                                    v-else-if="lesson.status === 'completed'"
                                    class="text-sm text-muted-foreground"
                                >
                                    Signed off
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Sign-Off Sheet (Slide-out) -->
        <Sheet v-model:open="isSignOffSheetOpen">
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <ClipboardCheck class="h-5 w-5" />
                        Sign Off Lesson
                    </SheetTitle>
                    <SheetDescription>
                        Review the lesson details and confirm the sign-off below.
                    </SheetDescription>
                </SheetHeader>

                <div v-if="signOffTarget" class="mt-6 space-y-6 px-6 py-4">
                    <!-- Lesson Details -->
                    <div class="space-y-3">
                        <h4 class="text-sm font-medium">Lesson Details</h4>
                        <div class="rounded-md border p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Date</span>
                                <span class="font-medium">{{ formatDate(signOffTarget.date) }}</span>
                            </div>
                            <Separator />
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Time</span>
                                <span class="font-medium">{{ formatTime(signOffTarget.start_time, signOffTarget.end_time) }}</span>
                            </div>
                            <Separator />
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Package</span>
                                <span class="font-medium">{{ signOffTarget.package_name }}</span>
                            </div>
                            <Separator />
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Amount</span>
                                <span class="font-medium">{{ formatCurrency(signOffTarget.amount_pence) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="space-y-3">
                        <h4 class="text-sm font-medium">Terms & Conditions</h4>
                        <div class="rounded-md border bg-muted/50 p-4 text-sm text-muted-foreground space-y-2 max-h-[200px] overflow-y-auto">
                            <p>By signing off this lesson, you confirm that:</p>
                            <ul class="list-disc pl-4 space-y-1">
                                <li>The lesson took place as scheduled on the date and time shown above.</li>
                                <li>The student attended and participated in the full lesson duration.</li>
                                <li>You delivered the lesson content in accordance with the agreed syllabus.</li>
                                <li>You understand that signing off will trigger a payout to your connected Stripe account for the lesson amount.</li>
                                <li>Once signed off, this action cannot be reversed. The lesson will be permanently marked as completed.</li>
                                <li>A feedback request email will be sent to the student following sign-off.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <SheetFooter class="px-6 pb-6">
                    <Button
                        variant="outline"
                        @click="isSignOffSheetOpen = false"
                        :disabled="isSigningOff"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleSignOff"
                        :disabled="isSigningOff"
                        class="min-w-[140px]"
                    >
                        <Loader2 v-if="isSigningOff" class="mr-2 h-4 w-4 animate-spin" />
                        <ClipboardCheck v-else class="mr-2 h-4 w-4" />
                        Confirm Sign Off
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </div>
</template>
