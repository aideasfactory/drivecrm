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
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
    tab?: string
    subtab?: string
}

const props = withDefaults(defineProps<Props>(), {
    tab: 'schedule',
    subtab: 'summary',
})

const isEditSheetOpen = ref(false)

type TabType = 'schedule' | 'details' | 'active-pupils' | 'reports' | 'actions'

const tabs: { key: TabType; label: string }[] = [
    { key: 'schedule', label: 'Schedule' },
    { key: 'details', label: 'Details' },
    { key: 'active-pupils', label: 'Pupils' },
    { key: 'reports', label: 'Reports' },
    { key: 'actions', label: 'Actions' },
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

            <!-- Tab Navigation -->
            <div class="flex gap-1 border-b" v-if="instructor.onboarding_complete">
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

                <!-- Actions Tab -->
                <ActionsTab v-if="activeTab === 'actions'" />
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
