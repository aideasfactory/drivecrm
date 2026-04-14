<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { toast } from '@/components/ui/sonner'
import { Bell, Send, Loader2 } from 'lucide-vue-next'

interface UserWithToken {
    id: number
    name: string
    email: string
    role: string
}

interface Props {
    users: UserWithToken[]
}

const props = defineProps<Props>()

const selectedUserId = ref<number | ''>('')
const title = ref('')
const body = ref('')
const sending = ref(false)

const selectedUser = computed(() =>
    props.users.find((u) => u.id === selectedUserId.value),
)

const canSend = computed(
    () => selectedUserId.value && title.value.trim() && body.value.trim(),
)

const handleSubmit = () => {
    if (!canSend.value) return

    sending.value = true

    router.post(
        '/push-notifications',
        {
            user_id: selectedUserId.value,
            title: title.value.trim(),
            body: body.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `Notification queued for ${selectedUser.value?.name}`,
                )
                title.value = ''
                body.value = ''
                selectedUserId.value = ''
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0]
                toast.error(
                    typeof firstError === 'string'
                        ? firstError
                        : 'Failed to queue notification',
                )
            },
            onFinish: () => {
                sending.value = false
            },
        },
    )
}

const breadcrumbs = [{ title: 'Push Notifications' }]
</script>

<template>
    <Head title="Push Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <Bell class="h-8 w-8" />
                    Push Notifications
                </h2>
                <p class="text-muted-foreground">
                    Send a push notification to a user who has registered for
                    push notifications.
                </p>
            </div>

            <!-- Send Form -->
            <Card class="max-w-2xl">
                <CardContent class="pt-6">
                    <form
                        @submit.prevent="handleSubmit"
                        class="flex flex-col gap-5"
                    >
                        <!-- User Select -->
                        <div class="flex flex-col gap-2">
                            <Label for="user">Recipient</Label>
                            <select
                                id="user"
                                v-model="selectedUserId"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="" disabled>
                                    Select a user...
                                </option>
                                <option
                                    v-for="user in props.users"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                Only users who have accepted push notifications
                                are shown.
                            </p>
                        </div>

                        <!-- Title -->
                        <div class="flex flex-col gap-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                v-model="title"
                                placeholder="Notification title"
                                maxlength="255"
                            />
                        </div>

                        <!-- Body -->
                        <div class="flex flex-col gap-2">
                            <Label for="body">Message</Label>
                            <textarea
                                id="body"
                                v-model="body"
                                placeholder="Notification message..."
                                rows="4"
                                maxlength="1000"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ body.length }} / 1000 characters
                            </p>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center gap-3">
                            <Button
                                type="submit"
                                :disabled="!canSend || sending"
                                class="cursor-pointer min-w-[160px]"
                            >
                                <Loader2
                                    v-if="sending"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Send v-else class="mr-2 h-4 w-4" />
                                {{
                                    sending
                                        ? 'Queuing...'
                                        : 'Queue Notification'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Info -->
            <div
                v-if="props.users.length === 0"
                class="rounded-md border border-dashed p-8 text-center"
            >
                <Bell class="mx-auto h-12 w-12 text-muted-foreground/50" />
                <h3 class="mt-4 text-lg font-semibold">
                    No users with push tokens
                </h3>
                <p class="mt-2 text-muted-foreground">
                    No users have registered for push notifications yet. Users
                    need to accept push notifications from the mobile app first.
                </p>
            </div>

            <!-- Users with tokens summary -->
            <div v-else class="flex items-center gap-2">
                <Badge variant="secondary">
                    {{ props.users.length }}
                    {{
                        props.users.length === 1
                            ? 'user'
                            : 'users'
                    }}
                </Badge>
                <span class="text-sm text-muted-foreground">
                    registered for push notifications
                </span>
            </div>
        </div>
    </AppLayout>
</template>
