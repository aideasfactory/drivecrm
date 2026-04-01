<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { toast } from '@/components/ui/sonner'
import {
    Activity,
    Search,
    Loader2,
    StickyNote,
    Mail,
    Bell,
    MessageSquare,
    CalendarCheck,
    CreditCard,
    UserCog,
    Filter,
    ExternalLink,
} from 'lucide-vue-next'

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

interface Props {
    studentId: number
}

const props = defineProps<Props>()

const logs = ref<ActivityLog[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const isLoadingMore = ref(false)
const searchQuery = ref('')
const activeCategory = ref('all')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const categories: { key: string; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'profile', label: 'Profile' },
    { key: 'note', label: 'Notes' },
    { key: 'notification', label: 'Notifications' },
    { key: 'message', label: 'Messages' },
    { key: 'booking', label: 'Bookings' },
    { key: 'payment', label: 'Payments' },
]

const hasMorePages = computed(() => {
    if (!meta.value) return false
    return meta.value.current_page < meta.value.last_page
})

const categoryIcon = (category: string) => {
    switch (category) {
        case 'note': return StickyNote
        case 'notification': return Bell
        case 'message': return MessageSquare
        case 'booking': return CalendarCheck
        case 'payment': return CreditCard
        case 'profile': return UserCog
        default: return Activity
    }
}

const categoryVariant = (category: string): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (category) {
        case 'notification': return 'default'
        case 'payment': return 'destructive'
        default: return 'secondary'
    }
}

const formatDate = (dateString: string): string => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

    const time = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })

    if (diffDays === 0) return `Today at ${time}`
    if (diffDays === 1) return `Yesterday at ${time}`
    if (diffDays < 7) return `${date.toLocaleDateString('en-GB', { weekday: 'long' })} at ${time}`

    return `${date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })} at ${time}`
}

const loadLogs = async (page = 1, append = false) => {
    if (page === 1) {
        loading.value = true
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

        const response = await axios.get(`/students/${props.studentId}/activity-logs`, { params })

        if (append) {
            logs.value.push(...response.data.logs)
        } else {
            logs.value = response.data.logs || []
        }

        meta.value = response.data.meta
    } catch {
        toast.error('Failed to load activity log')
    } finally {
        loading.value = false
        isLoadingMore.value = false
    }
}

const loadMore = () => {
    if (!meta.value || !hasMorePages.value) return
    loadLogs(meta.value.current_page + 1, true)
}

const setCategory = (category: string) => {
    activeCategory.value = category
    loadLogs()
}

const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        loadLogs()
    }, 300)
}

watch(searchQuery, handleSearch)

onMounted(() => {
    loadLogs()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Filters Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <!-- Category Filters -->
            <div class="flex flex-wrap gap-1">
                <Button
                    v-for="cat in categories"
                    :key="cat.key"
                    :variant="activeCategory === cat.key ? 'default' : 'outline'"
                    size="sm"
                    @click="setCategory(cat.key)"
                >
                    <Filter v-if="cat.key === 'all'" class="mr-1.5 h-3.5 w-3.5" />
                    <component
                        v-else
                        :is="categoryIcon(cat.key)"
                        class="mr-1.5 h-3.5 w-3.5"
                    />
                    {{ cat.label }}
                </Button>
            </div>

            <!-- Search -->
            <div class="relative w-full sm:w-64">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search activity..."
                    class="pl-9"
                />
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading" class="space-y-4">
            <div v-for="n in 5" :key="n" class="flex gap-4">
                <Skeleton class="h-10 w-10 shrink-0 rounded-full" />
                <div class="flex-1 space-y-2">
                    <Skeleton class="h-4 w-3/4" />
                    <Skeleton class="h-3 w-32" />
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="logs.length === 0"
            class="flex min-h-[200px] flex-col items-center justify-center gap-3 text-muted-foreground"
        >
            <Activity class="h-10 w-10" />
            <div class="text-center">
                <p class="font-medium">No activity found</p>
                <p class="mt-1 text-sm">
                    {{ searchQuery || activeCategory !== 'all'
                        ? 'Try adjusting your filters'
                        : 'Activity will appear here as events occur'
                    }}
                </p>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div v-else class="relative space-y-0">
            <!-- Timeline line -->
            <div class="absolute left-5 top-0 bottom-0 w-px bg-border" />

            <div
                v-for="log in logs"
                :key="log.id"
                class="relative flex gap-4 py-3"
            >
                <!-- Timeline dot with icon -->
                <div class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border bg-background">
                    <component
                        :is="categoryIcon(log.category)"
                        class="h-4 w-4 text-muted-foreground"
                    />
                </div>

                <!-- Content -->
                <div class="flex flex-1 flex-col gap-1 pt-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm">{{ log.message }}</p>
                        <Badge :variant="categoryVariant(log.category)" class="text-xs">
                            {{ log.category }}
                        </Badge>
                    </div>
                    <a
                        v-if="log.metadata?.invoice_url"
                        :href="log.metadata.invoice_url as string"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <ExternalLink class="h-3 w-3" />
                        View Invoice
                    </a>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(log.created_at) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Load More -->
        <div v-if="!loading && hasMorePages" class="flex justify-center">
            <Button
                variant="outline"
                @click="loadMore"
                :disabled="isLoadingMore"
                class="min-w-[140px]"
            >
                <Loader2 v-if="isLoadingMore" class="mr-2 h-4 w-4 animate-spin" />
                Load More
            </Button>
        </div>

        <!-- Total count -->
        <div
            v-if="!loading && meta && logs.length > 0"
            class="text-center text-xs text-muted-foreground"
        >
            Showing {{ logs.length }} of {{ meta.total }} activities
        </div>
    </div>
</template>
