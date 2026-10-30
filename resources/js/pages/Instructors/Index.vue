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
import { Search, Plus, Download, FileUp, Check, X } from 'lucide-vue-next'
import type { Instructor, InstructorFormOptions } from '@/types/instructor'
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'
import CsvImportSheet from '@/components/CsvImportSheet.vue'
import { useRole } from '@/composables/useRole'
import { exportCsv } from '@/actions/App/Http/Controllers/InstructorController'

interface Props {
    instructors: Instructor[]
    formOptions: InstructorFormOptions
}

const props = defineProps<Props>()

const { isOwner } = useRole()

type SetupFilter = '' | 'complete' | 'incomplete'
type OnboardingEmailFilter = '' | 'sent' | 'not_sent'
type StripeFilter = '' | 'connected' | 'not_connected'
type AvailabilityFilter = '' | 'added' | 'not_added'

interface Filters {
    setup: SetupFilter
    onboarding_email: OnboardingEmailFilter
    stripe: StripeFilter
    availability: AvailabilityFilter
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
const stripeFilter = ref<StripeFilter>(fromQuery('stripe', ['connected', 'not_connected']))
const availabilityFilter = ref<AvailabilityFilter>(fromQuery('availability', ['added', 'not_added']))

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
    stripe: stripeFilter.value,
    availability: availabilityFilter.value,
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
        matchesFilter(instructor.stripe_connected, filters.stripe, 'connected') &&
        matchesFilter(instructor.availability_added, filters.availability, 'added')
    )
}

const filteredInstructors = computed(() =>
    props.instructors.filter((instructor) => matches(instructor, currentFilters.value)),
)

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
    stripeFilter.value = ''
    availabilityFilter.value = ''
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

watch(filterQuery, (query) => {
    const params = new URLSearchParams(query).toString()
    window.history.replaceState(
        window.history.state,
        '',
        `${window.location.pathname}${params ? `?${params}` : ''}`,
    )
})

const exportUrl = computed(() => exportCsv.url({ query: filterQuery.value }))

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const setupSteps = (instructor: Instructor) => [
    { label: instructor.onboarding_email_sent ? 'Sent' : 'Not sent', done: instructor.onboarding_email_sent },
    { label: instructor.stripe_connected ? 'Connected' : 'Not connected', done: instructor.stripe_connected },
    { label: instructor.availability_added ? 'Added' : 'Not added', done: instructor.availability_added },
]

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
                                <TableHead>Onboarding email</TableHead>
                                <TableHead>Stripe</TableHead>
                                <TableHead>Availability</TableHead>
                                <TableHead>Pupils</TableHead>
                                <TableHead>Last Sync</TableHead>
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
                                            <div class="font-semibold">
                                                {{ instructor.name }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ instructor.email }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell v-for="step in setupSteps(instructor)" :key="step.label">
                                    <Badge :variant="step.done ? 'secondary' : 'destructive'">
                                        <Check v-if="step.done" class="mr-1 h-3 w-3" />
                                        <X v-else class="mr-1 h-3 w-3" />
                                        {{ step.label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="font-medium">
                                        {{ instructor.pupils_count }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">
                                        {{ instructor.last_sync }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredInstructors.length === 0">
                                <TableCell colspan="6" class="text-center">
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
