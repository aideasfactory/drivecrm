<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import InstructorHeader from '@/components/Instructors/InstructorHeader.vue'
import ScheduleTab from '@/components/Instructors/Tabs/ScheduleTab.vue'
import DetailsTab from '@/components/Instructors/Tabs/DetailsTab.vue'
import ActivePupilsTab from '@/components/Instructors/Tabs/ActivePupilsTab.vue'
import ActionsTab from '@/components/Instructors/Tabs/ActionsTab.vue'
import ReportsTab from '@/components/Instructors/Tabs/ReportsTab.vue'
import FinancesTab from '@/components/Instructors/Tabs/FinancesTab.vue'
import StudentTab from '@/components/Instructors/Tabs/StudentTab.vue'
import HmrcTab from '@/components/Instructors/Tabs/HmrcTab.vue'
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'
import type { InstructorDetail } from '@/types/instructor'

interface ConnectionStatus {
    connected: boolean
    connected_at: string | null
    expires_at: string | null
    refresh_expires_at: string | null
    scopes: string[]
    days_until_refresh_expiry: number | null
}

interface TaxProfile {
    completed_at: string | null
    business_type: string | null
    vat_registered: boolean
    vrn: string | null
    utr: string | null
    nino: string | null
    companies_house_number: string | null
}

interface ItsaThreshold {
    date: string
    income: number
    label: string
}

interface Applicability {
    profile_complete: boolean
    business_type: string | null
    vat: { applies: boolean; vrn: string | null }
    itsa: { applies: boolean; status: string; thresholds: ItsaThreshold[] }
    corporation_tax: { applies: false; reason: string }
    summary: string
}

interface BusinessTypeOption {
    value: string
    label: string
}

interface HmrcData {
    environment: string
    connection: ConnectionStatus
    helloWorldResponse: Record<string, unknown> | null
    taxProfile: TaxProfile | null
    applicability: Applicability | null
    businessTypes: BusinessTypeOption[]
}

interface Props {
    instructor: InstructorDetail
    tab?: string
    subtab?: string
    student?: number
    hmrc?: HmrcData | null
}

const props = withDefaults(defineProps<Props>(), {
    tab: 'schedule',
    subtab: 'summary',
})

const isEditSheetOpen = ref(false)

type TabType = 'schedule' | 'details' | 'active-pupils' | 'reports' | 'finances' | 'actions' | 'student' | 'hmrc'

const tabs: { key: TabType; label: string }[] = [
    { key: 'schedule', label: 'Schedule' },
    { key: 'details', label: 'Details' },
    { key: 'active-pupils', label: 'Pupils' },
    { key: 'reports', label: 'Reports' },
    { key: 'finances', label: 'Finances' },
    // { key: 'actions', label: 'Actions' }, // Temporarily hidden — will revisit once functionality is defined
]

const activeTab = computed(() => props.tab || 'schedule')

const switchTab = (tabKey: TabType) => {
    router.visit(`/instructors/${props.instructor.id}`, {
        data: { tab: tabKey },
        preserveState: true,
        preserveScroll: true,
    })
}

const isActiveTab = (tabKey: TabType) => {
    return activeTab.value === tabKey
}

const breadcrumbs = [
    { title: 'Instructors', href: '/instructors' },
    { title: props.instructor.name },
]
</script>

<template>
    <Head :title="instructor.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Instructor Header -->
            <InstructorHeader
                :instructor="instructor"
                @edit="isEditSheetOpen = true"
            />

            <!-- Tab Navigation (hidden when viewing a student or HMRC sub-page) -->
            <div class="flex gap-1 border-b" v-if="instructor.onboarding_complete && activeTab !== 'student' && activeTab !== 'hmrc'">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="switchTab(tab.key)"
                    :class="[
                        'px-4 py-2 font-medium text-sm transition-colors',
                        isActiveTab(tab.key)
                            ? 'border-b-2 border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab Content -->
            <div class="flex flex-col gap-6" v-if="instructor.onboarding_complete">
                <!-- Schedule Tab -->
                <ScheduleTab v-if="activeTab === 'schedule'" :instructor-id="instructor.id" />

                <!-- Details Tab -->
                <DetailsTab
                    v-if="activeTab === 'details'"
                    :instructor="instructor"
                    :subtab="subtab"
                />

                <!-- Reports Tab -->
                <ReportsTab
                    v-if="activeTab === 'reports'"
                    :instructor="instructor"
                />

                <!-- Active Pupils Tab -->
                <ActivePupilsTab
                    v-if="activeTab === 'active-pupils'"
                    :instructor="instructor"
                />

                <!-- Finances Tab -->
                <FinancesTab
                    v-if="activeTab === 'finances'"
                    :instructor="instructor"
                />

                <!-- Actions Tab -->
                <ActionsTab v-if="activeTab === 'actions'" />

                <!-- Student Detail Tab -->
                <StudentTab
                    v-if="activeTab === 'student' && student"
                    :instructor="instructor"
                    :student-id="student"
                    :subtab="subtab"
                />

                <!-- HMRC Tab -->
                <HmrcTab
                    v-if="activeTab === 'hmrc' && hmrc"
                    :instructor-id="instructor.id"
                    :hmrc="hmrc"
                />
            </div>
        </div>

        <!-- Edit Instructor Sheet -->
        <AddInstructorSheet
            v-model:open="isEditSheetOpen"
            :instructor="instructor"
            @instructor-updated="isEditSheetOpen = false"
        />
    </AppLayout>
</template>
