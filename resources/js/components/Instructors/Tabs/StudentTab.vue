<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { toast } from '@/components/ui/sonner'
import { ArrowLeft, Phone, Mail, StickyNote } from 'lucide-vue-next'
import type { InstructorDetail } from '@/types/instructor'
import OverviewSubTab from './Student/OverviewSubTab.vue'
import LessonsSubTab from './Student/LessonsSubTab.vue'
import PaymentsSubTab from './Student/PaymentsSubTab.vue'
import TransferSubTab from './Student/TransferSubTab.vue'
import EmergencyContactSubTab from './Student/EmergencyContactSubTab.vue'
import MessagesSubTab from './Student/MessagesSubTab.vue'
import ActionsSubTab from './Student/ActionsSubTab.vue'

interface StudentDetail {
    id: number
    user_id: number | null
    instructor_id: number | null
    name: string
    first_name: string | null
    surname: string | null
    email: string | null
    phone: string | null
    has_app: boolean
    lessons_completed: number
    lessons_total: number
    revenue_pence: number
    status: string
}

interface Props {
    instructor: InstructorDetail
    studentId: number
    subtab?: string
}

const props = withDefaults(defineProps<Props>(), {
    subtab: 'overview',
})

const student = ref<StudentDetail | null>(null)
const loading = ref(true)

type SubTabType = 'overview' | 'lessons' | 'payments' | 'transfer' | 'emergency' | 'messages' | 'actions'

const subTabs: { key: SubTabType; label: string }[] = [
    { key: 'overview', label: 'Overview' },
    { key: 'lessons', label: 'Lessons' },
    { key: 'payments', label: 'Payments' },
    { key: 'transfer', label: 'Transfer' },
    { key: 'emergency', label: 'Emergency Contact' },
    { key: 'messages', label: 'Messages' },
    { key: 'actions', label: 'Actions' },
]

const activeSubTab = computed(() => props.subtab || 'overview')

const switchSubTab = (subTabKey: SubTabType) => {
    router.visit(`/instructors/${props.instructor.id}`, {
        data: { tab: 'student', student: props.studentId, subtab: subTabKey },
        preserveState: true,
        preserveScroll: true,
    })
}

const isActiveSubTab = (subTabKey: SubTabType) => {
    return activeSubTab.value === subTabKey
}

const goBack = () => {
    router.visit(`/instructors/${props.instructor.id}`, {
        data: { tab: 'active-pupils' },
        preserveState: true,
        preserveScroll: true,
    })
}

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const loadStudent = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/students/${props.studentId}`)
        student.value = response.data.student
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to load student'
        toast.error(message)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadStudent()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Back Button -->
        <div>
            <Button variant="ghost" class="gap-2 cursor-pointer" @click="goBack">
                <ArrowLeft class="h-4 w-4" />
                Back to Pupils
            </Button>
        </div>

        <!-- Student Header -->
        <Card>
            <CardContent class="p-6">
                <!-- Loading Skeleton -->
                <div v-if="loading" class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <Skeleton class="h-20 w-20 rounded-full" />
                        <div class="flex flex-col gap-3">
                            <Skeleton class="h-8 w-48" />
                            <div class="flex gap-4">
                                <Skeleton class="h-4 w-32" />
                                <Skeleton class="h-4 w-44" />
                            </div>
                        </div>
                    </div>
                    <Skeleton class="h-10 w-28" />
                </div>

                <!-- Loaded Content -->
                <div v-else-if="student" class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <!-- Large Avatar -->
                        <Avatar class="h-20 w-20">
                            <AvatarFallback class="text-2xl">
                                {{ getInitials(student.name) }}
                            </AvatarFallback>
                        </Avatar>

                        <!-- Student Info -->
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <h2 class="text-3xl font-bold">{{ student.name }}</h2>
                                <Badge v-if="student.status" variant="outline">
                                    {{ student.status.charAt(0).toUpperCase() + student.status.slice(1) }}
                                </Badge>
                            </div>

                            <!-- Contact Info Row -->
                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <div
                                    v-if="student.phone"
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <Phone class="h-4 w-4" />
                                    <span>{{ student.phone }}</span>
                                </div>

                                <div
                                    v-if="student.email"
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <Mail class="h-4 w-4" />
                                    <span>{{ student.email }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="flex items-center gap-2">
                        <Button>
                            <StickyNote class="mr-2 h-4 w-4" />
                            Add Note
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Sub-tab Navigation + Content -->
        <Card>
            <!-- Sub-tab Navigation -->
            <div class="flex gap-1 border-b px-2">
                <button
                    v-for="subtab in subTabs"
                    :key="subtab.key"
                    @click="switchSubTab(subtab.key)"
                    :class="[
                        'px-4 py-3 text-sm font-medium transition-colors',
                        isActiveSubTab(subtab.key)
                            ? 'border-b-2 border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ subtab.label }}
                </button>
            </div>

            <!-- Sub-tab Content -->
            <CardContent class="p-6">
                <OverviewSubTab
                    v-if="activeSubTab === 'overview'"
                    :student-id="studentId"
                />
                <LessonsSubTab
                    v-if="activeSubTab === 'lessons'"
                    :student-id="studentId"
                />
                <PaymentsSubTab
                    v-if="activeSubTab === 'payments'"
                    :student-id="studentId"
                />
                <TransferSubTab
                    v-if="activeSubTab === 'transfer'"
                    :student-id="studentId"
                />
                <EmergencyContactSubTab
                    v-if="activeSubTab === 'emergency'"
                    :student-id="studentId"
                />
                <MessagesSubTab
                    v-if="activeSubTab === 'messages'"
                    :student-id="studentId"
                />
                <ActionsSubTab
                    v-if="activeSubTab === 'actions'"
                    :student-id="studentId"
                />
            </CardContent>
        </Card>
    </div>
</template>
