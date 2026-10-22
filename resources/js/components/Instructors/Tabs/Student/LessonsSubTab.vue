<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Separator } from '@/components/ui/separator'
import { Checkbox } from '@/components/ui/checkbox'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import {
    BookOpen,
    CheckCircle2,
    Clock,
    Ban,
    Banknote,
    PoundSterling,
    Loader2,
    ClipboardCheck,
    CalendarDays,
    FileText,
    Send,
    ShoppingCart,
    HandCoins,
    TriangleAlert,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import { confirmAlternativePayment } from '@/actions/App/Http/Controllers/PupilController'

interface Lesson {
    id: number
    student_lesson_number: number
    order_id: number
    instructor_id: number
    instructor_name: string | null
    package_name: string
    amount_pence: number
    date: string | null
    start_time: string | null
    end_time: string | null
    status: 'pending' | 'completed' | 'cancelled'
    /** Package week booked onto a time already taken; the DRIVE team is rearranging it. */
    is_provisional: boolean
    completed_at: string | null
    summary: string | null
    lesson_payment_id: number | null
    payment_status: 'due' | 'paid' | 'refunded' | null
    has_stripe_invoice: boolean
    payment_mode: 'upfront' | 'weekly' | 'imported' | 'cash'
    payout_status: 'pending' | 'paid' | 'failed' | null
    has_payout: boolean
    calendar_date: string | null
    alternative_payment_allowed: boolean
    can_sign_off_with_alternative_payment: boolean
    paid_by_alternative_method: boolean
}

interface AlternativePaymentBooking {
    order_id: number
    package_name: string | null
    lessons_count: number
    first_lesson_date: string | null
    first_lesson_start_time: string | null
    first_lesson_end_time: string | null
    amount_pence: number
}

interface Props {
    studentId: number
    studentFirstName?: string | null
    studentLastName?: string | null
    studentEmail?: string | null
    instructorId?: number | null
}

const props = defineProps<Props>()

// Data state
const lessons = ref<Lesson[]>([])
const loading = ref(true)
const alternativePaymentAllowed = ref(false)
const alternativePaymentBookings = ref<AlternativePaymentBooking[]>([])

// Confirm-booking (alternative payment) dialog state
const isConfirmBookingOpen = ref(false)
const confirmBookingTarget = ref<AlternativePaymentBooking | null>(null)
const confirmBookingRisksAccepted = ref(false)
const isConfirmingBooking = ref(false)

// Sign-off sheet state
const isSignOffSheetOpen = ref(false)
const signOffTarget = ref<Lesson | null>(null)
const isSigningOff = ref(false)
const summary = ref('')
const alternativePaymentConfirmed = ref(false)
const errors = ref<Record<string, string>>({})

// View summary dialog state
const isViewSummaryOpen = ref(false)
const viewSummaryTarget = ref<Lesson | null>(null)

// Resend invoice state
const resendingInvoiceId = ref<number | null>(null)

// Computed
const pendingLessons = computed(() => lessons.value.filter((l) => l.status === 'pending'))
const completedLessons = computed(() => lessons.value.filter((l) => l.status === 'completed'))

// Purchase lessons URL
const canPurchaseLessons = computed(() => {
    return !!(props.studentFirstName && props.studentLastName && props.studentEmail && props.instructorId)
})

const purchaseLessonsUrl = computed(() => {
    const params = new URLSearchParams()
    if (props.studentFirstName) params.set('first_name', props.studentFirstName)
    if (props.studentLastName) params.set('last_name', props.studentLastName)
    if (props.studentEmail) params.set('email', props.studentEmail)
    if (props.instructorId) params.set('instructor_id', String(props.instructorId))
    params.set('staff_booking', '1')
    return `/onboarding?${params.toString()}`
})

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
        alternativePaymentAllowed.value = response.data.alternative_payment_allowed === true
        alternativePaymentBookings.value = response.data.alternative_payment_bookings || []
    } catch {
        toast.error('Failed to load lessons')
    } finally {
        loading.value = false
    }
}

const openViewSummary = (lesson: Lesson) => {
    viewSummaryTarget.value = lesson
    isViewSummaryOpen.value = true
}

// Resend invoice
const resendInvoice = async (lesson: Lesson) => {
    resendingInvoiceId.value = lesson.id
    try {
        await axios.post(`/students/${props.studentId}/lessons/${lesson.id}/resend-invoice`)
        toast.success('Invoice email has been resent')
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to resend invoice'
        toast.error(message)
    } finally {
        resendingInvoiceId.value = null
    }
}

// Sign-off flow
const openSignOffSheet = (lesson: Lesson) => {
    signOffTarget.value = lesson
    summary.value = ''
    alternativePaymentConfirmed.value = false
    errors.value = {}
    isSignOffSheetOpen.value = true
}

const canSignOff = (lesson: Lesson): boolean =>
    lesson.status === 'pending' && (lesson.payment_status === 'paid' || lesson.can_sign_off_with_alternative_payment)

const signOffNeedsAlternativeConfirmation = computed(
    () => signOffTarget.value?.can_sign_off_with_alternative_payment === true,
)

// Confirm a booking awaiting its first payment as paid by an alternative method
const openConfirmBooking = (booking: AlternativePaymentBooking) => {
    confirmBookingTarget.value = booking
    confirmBookingRisksAccepted.value = false
    isConfirmBookingOpen.value = true
}

const handleConfirmBooking = async () => {
    if (!confirmBookingTarget.value || !confirmBookingRisksAccepted.value) return

    isConfirmingBooking.value = true
    try {
        const response = await axios.post(
            confirmAlternativePayment.url({ student: props.studentId, order: confirmBookingTarget.value.order_id }),
            { accept_risks: true },
        )
        toast.success(response.data.message || 'Booking confirmed')
        isConfirmBookingOpen.value = false
        confirmBookingTarget.value = null
        await loadLessons()
    } catch (error: any) {
        const data = error.response?.data
        const message = data?.errors?.order?.[0] || data?.errors?.accept_risks?.[0] || data?.message || 'Failed to confirm booking'
        toast.error(message)
    } finally {
        isConfirmingBooking.value = false
    }
}

const handleSignOff = async () => {
    if (!signOffTarget.value) return

    errors.value = {}

    if (!summary.value.trim()) {
        errors.value = { summary: 'Please provide a lesson summary.' }
        return
    }

    if (signOffNeedsAlternativeConfirmation.value && !alternativePaymentConfirmed.value) {
        errors.value = {
            alternative_payment_confirmed: 'Please confirm the pupil paid by an alternative method and that you accept the risks.',
        }
        return
    }

    isSigningOff.value = true
    try {
        await axios.post(
            `/students/${props.studentId}/lessons/${signOffTarget.value.id}/sign-off`,
            {
                summary: summary.value.trim(),
                alternative_payment_confirmed: signOffNeedsAlternativeConfirmation.value
                    ? alternativePaymentConfirmed.value
                    : undefined,
            },
        )

        // Optimistically update the lesson status in the list
        const idx = lessons.value.findIndex((l) => l.id === signOffTarget.value!.id)
        if (idx !== -1) {
            lessons.value[idx] = {
                ...lessons.value[idx],
                status: 'completed',
                completed_at: new Date().toISOString(),
                summary: summary.value.trim(),
                ...(signOffNeedsAlternativeConfirmation.value ? { payment_status: 'paid' as const } : {}),
            }
        }

        isSignOffSheetOpen.value = false
        signOffTarget.value = null
        toast.success('Lesson sign-off is being processed')
    } catch (error: any) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(([key, val]: [string, any]) => [key, val[0]])
            )
        } else {
            const message = error.response?.data?.message || 'Failed to sign off lesson'
            toast.error(message)
        }
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

        <!-- Bookings awaiting payment that can be confirmed as paid by an alternative method -->
        <Card v-if="!loading && alternativePaymentBookings.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <HandCoins class="h-5 w-5" />
                    Bookings Awaiting Payment
                </CardTitle>
                <p class="text-sm text-muted-foreground">
                    Alternative payments are on for this pupil. If they are paying the instructor directly, confirm
                    the booking here. Until then it waits for a card payment and is not cancelled automatically.
                </p>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>First Lesson</TableHead>
                            <TableHead>Time</TableHead>
                            <TableHead>Package</TableHead>
                            <TableHead>Lessons</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="booking in alternativePaymentBookings" :key="booking.order_id">
                            <TableCell class="font-medium">{{ formatDate(booking.first_lesson_date) }}</TableCell>
                            <TableCell>
                                {{ formatTime(booking.first_lesson_start_time, booking.first_lesson_end_time) }}
                            </TableCell>
                            <TableCell>{{ booking.package_name || '—' }}</TableCell>
                            <TableCell class="tabular-nums">{{ booking.lessons_count }}</TableCell>
                            <TableCell class="text-right">
                                <Button variant="outline" size="sm" class="gap-1" @click="openConfirmBooking(booking)">
                                    <HandCoins class="h-4 w-4" />
                                    Paying by Alternative Method
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Lessons Table -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <CalendarDays class="h-5 w-5" />
                    Lessons
                </CardTitle>
                <a
                    v-if="canPurchaseLessons"
                    :href="purchaseLessonsUrl"
                    target="_blank"
                >
                </a>
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
                            <TableHead>Lesson #</TableHead>
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
                            <TableCell class="font-medium tabular-nums">
                                #{{ lesson.student_lesson_number }}
                            </TableCell>
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
                                <Badge
                                    v-if="lesson.is_provisional"
                                    variant="destructive"
                                    class="mt-1 gap-1"
                                    title="Booked onto a time that was already taken. The DRIVE team is speaking to the customer."
                                >
                                    <TriangleAlert class="h-3 w-3" />
                                    Provisional – clash
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="paymentBadgeVariant(lesson.payment_status)" class="gap-1">
                                        <PoundSterling class="h-3 w-3" />
                                        {{ lesson.payment_status
                                            ? lesson.payment_status.charAt(0).toUpperCase() + lesson.payment_status.slice(1)
                                            : '—' }}
                                    </Badge>
                                    <Badge v-if="lesson.payment_mode === 'cash'" variant="outline" class="gap-1">
                                        <Banknote class="h-3 w-3" />
                                        Cash
                                    </Badge>
                                    <Badge v-else-if="lesson.paid_by_alternative_method" variant="outline" class="gap-1">
                                        <HandCoins class="h-3 w-3" />
                                        Alternative method
                                    </Badge>
                                    <Badge
                                        v-else-if="lesson.can_sign_off_with_alternative_payment"
                                        variant="secondary"
                                        class="gap-1"
                                    >
                                        <HandCoins class="h-3 w-3" />
                                        Alt. payment allowed
                                    </Badge>
                                    <Button
                                        v-if="lesson.payment_status === 'due' && lesson.has_stripe_invoice"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 gap-1 px-2 text-xs"
                                        :disabled="resendingInvoiceId === lesson.id"
                                        @click="resendInvoice(lesson)"
                                    >
                                        <Loader2 v-if="resendingInvoiceId === lesson.id" class="h-3 w-3 animate-spin" />
                                        <Send v-else class="h-3 w-3" />
                                        Resend
                                    </Button>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    v-if="canSignOff(lesson)"
                                    variant="outline"
                                    size="sm"
                                    @click="openSignOffSheet(lesson)"
                                    class="gap-1"
                                >
                                    <ClipboardCheck class="h-4 w-4" />
                                    Sign Off
                                </Button>
                                <span
                                    v-else-if="lesson.status === 'pending'"
                                    class="text-xs text-muted-foreground"
                                >
                                    Awaiting payment
                                </span>
                                <Button
                                    v-else-if="lesson.status === 'completed' && lesson.summary"
                                    variant="ghost"
                                    size="sm"
                                    @click="openViewSummary(lesson)"
                                    class="gap-1"
                                >
                                    <FileText class="h-4 w-4" />
                                    View Summary
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

                <div v-if="signOffTarget" class="mt-6 flex-1 space-y-6 overflow-y-auto px-6 py-4">
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

                    <!-- Lesson Summary -->
                    <div class="space-y-2">
                        <Label for="lesson_summary">Lesson Summary *</Label>
                        <textarea
                            id="lesson_summary"
                            v-model="summary"
                            rows="4"
                            placeholder="Describe what was covered in this lesson, e.g. roundabouts, left turns, emergency stops..."
                            :disabled="isSigningOff"
                            class="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <p class="text-muted-foreground text-xs">
                            This summary will be used to recommend relevant learning resources to the student.
                        </p>
                        <p
                            v-if="errors.summary"
                            class="text-destructive text-sm"
                        >
                            {{ errors.summary }}
                        </p>
                    </div>

                    <!-- Alternative payment confirmation (unpaid lesson, pupil with alternative payments on) -->
                    <div v-if="signOffNeedsAlternativeConfirmation" class="space-y-3">
                        <Alert>
                            <TriangleAlert class="h-4 w-4" />
                            <AlertTitle>This lesson hasn't been paid by card</AlertTitle>
                            <AlertDescription>
                                Signing off cancels the pupil's card payment request for this lesson and records it
                                as paid by an alternative method ({{ formatCurrency(signOffTarget.amount_pence) }}, no
                                booking or digital fee). No payout is made through Drive, and late cancellations or
                                no-shows aren't covered.
                            </AlertDescription>
                        </Alert>
                        <div class="flex items-start gap-3 rounded-md border border-input p-3">
                            <Checkbox
                                id="alternative_payment_confirmed"
                                v-model="alternativePaymentConfirmed"
                                :disabled="isSigningOff"
                                class="mt-0.5"
                            />
                            <Label for="alternative_payment_confirmed" class="cursor-pointer leading-snug">
                                I confirm the pupil paid by an alternative method and I accept the risks.
                            </Label>
                        </div>
                        <p v-if="errors.alternative_payment_confirmed" class="text-destructive text-sm">
                            {{ errors.alternative_payment_confirmed }}
                        </p>
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
                                <li v-if="signOffNeedsAlternativeConfirmation">You understand that no payout will be made, because the pupil paid you directly.</li>
                                <li v-else>You understand that signing off will trigger a payout to your connected Stripe account for the lesson amount.</li>
                                <li>Once signed off, this action cannot be reversed. The lesson will be permanently marked as completed.</li>
                                <li>A feedback request email will be sent to the student following sign-off.</li>
                                <li>Relevant learning resources will be recommended to the student based on your lesson summary.</li>
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

        <!-- Confirm Booking (Alternative Payment) Dialog -->
        <Dialog v-model:open="isConfirmBookingOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <HandCoins class="h-5 w-5" />
                        Paying by Alternative Method
                    </DialogTitle>
                    <DialogDescription>
                        {{ confirmBookingTarget?.package_name }} · first lesson
                        {{ confirmBookingTarget ? formatDate(confirmBookingTarget.first_lesson_date) : '' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <Alert>
                        <TriangleAlert class="h-4 w-4" />
                        <AlertTitle>The pupil will pay the instructor directly</AlertTitle>
                        <AlertDescription>
                            This confirms the booking and cancels the pupil's card payment link. The instructor isn't
                            paid through Drive for lessons paid this way, and late cancellations or no-shows aren't
                            covered.<template v-if="(confirmBookingTarget?.lessons_count ?? 0) > 1">
                                Later lessons are still invoiced as normal.</template>
                        </AlertDescription>
                    </Alert>
                    <div class="flex items-start gap-3 rounded-md border border-input p-3">
                        <Checkbox
                            id="confirm_booking_risks"
                            v-model="confirmBookingRisksAccepted"
                            :disabled="isConfirmingBooking"
                            class="mt-0.5"
                        />
                        <Label for="confirm_booking_risks" class="cursor-pointer leading-snug">
                            I confirm the pupil is paying by an alternative method and I accept the risks.
                        </Label>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" :disabled="isConfirmingBooking" @click="isConfirmBookingOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="isConfirmingBooking || !confirmBookingRisksAccepted"
                        class="min-w-[160px]"
                        @click="handleConfirmBooking"
                    >
                        <Loader2 v-if="isConfirmingBooking" class="mr-2 h-4 w-4 animate-spin" />
                        <HandCoins v-else class="mr-2 h-4 w-4" />
                        Confirm Booking
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- View Summary Dialog -->
        <Dialog v-model:open="isViewSummaryOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Lesson Summary
                    </DialogTitle>
                    <DialogDescription>
                        {{ viewSummaryTarget ? formatDate(viewSummaryTarget.date) : '' }}
                        {{ viewSummaryTarget ? formatTime(viewSummaryTarget.start_time, viewSummaryTarget.end_time) : '' }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="viewSummaryTarget" class="space-y-3">
                    <div class="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2 text-sm">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <PoundSterling class="h-4 w-4" />
                            Lesson cost
                        </span>
                        <span class="font-medium">{{ formatCurrency(viewSummaryTarget.amount_pence) }}</span>
                    </div>
                    <div class="text-sm whitespace-pre-wrap">
                        {{ viewSummaryTarget.summary }}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
