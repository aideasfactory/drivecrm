<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import DeleteLearnerSection from '@/components/Instructors/Tabs/Student/Actions/DeleteLearnerSection.vue'
import TestPassGuaranteeBadge from '@/components/Shared/TestPassGuaranteeBadge.vue'
import { Search, GraduationCap, CalendarPlus, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { show as instructorsShow } from '@/routes/instructors'
import { start as onboardingStart } from '@/routes/onboarding'
import { index as pupilsIndex } from '@/routes/pupils'
import { toast } from '@/components/ui/sonner'
import type { PupilListing } from '@/types/pupil'

interface OnboardedInstructor {
    id: number
    name: string
}

interface StudentSummary {
    id: number
    name: string
    email: string | null
    phone: string | null
    status: string
    lessons_completed: number
    lessons_total: number
    revenue_pence: number
    instructor_id: number | null
}

type Assignment = 'all' | 'assigned' | 'unassigned'

interface Pagination {
    current_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
}

interface Filters {
    assignment: Assignment
    q: string | null
}

interface Props {
    pupils: PupilListing[]
    pagination: Pagination
    counts: Record<Assignment, number>
    filters: Filters
    onboardedInstructors: OnboardedInstructor[]
}

const props = defineProps<Props>()

const assignmentOptions: { value: Assignment; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'assigned', label: 'Assigned' },
    { value: 'unassigned', label: 'Unassigned' },
]

const searchQuery = ref(props.filters.q ?? '')
let searchDebounce: ReturnType<typeof setTimeout> | undefined

const applyFilters = (changes: Partial<Filters> = {}) => {
    const next: Filters = {
        assignment: props.filters.assignment,
        q: searchQuery.value.trim() || null,
        ...changes,
    }
    const query: Record<string, string> = {}

    if (next.assignment !== 'all') query.assignment = next.assignment
    if (next.q) query.q = next.q

    router.get(pupilsIndex.url({ query }), {}, { preserveScroll: true, preserveState: true })
}

watch(searchQuery, () => {
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
        if ((props.filters.q ?? '') === searchQuery.value.trim()) {
            return
        }

        applyFilters()
    }, 400)
})

const goToPage = (url: string | null) => {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
}

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

// Assignment sheet state
const sheetOpen = ref(false)
const summaryLoading = ref(false)
const summary = ref<StudentSummary | null>(null)
const selectedInstructorId = ref<number | ''>('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const openAssignSheet = async (pupil: PupilListing) => {
    summary.value = null
    selectedInstructorId.value = ''
    errorMessage.value = null
    sheetOpen.value = true
    summaryLoading.value = true

    try {
        const { data } = await axios.get(`/students/${pupil.id}`)
        summary.value = data.student
    } catch (e) {
        errorMessage.value = 'Could not load student details.'
    } finally {
        summaryLoading.value = false
    }
}

const navigateToPupil = (pupil: PupilListing) => {
    if (pupil.instructor_id) {
        router.visit(`/instructors/${pupil.instructor_id}`, {
            data: { tab: 'student', student: pupil.id, subtab: 'overview' },
        })
        return
    }

    openAssignSheet(pupil)
}

const navigateToInstructor = (event: Event, instructorId: number) => {
    event.stopPropagation()
    router.visit(instructorsShow.url(instructorId))
}

const formatRevenue = (pence: number) => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(pence / 100)
}

const submitAssignment = async () => {
    if (!summary.value || !selectedInstructorId.value) {
        return
    }

    submitting.value = true
    errorMessage.value = null

    try {
        await axios.post(`/pupils/${summary.value.id}/assign-instructor`, {
            instructor_id: selectedInstructorId.value,
        })
        toast.success('Instructor assigned. The instructor has been notified.')
        sheetOpen.value = false
        router.reload({ only: ['pupils', 'pagination', 'counts'] })
    } catch (e: unknown) {
        const message =
            (e as { response?: { data?: { message?: string } } })?.response
                ?.data?.message ?? 'Could not assign instructor.'
        errorMessage.value = message
    } finally {
        submitting.value = false
    }
}

const breadcrumbs = [{ title: 'Students' }]

const staffBookingUrl = onboardingStart.url({ query: { staff_booking: 1 } })

const onLearnerDeleted = () => {
    sheetOpen.value = false
    router.reload({ only: ['pupils', 'pagination', 'counts'] })
}
</script>

<template>
    <Head title="Students" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <h2 class="text-3xl font-bold">Students</h2>
                    <p class="text-muted-foreground">
                        Manage your learner drivers and view their assigned
                        instructors
                    </p>
                </div>
                <Button as-child>
                    <a
                        :href="staffBookingUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <CalendarPlus class="mr-2 h-4 w-4" />
                        Book lessons for a student
                    </a>
                </Button>
            </div>

            <!-- Search + filter -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search name, email, phone or instructor..."
                        class="pl-9"
                    />
                </div>
                <div class="flex items-center gap-1 rounded-md border p-1">
                    <Button
                        v-for="option in assignmentOptions"
                        :key="option.value"
                        :variant="filters.assignment === option.value ? 'default' : 'ghost'"
                        size="sm"
                        @click="applyFilters({ assignment: option.value })"
                    >
                        {{ option.label }}
                        <Badge variant="secondary" class="ml-2">
                            {{ counts[option.value] }}
                        </Badge>
                    </Button>
                </div>
            </div>

            <!-- Students Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Instructor</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="pupil in pupils"
                                :key="pupil.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToPupil(pupil)"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Avatar>
                                            <AvatarFallback>
                                                {{ getInitials(pupil.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div class="flex items-center gap-2 font-semibold">
                                                {{ pupil.name }}
                                                <TestPassGuaranteeBadge
                                                    v-if="pupil.has_test_pass_guarantee"
                                                    short
                                                />
                                            </div>
                                            <div
                                                v-if="pupil.email"
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ pupil.email }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="pupil.instructor_id && pupil.instructor_name"
                                        class="flex items-center gap-3"
                                    >
                                        <Avatar class="h-8 w-8">
                                            <AvatarFallback class="text-xs">
                                                {{ getInitials(pupil.instructor_name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span
                                            class="font-medium text-primary hover:underline"
                                            @click="navigateToInstructor($event, pupil.instructor_id)"
                                        >
                                            {{ pupil.instructor_name }}
                                        </span>
                                    </div>
                                    <Badge
                                        v-else
                                        variant="secondary"
                                        class="gap-1"
                                    >
                                        <GraduationCap class="h-3 w-3" />
                                        Unassigned
                                    </Badge>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="pupils.length === 0">
                                <TableCell colspan="2" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No students found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div
                        v-if="pagination.last_page > 1"
                        class="flex flex-wrap items-center justify-between gap-2 border-t p-4"
                    >
                        <div class="text-sm text-muted-foreground">
                            Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }}
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!pagination.prev_page_url"
                                @click="goToPage(pagination.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <div class="text-sm">
                                Page {{ pagination.current_page }} of {{ pagination.last_page }}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!pagination.next_page_url"
                                @click="goToPage(pagination.next_page_url)"
                            >
                                Next
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Assign Instructor Sheet -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent class="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Assign Instructor</SheetTitle>
                    <SheetDescription>
                        This student has no instructor. Review their details
                        and assign them to a Stripe-onboarded instructor.
                    </SheetDescription>
                </SheetHeader>

                <div class="flex flex-col gap-6 px-4 py-4">
                    <div v-if="summaryLoading" class="text-sm text-muted-foreground">
                        Loading student details...
                    </div>

                    <template v-else-if="summary">
                        <!-- Summary -->
                        <div class="flex flex-col gap-3 rounded-md border p-4">
                            <div class="flex items-center gap-3">
                                <Avatar>
                                    <AvatarFallback>{{ getInitials(summary.name) }}</AvatarFallback>
                                </Avatar>
                                <div>
                                    <div class="font-semibold">{{ summary.name }}</div>
                                    <div
                                        v-if="summary.email"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ summary.email }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div class="text-xs text-muted-foreground">Status</div>
                                    <div class="font-medium capitalize">{{ summary.status }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Phone</div>
                                    <div class="font-medium">{{ summary.phone ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Lessons completed</div>
                                    <div class="font-medium">
                                        {{ summary.lessons_completed }} / {{ summary.lessons_total }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Lifetime revenue</div>
                                    <div class="font-medium">{{ formatRevenue(summary.revenue_pence) }}</div>
                                </div>
                            </div>
                        </div>

                        <DeleteLearnerSection
                            compact
                            :student-id="summary.id"
                            :student-name="summary.name"
                            @deleted="onLearnerDeleted"
                        />

                        <!-- Assign form -->
                        <div class="flex flex-col gap-2">
                            <Label for="assign-instructor">Assign to instructor</Label>
                            <select
                                id="assign-instructor"
                                v-model="selectedInstructorId"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="" disabled>
                                    Select an instructor...
                                </option>
                                <option
                                    v-for="instructor in props.onboardedInstructors"
                                    :key="instructor.id"
                                    :value="instructor.id"
                                >
                                    {{ instructor.name }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                Only instructors who have completed Stripe
                                onboarding are listed.
                            </p>
                        </div>

                        <p
                            v-if="errorMessage"
                            class="text-sm text-destructive"
                        >
                            {{ errorMessage }}
                        </p>
                    </template>

                    <p
                        v-else-if="errorMessage"
                        class="text-sm text-destructive"
                    >
                        {{ errorMessage }}
                    </p>
                </div>

                <SheetFooter>
                    <Button
                        variant="outline"
                        @click="sheetOpen = false"
                        :disabled="submitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="submitAssignment"
                        :disabled="!selectedInstructorId || submitting || !summary"
                    >
                        {{ submitting ? 'Assigning...' : 'Assign instructor' }}
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
