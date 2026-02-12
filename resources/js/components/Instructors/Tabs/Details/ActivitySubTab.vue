<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Car,
    Calendar,
    MessageSquare,
    CreditCard,
    User,
    Package,
    Users,
    Search,
    Activity,
    Loader2,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import type { InstructorDetail } from '@/types/instructor'

interface ActivityLog {
    id: number
    category: string
    message: string
    metadata: Record<string, unknown> | null
    created_at: string
}

interface PaginationMeta {
    current_page: number
    total: number
    per_page: number
    last_page: number
}

const props = defineProps<{
    instructor: InstructorDetail
}>()

// State
const logs = ref<ActivityLog[]>([])
const meta = ref<PaginationMeta | null>(null)
const isLoading = ref(true)
const isLoadingMore = ref(false)
const searchQuery = ref('')
const activeCategory = ref('all')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

// Category configuration
const categories = [
    { key: 'all', label: 'All', icon: null },
    { key: 'lesson', label: 'Lessons', icon: Car },
    { key: 'booking', label: 'Bookings', icon: Calendar },
    { key: 'message', label: 'Messages', icon: MessageSquare },
    { key: 'payment', label: 'Payments', icon: CreditCard },
    { key: 'profile', label: 'Profile', icon: User },
]

const categoryIconMap: Record<string, typeof Car> = {
    lesson: Car,
    booking: Calendar,
    message: MessageSquare,
    payment: CreditCard,
    profile: User,
    package: Package,
    student: Users,
    instructor: User,
}

// Computed
const hasLogs = computed(() => logs.value.length > 0)
const hasMorePages = computed(() => {
    if (!meta.value) return false
    return meta.value.current_page < meta.value.last_page
})

// Relative time formatting
const timeAgo = (dateString: string): string => {
    const date = new Date(dateString)
    const now = new Date()
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)

    if (seconds < 60) return 'Just now'

    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`

    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours} hour${hours !== 1 ? 's' : ''} ago`

    const days = Math.floor(hours / 24)
    if (days < 7) return `${days} day${days !== 1 ? 's' : ''} ago`

    const weeks = Math.floor(days / 7)
    if (weeks < 4) return `${weeks} week${weeks !== 1 ? 's' : ''} ago`

    const months = Math.floor(days / 30)
    if (months < 12) return `${months} month${months !== 1 ? 's' : ''} ago`

    const years = Math.floor(days / 365)
    return `${years} year${years !== 1 ? 's' : ''} ago`
}

// Get icon component for a category
const getCategoryIcon = (category: string) => {
    return categoryIconMap[category] || Activity
}

// Load activity logs
const loadLogs = async (page = 1, append = false) => {
    if (page === 1) {
        isLoading.value = true
    } else {
        isLoadingMore.value = true
    }

    try {
        const params: Record<string, string | number> = { page }

        if (activeCategory.value !== 'all') {
            params.category = activeCategory.value
        }

        if (searchQuery.value.trim()) {
            params.search = searchQuery.value.trim()
        }

        const response = await axios.get(
            `/instructors/${props.instructor.id}/activity-logs`,
            { params }
        )

        if (append) {
            logs.value.push(...response.data.logs)
        } else {
            logs.value = response.data.logs || []
        }

        meta.value = response.data.meta
    } catch (error) {
        console.error('Error loading activity logs:', error)
        toast({ title: 'Failed to load activity logs', variant: 'destructive' })
    } finally {
        isLoading.value = false
        isLoadingMore.value = false
    }
}

// Load more (next page)
const loadMore = () => {
    if (!meta.value || !hasMorePages.value) return
    loadLogs(meta.value.current_page + 1, true)
}

// Filter by category
const setCategory = (category: string) => {
    activeCategory.value = category
    loadLogs()
}

// Debounced search
const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        loadLogs()
    }, 300)
}

// Watch search input
watch(searchQuery, handleSearch)

onMounted(() => {
    loadLogs()
})
</script>

<template>
    <div>
        <!-- Header: Title + Search -->
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <Activity class="h-5 w-5" />
                Activity Timeline
            </h3>
            <div class="relative w-full lg:w-64">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search activity..."
                    class="pl-9"
                />
            </div>
        </div>

        <!-- Filter Badges -->
        <div class="mb-6 flex flex-wrap gap-2">
            <Badge
                v-for="cat in categories"
                :key="cat.key"
                :variant="activeCategory === cat.key ? 'default' : 'secondary'"
                class="cursor-pointer px-3 py-1 text-sm"
                @click="setCategory(cat.key)"
            >
                <component
                    v-if="cat.icon"
                    :is="cat.icon"
                    class="mr-1 h-3 w-3"
                />
                {{ cat.label }}
            </Badge>
        </div>

        <!-- Loading Skeletons -->
        <Card v-if="isLoading">
            <CardContent class="space-y-4 p-6">
                <div v-for="n in 5" :key="n" class="space-y-2 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Skeleton class="h-5 w-5 rounded-full" />
                            <Skeleton class="h-4 w-40" />
                        </div>
                        <Skeleton class="h-3 w-20" />
                    </div>
                    <Skeleton class="h-4 w-3/4" />
                    <Skeleton class="h-3 w-1/2" />
                </div>
            </CardContent>
        </Card>

        <!-- Timeline Content -->
        <Card v-else>
            <CardContent class="max-h-[600px] overflow-y-auto p-6">
                <!-- Empty State -->
                <div
                    v-if="!hasLogs"
                    class="flex min-h-[300px] flex-col items-center justify-center gap-4 text-muted-foreground"
                >
                    <Activity class="h-12 w-12" />
                    <div class="text-center">
                        <p class="text-lg font-medium">No activity found</p>
                        <p class="mt-1 text-sm">
                            {{ searchQuery || activeCategory !== 'all'
                                ? 'Try adjusting your search or filters'
                                : 'Activity will appear here as actions are logged'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Timeline Items -->
                <div v-else class="space-y-4">
                    <div
                        v-for="log in logs"
                        :key="log.id"
                        class="rounded-lg border p-4"
                    >
                        <div class="mb-2 flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <component
                                    :is="getCategoryIcon(log.category)"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <Badge variant="secondary" class="text-xs">
                                    {{ log.category }}
                                </Badge>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ timeAgo(log.created_at) }}
                            </span>
                        </div>
                        <p class="text-sm">{{ log.message }}</p>
                        <div
                            v-if="log.metadata"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            <span
                                v-for="(value, key) in log.metadata"
                                :key="String(key)"
                                class="mr-3"
                            >
                                {{ String(key) }}: {{ value }}
                            </span>
                        </div>
                    </div>

                    <!-- Load More Button -->
                    <div v-if="hasMorePages" class="flex justify-center pt-4">
                        <Button
                            variant="outline"
                            @click="loadMore"
                            :disabled="isLoadingMore"
                            class="min-w-[140px]"
                        >
                            <Loader2
                                v-if="isLoadingMore"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Load More
                        </Button>
                    </div>
                </div>

                <!-- Total count -->
                <div v-if="meta && hasLogs" class="mt-4 text-center text-xs text-muted-foreground">
                    Showing {{ logs.length }} of {{ meta.total }} activities
                </div>
            </CardContent>
        </Card>
    </div>
</template>
