<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import SummarySubTab from './Details/SummarySubTab.vue'
import EditDetailsSubTab from './Details/EditDetailsSubTab.vue'
import CoverageSubTab from './Details/CoverageSubTab.vue'
import ActivitySubTab from './Details/ActivitySubTab.vue'
import EmergencyContactSubTab from './Details/EmergencyContactSubTab.vue'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
    subtab?: string
}

const props = withDefaults(defineProps<Props>(), {
    subtab: 'summary',
})

type SubTabType = 'summary' | 'edit' | 'coverage' | 'activity' | 'emergency'

const subTabs: { key: SubTabType; label: string }[] = [
    { key: 'summary', label: 'Summary' },
    { key: 'edit', label: 'Packages' },
    { key: 'coverage', label: 'Coverage' },
    { key: 'activity', label: 'Activity' },
    { key: 'emergency', label: 'Emergency Contact' },
]

const activeSubTab = computed(() => props.subtab || 'summary')

const switchSubTab = (subTabKey: SubTabType) => {
    router.visit(`/instructors/${props.instructor.id}`, {
        data: { tab: 'details', subtab: subTabKey },
        preserveState: true,
        preserveScroll: true,
    })
}

const isActiveSubTab = (subTabKey: SubTabType) => {
    return activeSubTab.value === subTabKey
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Sub-tab Navigation -->
        <div class="flex gap-1 border-b">
            <button
                v-for="subtab in subTabs"
                :key="subtab.key"
                @click="switchSubTab(subtab.key)"
                :class="[
                    'px-4 py-2 text-sm font-medium transition-colors',
                    isActiveSubTab(subtab.key)
                        ? 'border-b-2 border-primary text-foreground'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                {{ subtab.label }}
            </button>
        </div>

        <!-- Sub-tab Content -->
        <div>
            <SummarySubTab
                v-if="activeSubTab === 'summary'"
                :instructor="instructor"
            />
            <EditDetailsSubTab
                v-if="activeSubTab === 'edit'"
                :instructor="instructor"
            />
            <CoverageSubTab
                v-if="activeSubTab === 'coverage'"
                :instructor="instructor"
            />
            <ActivitySubTab v-if="activeSubTab === 'activity'" />
            <EmergencyContactSubTab v-if="activeSubTab === 'emergency'" />
        </div>
    </div>
</template>
