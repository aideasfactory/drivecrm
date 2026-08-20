<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useRole } from '@/composables/useRole'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
    BookOpen,
    PoundSterling,
    Smartphone,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'
import NotesCard from './NotesCard.vue'

interface StudentOverview {
    lessons_completed: number
    lessons_total: number
    revenue_pence: number
    has_app: boolean
}

interface Props {
    studentId: number
}

const props = defineProps<Props>()

// Student overview state
const overview = ref<StudentOverview | null>(null)
const overviewLoading = ref(true)

// Internal notes are admin-only (role: owner); the endpoints enforce this server-side too
const { isOwner } = useRole()

const formatCurrency = (pence: number): string => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(pence / 100)
}

// Load student overview data
const loadOverview = async () => {
    overviewLoading.value = true
    try {
        const response = await axios.get(`/students/${props.studentId}`)
        overview.value = response.data.student
    } catch {
        toast.error('Failed to load student overview')
    } finally {
        overviewLoading.value = false
    }
}

onMounted(() => {
    loadOverview()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Hero Stat Cards -->
        <div v-if="overviewLoading" class="grid grid-cols-1 gap-4 md:grid-cols-3">
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

        <div v-else-if="overview" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Total Lessons -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">
                                {{ overview.lessons_completed }}/{{ overview.lessons_total }}
                            </p>
                            <p class="text-sm text-muted-foreground">Total Lessons</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Total Revenue -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <PoundSterling class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">
                                {{ formatCurrency(overview.revenue_pence) }}
                            </p>
                            <p class="text-sm text-muted-foreground">Total Revenue</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- App Installed -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <Smartphone class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <Badge :variant="overview.has_app ? 'default' : 'secondary'" class="text-sm">
                                {{ overview.has_app ? 'Installed' : 'Not Installed' }}
                            </Badge>
                            <p class="mt-1 text-sm text-muted-foreground">App Installed</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Notes: admins see internal (private) + shared side by side; instructors only shared -->
        <div class="grid grid-cols-1 items-start gap-6" :class="isOwner ? 'lg:grid-cols-2' : ''">
            <NotesCard v-if="isOwner" :student-id="studentId" internal />
            <NotesCard :student-id="studentId" />
        </div>
    </div>
</template>
