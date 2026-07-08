<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import {
    toFriendlyNotification,
    toneContainerClasses,
    type ActivityLogItem,
} from '@/lib/notifications'
import type { AppPageProps } from '@/types'

const page = usePage<AppPageProps>()
const instructorId = computed(() => page.props.auth?.instructor_id ?? null)

const notifications = ref<ActivityLogItem[]>([])
const loading = ref(false)
const isOpen = ref(false)
let pollHandle: ReturnType<typeof setInterval> | null = null

const RECENT_WINDOW_MS = 7 * 24 * 60 * 60 * 1000

const recentCount = computed(() => {
    const cutoff = Date.now() - RECENT_WINDOW_MS
    return notifications.value.filter((n) => {
        const t = new Date(n.created_at).getTime()
        return !Number.isNaN(t) && t >= cutoff
    }).length
})

const badgeLabel = computed(() => {
    if (recentCount.value === 0) return null
    if (recentCount.value > 9) return '9+'
    return String(recentCount.value)
})

const friendly = computed(() =>
    notifications.value.slice(0, 10).map((item) => ({
        item,
        friendly: toFriendlyNotification(item),
    })),
)

const loadNotifications = async () => {
    if (!instructorId.value) return
    loading.value = true
    try {
        const response = await axios.get(
            `/instructors/${instructorId.value}/activity-logs`,
            { params: { category: 'notification', per_page: 10 } },
        )
        notifications.value = response.data.logs ?? []
    } catch {
        // Silent — the header shouldn't scream toasts at the user.
    } finally {
        loading.value = false
    }
}

const startPolling = () => {
    stopPolling()
    pollHandle = setInterval(() => {
        if (!isOpen.value) loadNotifications()
    }, 60_000)
}

const stopPolling = () => {
    if (pollHandle) {
        clearInterval(pollHandle)
        pollHandle = null
    }
}

watch(isOpen, (open) => {
    if (open) loadNotifications()
})

onMounted(() => {
    if (!instructorId.value) return
    loadNotifications()
    startPolling()
})

onUnmounted(() => {
    stopPolling()
})
</script>

<template>
    <div v-if="instructorId">
        <DropdownMenu v-model:open="isOpen">
            <DropdownMenuTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    class="group relative h-9 w-9 cursor-pointer"
                    aria-label="Notifications"
                >
                    <Bell class="size-5 opacity-80 group-hover:opacity-100" />
                    <span
                        v-if="badgeLabel"
                        class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-primary-foreground"
                    >
                        {{ badgeLabel }}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[380px] p-0">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Bell class="h-4 w-4" />
                        <span class="text-sm font-semibold">Notifications</span>
                    </div>
                    <span
                        v-if="recentCount > 0"
                        class="text-xs text-muted-foreground"
                    >
                        {{ recentCount }} new this week
                    </span>
                </div>

                <div class="max-h-[400px] overflow-y-auto">
                    <div v-if="loading && notifications.length === 0" class="space-y-3 p-4">
                        <div v-for="n in 4" :key="n" class="flex gap-3">
                            <Skeleton class="h-9 w-9 shrink-0 rounded-full" />
                            <div class="flex-1 space-y-2">
                                <Skeleton class="h-3.5 w-3/4" />
                                <Skeleton class="h-3 w-1/2" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="notifications.length === 0"
                        class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center"
                    >
                        <Bell class="h-8 w-8 text-muted-foreground" />
                        <p class="text-sm font-medium">You're all caught up</p>
                        <p class="text-xs text-muted-foreground">
                            New notifications will show up here.
                        </p>
                    </div>

                    <ul v-else class="divide-y">
                        <li
                            v-for="entry in friendly"
                            :key="entry.item.id"
                            class="flex items-start gap-3 px-4 py-3"
                        >
                            <div
                                :class="[
                                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-full',
                                    toneContainerClasses(entry.friendly.tone),
                                ]"
                            >
                                <component :is="entry.friendly.icon" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ entry.friendly.title }}
                                </p>
                                <p class="line-clamp-2 text-xs text-muted-foreground">
                                    {{ entry.friendly.summary }}
                                </p>
                                <p class="mt-1 text-[11px] text-muted-foreground/80">
                                    {{ entry.friendly.friendlyDate }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
