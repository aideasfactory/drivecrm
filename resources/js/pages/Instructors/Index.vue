<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
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
import { Search, Plus, Download, FileUp, X, ArrowUpDown, ArrowUp, ArrowDown, Mail, UserCheck, CreditCard, CalendarCheck } from 'lucide-vue-next'
import type { Instructor, InstructorFormOptions, LowAvailabilitySettings } from '@/types/instructor'
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'
import CsvImportSheet from '@/components/CsvImportSheet.vue'
import { useRole } from '@/composables/useRole'
import { exportCsv } from '@/actions/App/Http/Controllers/InstructorController'

interface Props {
    instructors: Instructor[]
    formOptions: InstructorFormOptions
    lowAvailability: LowAvailabilitySettings
}

const props = defineProps<Props>()

const { isOwner } = useRole()

type SetupFilter = '' | 'complete' | 'incomplete'
type OnboardingEmailFilter = '' | 'sent' | 'not_sent'
type SignedUpFilter = '' | 'signed_up' | 'not_signed_up'
type StripeFilter = '' | 'connected' | 'not_connected'
type AvailabilityFilter = '' | 'added' | 'not_added'
type LowAvailabilityFilter = '' | 'low' | 'not_low'
type SlotsSort = '' | 'asc' | 'desc'

interface Filters {
    setup: SetupFilter
    onboarding_email: OnboardingEmailFilter
    signed_up: SignedUpFilter
    stripe: StripeFilter
    availability: AvailabilityFilter
    low_availability: LowAvailabilityFilter
}

// Filters live in the page address so a chase list can be bookmarked or shared.
const initialQuery = new URLSearchParams(window.location.search)
const fromQuery = <T extends string>(key: string, allowed: T[]): T | '' => {
    const value = initialQuery.get(key) as T | null
    return value && allowed.includes(value) ? value : ''
}

const searchQuery = ref(initialQuery.get('search') ?? '')
const setupFilter = ref<SetupFilter>(fromQuery('setup', ['complete', 'incomplete']))
const onboardingEmailFilter = ref<OnboardingEmailFilter>(fromQuery('onboarding_email', ['sent', 'not_sent']))
const signedUpFilter = ref<SignedUpFilter>(fromQuery('signed_up', ['signed_up', 'not_signed_up']))
const stripeFilter = ref<StripeFilter>(fromQuery('stripe', ['connected', 'not_connected']))
const availabilityFilter = ref<AvailabilityFilter>(fromQuery('availability', ['added', 'not_added']))
const lowAvailabilityFilter = ref<LowAvailabilityFilter>(fromQuery('low_availability', ['low', 'not_low']))
const slotsSort = ref<SlotsSort>(fromQuery('sort_slots', ['asc', 'desc']))

const filterSelectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring'

const isAddSheetOpen = ref(false)
const isCsvImportOpen = ref(false)

// Instructor CSV import is hidden for now: it emails every new instructor
// immediately and doesn't record them as imported. Use Data Import instead.
// Set to true to bring the CSV Template / Upload CSV buttons back.
const SHOW_INSTRUCTOR_CSV_IMPORT = false

const handleCsvImported = () => {
    router.reload()
}

const matchesFilter = (value: boolean, filter: string, yes: string) =>
    filter === '' || value === (filter === yes)

/** All three setup steps done (same rule as GetInstructorListAction's `setup_complete`). */
const isSetupComplete = (instructor: Instructor) =>
    instructor.onboarding_email_sent && instructor.stripe_connected && instructor.availability_added

const currentFilters = computed<Filters>(() => ({
    setup: setupFilter.value,
    onboarding_email: onboardingEmailFilter.value,
    signed_up: signedUpFilter.value,
    stripe: stripeFilter.value,
    availability: availabilityFilter.value,
    low_availability: lowAvailabilityFilter.value,
}))

// Mirrors GetInstructorListAction::applyFilters so the CSV export matches the list on screen.
// Every filter must match (AND); `setup: incomplete` means missing any of the three steps.
const matches = (instructor: Instructor, filters: Filters) => {
    const query = searchQuery.value.trim().toLowerCase()

    return (
        (query === '' ||
            instructor.name.toLowerCase().includes(query) ||
            instructor.email.toLowerCase().includes(query)) &&
        matchesFilter(isSetupComplete(instructor), filters.setup, 'complete') &&
        matchesFilter(instructor.onboarding_email_sent, filters.onboarding_email, 'sent') &&
        matchesFilter(instructor.signed_up, filters.signed_up, 'signed_up') &&
        matchesFilter(instructor.stripe_connected, filters.stripe, 'connected') &&
        matchesFilter(instructor.availability_added, filters.availability, 'added') &&
        matchesFilter(instructor.low_availability, filters.low_availability, 'low')
    )
}

const filteredInstructors = computed(() => {
    const rows = props.instructors.filter((instructor) => matches(instructor, currentFilters.value))
    const direction = slotsSort.value === 'asc' ? 1 : slotsSort.value === 'desc' ? -1 : 0

    return direction === 0
        ? rows
        : [...rows].sort((a, b) => (a.total_available_slots - b.total_available_slots) * direction)
})

const toggleSlotsSort = () => {
    slotsSort.value = slotsSort.value === '' ? 'asc' : slotsSort.value === 'asc' ? 'desc' : ''
}

const slotsTitle = (instructor: Instructor) =>
    instructor.available_slots_by_week
        .map((slots, week) => `Week ${week + 1}: ${slots} slot${slots === 1 ? '' : 's'}`)
        .join('\n')

/** How many instructors an option would show, given the search and the other filters. */
const optionCount = <K extends keyof Filters>(key: K, value: Filters[K]) =>
    props.instructors.filter((instructor) =>
        matches(instructor, { ...currentFilters.value, [key]: value }),
    ).length

const hasActiveFilters = computed(
    () =>
        searchQuery.value.trim() !== '' ||
        Object.values(currentFilters.value).some((value) => value !== ''),
)

const clearFilters = () => {
    searchQuery.value = ''
    setupFilter.value = ''
    onboardingEmailFilter.value = ''
    signedUpFilter.value = ''
    stripeFilter.value = ''
    availabilityFilter.value = ''
    lowAvailabilityFilter.value = ''
}

const filterQuery = computed(() => {
    const query: Record<string, string> = {}
    const search = searchQuery.value.trim()

    if (search !== '') query.search = search

    for (const [key, value] of Object.entries(currentFilters.value)) {
        if (value) query[key] = value
    }

    return query
})

watch([filterQuery, slotsSort], ([query, sort]) => {
    const params = new URLSearchParams(sort ? { ...query, sort_slots: sort } : query).toString()
    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${params ? `?${params}` : ''}`,
    )
})

const exportUrl = computed(() => exportCsv.url({ query: filterQuery.value }))

const formatShortDate = (date: string) =>
    new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const setupSteps = (instructor: Instructor) => [
    {
        key: 'onboarding_email',
        icon: Mail,
        done: instructor.onboarding_email_sent,
        title: `Onboarding email: ${instructor.onboarding_email_sent ? 'Sent' : 'Not sent'}`,
    },
    {
        key: 'signed_up',
        icon: UserCheck,
        done: instructor.signed_up,
        title: instructor.signed_up
            ? 'Signed up'
            : ['Not signed up', notSignedUpTitle(instructor)].filter(Boolean).join(' — '),
    },
    {
        key: 'stripe',
        icon: CreditCard,
        done: instructor.stripe_connected,
        title: `Stripe: ${instructor.stripe_connected ? 'Connected' : 'Not connected'}`,
    },
    {
        key: 'availability',
        icon: CalendarCheck,
        done: instructor.availability_added,
        title: `Availability: ${instructor.availability_added ? 'Added' : 'Not added'}`,
    },
]

/** Hover text on the "Not signed up" badge: when the onboarding email last went out. */
const notSignedUpTitle = (instructor: Instructor) =>
    instructor.onboarding_email_last_sent_at
        ? `Onboarding email last sent ${formatShortDate(instructor.onboarding_email_last_sent_at)}`
        : undefined

const navigateToInstructor = (instructorId: number) => {
    router.visit(`/instructors/${instructorId}`)
}

const breadcrumbs = [{ title: 'Instructors' }]
</script>

<template>
    <Head title="Instructors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header and Actions -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-2">
                    <h2 class="text-3xl font-bold">Instructors</h2>
                    <p class="text-muted-foreground">
                        Manage your driving instructors and their performance
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="isOwner" variant="outline" as="a" :href="exportUrl">
                        <Download class="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                    <Button v-if="SHOW_INSTRUCTOR_CSV_IMPORT" variant="outline" as="a" href="/instructors/csv-template">
                        <Download class="mr-2 h-4 w-4" />
                        CSV Template
                    </Button>
                    <Button v-if="SHOW_INSTRUCTOR_CSV_IMPORT" variant="outline" @click="isCsvImportOpen = true">
                        <FileUp class="mr-2 h-4 w-4" />
                        Upload CSV
                    </Button>
                    <Button @click="isAddSheetOpen = true" class="cursor-pointer">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Instructor
                    </Button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex flex-1 flex-wrap items-center gap-2">
                    <div class="relative w-full max-w-md flex-1 basis-64">
                        <Search
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search instructors..."
                            class="pl-9"
                        />
                    </div>
                    <select
                        v-model="setupFilter"
                        aria-label="Filter by setup"
                        :class="filterSelectClass"
                    >
                        <option value="">Setup: All ({{ optionCount('setup', '') }})</option>
                        <option value="incomplete">Setup: Incomplete ({{ optionCount('setup', 'incomplete') }})</option>
                        <option value="complete">Setup: Complete ({{ optionCount('setup', 'complete') }})</option>
                    </select>
                    <select
                        v-model="onboardingEmailFilter"
                        aria-label="Filter by onboarding email"
                        :class="filterSelectClass"
                    >
                        <option value="">Onboarding email: All ({{ optionCount('onboarding_email', '') }})</option>
                        <option value="sent">Onboarding email: Sent ({{ optionCount('onboarding_email', 'sent') }})</option>
                        <option value="not_sent">Onboarding email: Not sent ({{ optionCount('onboarding_email', 'not_sent') }})</option>
                    </select>
                    <select
                        v-model="signedUpFilter"
                        aria-label="Filter by sign-up"
                        :class="filterSelectClass"
                    >
                        <option value="">Signed up: All ({{ optionCount('signed_up', '') }})</option>
                        <option value="signed_up">Signed up: Yes ({{ optionCount('signed_up', 'signed_up') }})</option>
                        <option value="not_signed_up">Signed up: No ({{ optionCount('signed_up', 'not_signed_up') }})</option>
                    </select>
                    <select
                        v-model="stripeFilter"
                        aria-label="Filter by Stripe connection"
                        :class="filterSelectClass"
                    >
                        <option value="">Stripe: All ({{ optionCount('stripe', '') }})</option>
                        <option value="connected">Stripe: Connected ({{ optionCount('stripe', 'connected') }})</option>
                        <option value="not_connected">Stripe: Not connected ({{ optionCount('stripe', 'not_connected') }})</option>
                    </select>
                    <select
                        v-model="availabilityFilter"
                        aria-label="Filter by availability"
                        :class="filterSelectClass"
                    >
                        <option value="">Availability: All ({{ optionCount('availability', '') }})</option>
                        <option value="added">Availability: Added ({{ optionCount('availability', 'added') }})</option>
                        <option value="not_added">Availability: Not added ({{ optionCount('availability', 'not_added') }})</option>
                    </select>
                    <select
                        v-model="lowAvailabilityFilter"
                        aria-label="Filter by low availability"
                        :class="filterSelectClass"
                    >
                        <option value="">Low availability: All ({{ optionCount('low_availability', '') }})</option>
                        <option value="low">Low availability: Yes ({{ optionCount('low_availability', 'low') }})</option>
                        <option value="not_low">Low availability: No ({{ optionCount('low_availability', 'not_low') }})</option>
                    </select>
                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                        <X class="mr-2 h-4 w-4" />
                        Clear filters
                    </Button>
                </div>
            </div>

            <p class="-mt-2 text-sm text-muted-foreground">
                Showing {{ filteredInstructors.length }} of {{ props.instructors.length }} instructors
            </p>

            <!-- Instructors Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead title="Onboarding email · Signed up · Stripe · Availability">Setup</TableHead>
                                <TableHead>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 whitespace-nowrap hover:text-foreground"
                                        :title="`Diary slots added (open or booked) over the next ${props.lowAvailability.weeks} weeks`"
                                        @click="toggleSlotsSort"
                                    >
                                        Slots added
                                        <ArrowUp v-if="slotsSort === 'asc'" class="h-3 w-3" />
                                        <ArrowDown v-else-if="slotsSort === 'desc'" class="h-3 w-3" />
                                        <ArrowUpDown v-else class="h-3 w-3" />
                                    </button>
                                </TableHead>
                                <TableHead class="text-right">Pupils</TableHead>
                                <TableHead class="whitespace-nowrap">Last updated</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="instructor in filteredInstructors"
                                :key="instructor.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToInstructor(instructor.id)"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Avatar>
                                            <AvatarFallback>
                                                {{
                                                    getInitials(instructor.name)
                                                }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div class="flex items-center gap-2 font-semibold">
                                                {{ instructor.name }}
                                                <Badge v-if="instructor.pending_deletion_on" variant="destructive">
                                                    Deleting {{ formatShortDate(instructor.pending_deletion_on) }}
                                                </Badge>
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ instructor.email }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            v-for="step in setupSteps(instructor)"
                                            :key="step.key"
                                            :title="step.title"
                                            :aria-label="step.title"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md"
                                            :class="step.done
                                                ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400'
                                                : 'bg-destructive/15 text-destructive'"
                                        >
                                            <component :is="step.icon" class="h-3.5 w-3.5" />
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2 whitespace-nowrap" :title="slotsTitle(instructor)">
                                        <span class="font-medium tabular-nums">
                                            {{ instructor.total_available_slots }}
                                        </span>
                                        <Badge v-if="instructor.low_availability" variant="destructive">
                                            Low
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span class="font-medium tabular-nums">
                                        {{ instructor.pupils_count }}
                                    </span>
                                </TableCell>
                                <TableCell class="whitespace-nowrap">
                                    <span class="text-sm text-muted-foreground">
                                        {{ instructor.last_sync }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredInstructors.length === 0">
                                <TableCell colspan="5" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No instructors found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Add Instructor Sheet -->
        <AddInstructorSheet
            v-model:open="isAddSheetOpen"
            :form-options="props.formOptions"
            @instructor-created="isAddSheetOpen = false"
        />

        <!-- CSV Import Sheet -->
        <CsvImportSheet
            v-model:open="isCsvImportOpen"
            title="Import Instructors from CSV"
            description="Upload a CSV file to bulk-create instructor records. Download the template first to see the required format."
            import-url="/instructors/import-csv"
            @imported="handleCsvImported"
        />
    </AppLayout>
</template>
