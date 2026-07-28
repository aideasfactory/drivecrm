<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Search, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { index } from '@/routes/enquiries'

interface Enquiry {
    id: string
    source: 'booking' | 'onboarding'
    total_steps: number
    current_step: number
    max_step_reached: number
    status: 'completed' | 'full_onboarding' | 'in_progress'
    is_complete: boolean
    in_area: boolean | null
    first_name: string | null
    last_name: string | null
    email: string | null
    phone: string | null
    postcode: string | null
    transmission: 'manual' | 'automatic' | 'both' | null
    created_at: string | null
    updated_at: string | null
    data: Record<string, unknown>
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Paginator {
    data: Enquiry[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    prev_page_url: string | null
    next_page_url: string | null
    links: PaginationLink[]
}

interface Filters {
    status: 'all' | 'completed' | 'full_onboarding' | 'in_progress'
    area: 'all' | 'in_area' | 'out_of_area' | 'unknown'
}

interface Props {
    enquiries: Paginator
    filters: Filters
}

const props = defineProps<Props>()

const statusOptions: { value: Filters['status']; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'completed', label: 'Completed' },
    { value: 'full_onboarding', label: 'Full onboarding' },
    { value: 'in_progress', label: 'In progress' },
]

const areaOptions: { value: Filters['area']; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'in_area', label: 'In area' },
    { value: 'out_of_area', label: 'Out of area' },
    { value: 'unknown', label: 'Unknown' },
]

const applyFilters = (changes: Partial<Filters>) => {
    router.get(
        index.url(),
        { ...props.filters, ...changes },
        { preserveScroll: true, preserveState: true },
    )
}

const searchQuery = ref('')
const selectedEnquiry = ref<Enquiry | null>(null)
const isSheetOpen = ref(false)

const filteredEnquiries = computed(() => {
    if (!searchQuery.value) {
        return props.enquiries.data
    }

    const query = searchQuery.value.toLowerCase()
    return props.enquiries.data.filter((e) => {
        const name = `${e.first_name ?? ''} ${e.last_name ?? ''}`.toLowerCase()
        return (
            name.includes(query) ||
            (e.email?.toLowerCase().includes(query) ?? false) ||
            (e.postcode?.toLowerCase().includes(query) ?? false) ||
            (e.phone?.toLowerCase().includes(query) ?? false)
        )
    })
})

const formatDate = (iso: string | null) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
        ', ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

const fullName = (e: Enquiry) => {
    const name = `${e.first_name ?? ''} ${e.last_name ?? ''}`.trim()
    return name || '—'
}

const openEnquiry = (e: Enquiry) => {
    selectedEnquiry.value = e
    isSheetOpen.value = true
}

const goToPage = (url: string | null) => {
    if (!url) return
    router.visit(url, { preserveScroll: true, preserveState: true })
}

const selectedSteps = computed(() => {
    const data = selectedEnquiry.value?.data as { steps?: Record<string, Record<string, unknown>> } | undefined
    const stepsRaw = data?.steps
    if (!stepsRaw || Array.isArray(stepsRaw)) return []
    return Object.entries(stepsRaw)
        .map(([key, value]) => ({
            key,
            label: key.replace(/^step/, 'Step '),
            entries: Object.entries(value ?? {}),
        }))
        .sort((a, b) => a.key.localeCompare(b.key))
})

const transmissionLabel = (value: Enquiry['transmission']) => {
    switch (value) {
        case 'manual': return 'Manual'
        case 'automatic': return 'Automatic'
        case 'both': return 'Either'
        default: return '—'
    }
}

const inAreaLabel = (e: Enquiry) => {
    if (e.in_area === null) return null
    return e.in_area ? 'In area' : 'Out of area'
}

const statusBadge = (e: Enquiry): { label: string; variant: 'default' | 'secondary' | 'outline' } => {
    switch (e.status) {
        case 'completed': return { label: 'Completed', variant: 'default' }
        case 'full_onboarding': return { label: 'Full onboarding', variant: 'secondary' }
        default: return { label: 'In progress', variant: 'outline' }
    }
}

const breadcrumbs = [{ title: 'Enquiries' }]
</script>

<template>
    <Head title="Enquiries" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Enquiries</h2>
                <p class="text-muted-foreground">
                    Every enquiry started via the onboarding or booking flows, with the step they reached.
                </p>
            </div>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-wrap items-center gap-4 mb-4">
                        <div class="relative flex-1 max-w-md">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search name, email, phone or postcode"
                                class="pl-9"
                            />
                        </div>
                        <div class="flex items-center gap-1 rounded-md border p-1">
                            <Button
                                v-for="option in statusOptions"
                                :key="option.value"
                                :variant="filters.status === option.value ? 'default' : 'ghost'"
                                size="sm"
                                @click="applyFilters({ status: option.value })"
                            >
                                {{ option.label }}
                            </Button>
                        </div>
                        <div class="flex items-center gap-1 rounded-md border p-1">
                            <Button
                                v-for="option in areaOptions"
                                :key="option.value"
                                :variant="filters.area === option.value ? 'default' : 'ghost'"
                                size="sm"
                                @click="applyFilters({ area: option.value })"
                            >
                                {{ option.label }}
                            </Button>
                        </div>
                        <div class="text-sm text-muted-foreground ml-auto">
                            {{ enquiries.total }} total
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Created</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Phone</TableHead>
                                <TableHead>Postcode</TableHead>
                                <TableHead>Transmission</TableHead>
                                <TableHead>Step</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="enquiry in filteredEnquiries"
                                :key="enquiry.id"
                                class="cursor-pointer"
                                @click="openEnquiry(enquiry)"
                            >
                                <TableCell class="whitespace-nowrap text-sm">
                                    {{ formatDate(enquiry.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="enquiry.source === 'booking' ? 'default' : 'secondary'">
                                        {{ enquiry.source === 'booking' ? 'Booking' : 'Onboarding' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="font-medium">{{ fullName(enquiry) }}</TableCell>
                                <TableCell class="text-sm">{{ enquiry.email ?? '—' }}</TableCell>
                                <TableCell class="text-sm">{{ enquiry.phone ?? '—' }}</TableCell>
                                <TableCell class="text-sm">{{ enquiry.postcode ?? '—' }}</TableCell>
                                <TableCell class="text-sm">{{ transmissionLabel(enquiry.transmission) }}</TableCell>
                                <TableCell class="text-sm whitespace-nowrap">
                                    {{ enquiry.max_step_reached }}/{{ enquiry.total_steps }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="statusBadge(enquiry).variant">
                                        {{ statusBadge(enquiry).label }}
                                    </Badge>
                                    <Badge
                                        v-if="inAreaLabel(enquiry)"
                                        :variant="inAreaLabel(enquiry) === 'In area' ? 'default' : 'secondary'"
                                        class="ml-2"
                                    >
                                        {{ inAreaLabel(enquiry) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredEnquiries.length === 0">
                                <TableCell colspan="9" class="text-center text-muted-foreground py-8">
                                    No enquiries match your search or filters.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div class="flex items-center justify-between mt-4" v-if="enquiries.last_page > 1">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ enquiries.from ?? 0 }}–{{ enquiries.to ?? 0 }} of {{ enquiries.total }}
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!enquiries.prev_page_url"
                                @click="goToPage(enquiries.prev_page_url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                Previous
                            </Button>
                            <div class="text-sm">
                                Page {{ enquiries.current_page }} of {{ enquiries.last_page }}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="!enquiries.next_page_url"
                                @click="goToPage(enquiries.next_page_url)"
                            >
                                Next
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Sheet v-model:open="isSheetOpen">
            <SheetContent class="w-full sm:max-w-lg overflow-y-auto">
                <SheetHeader>
                    <SheetTitle>Enquiry details</SheetTitle>
                    <SheetDescription v-if="selectedEnquiry">
                        {{ selectedEnquiry.source === 'booking' ? 'Booking' : 'Onboarding' }} enquiry
                        — created {{ formatDate(selectedEnquiry.created_at) }}
                    </SheetDescription>
                </SheetHeader>

                <div v-if="selectedEnquiry" class="mt-6 space-y-6 px-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <Badge v-if="selectedEnquiry.status !== 'in_progress'" :variant="statusBadge(selectedEnquiry).variant">
                                {{ statusBadge(selectedEnquiry).label }}
                            </Badge>
                            <Badge v-else variant="outline">
                                In progress — step {{ selectedEnquiry.max_step_reached }} of {{ selectedEnquiry.total_steps }}
                            </Badge>
                            <Badge
                                v-if="inAreaLabel(selectedEnquiry)"
                                :variant="inAreaLabel(selectedEnquiry) === 'In area' ? 'default' : 'secondary'"
                            >
                                {{ inAreaLabel(selectedEnquiry) }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground font-mono break-all">
                            {{ selectedEnquiry.id }}
                        </p>
                    </div>

                    <div v-for="section in selectedSteps" :key="section.key" class="space-y-2">
                        <h4 class="font-semibold text-sm">{{ section.label }}</h4>
                        <div class="rounded-md border divide-y">
                            <div
                                v-for="[k, v] in section.entries"
                                :key="k"
                                class="flex justify-between gap-4 px-3 py-2 text-sm"
                            >
                                <span class="text-muted-foreground">{{ k }}</span>
                                <span class="text-right font-mono text-xs break-all">{{ JSON.stringify(v) }}</span>
                            </div>
                            <div v-if="section.entries.length === 0" class="px-3 py-2 text-sm text-muted-foreground">
                                No data captured.
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedSteps.length === 0" class="text-sm text-muted-foreground">
                        No step data captured — the user did not get past the landing page.
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
