<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { toast } from '@/components/ui/sonner'
import { index as supportMessagesIndex, store as supportMessagesStore } from '@/routes/support-messages'
import { MessageSquare, Send, Loader2, Inbox } from 'lucide-vue-next'

interface ConversationUser {
    id: number
    name: string
    role: string | null
}

interface ConversationEntry {
    user: ConversationUser
    latest_message: {
        message: string
        is_own: boolean
        created_at: string | null
    }
}

interface ThreadMessage {
    id: number
    message: string
    is_own: boolean
    sender_name: string | null
    created_at: string | null
}

interface SelectedUser {
    id: number
    name: string
    email: string
    role: string | null
}

interface Props {
    conversations: ConversationEntry[]
    selectedUser: SelectedUser | null
    thread: ThreadMessage[] | null
}

const props = defineProps<Props>()

const replyText = ref('')
const sending = ref(false)
const threadScrollRef = ref<HTMLDivElement | null>(null)

const scrollThreadToBottom = () => {
    nextTick(() => {
        if (threadScrollRef.value) {
            threadScrollRef.value.scrollTop = threadScrollRef.value.scrollHeight
        }
    })
}

watch(() => props.thread, () => scrollThreadToBottom(), { immediate: true })

const selectConversation = (userId: number) => {
    if (props.selectedUser?.id === userId) return
    router.get(
        supportMessagesIndex().url,
        { user: userId },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedUser', 'thread'],
            replace: true,
        },
    )
}

const canSend = computed(() => replyText.value.trim().length > 0)

const handleSend = () => {
    if (!canSend.value || !props.selectedUser) return
    sending.value = true
    router.post(
        supportMessagesStore(props.selectedUser.id).url,
        { message: replyText.value.trim() },
        {
            preserveScroll: true,
            only: ['conversations', 'selectedUser', 'thread', 'flash', 'errors'],
            onSuccess: () => {
                replyText.value = ''
                toast.success('Reply sent')
                scrollThreadToBottom()
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0]
                toast.error(
                    typeof firstError === 'string'
                        ? firstError
                        : 'Failed to send reply',
                )
            },
            onFinish: () => {
                sending.value = false
            },
        },
    )
}

const formatTime = (iso: string | null): string => {
    if (!iso) return ''
    const d = new Date(iso)
    const now = new Date()
    const sameDay =
        d.getFullYear() === now.getFullYear() &&
        d.getMonth() === now.getMonth() &&
        d.getDate() === now.getDate()
    if (sameDay) {
        return d.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        })
    }
    return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    })
}

const formatFull = (iso: string | null): string => {
    if (!iso) return ''
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    })
}

const truncate = (text: string, max = 60): string =>
    text.length > max ? text.slice(0, max - 1) + '…' : text

const breadcrumbs = [{ title: 'Support Messages' }]
</script>

<template>
    <Head title="Support Messages" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="flex items-center gap-3 text-3xl font-bold">
                    <MessageSquare class="h-8 w-8" />
                    Support Messages
                </h2>
                <p class="text-muted-foreground">
                    Chat with students and instructors who have messaged support
                    from the app.
                </p>
            </div>

            <div
                class="grid min-h-[70vh] grid-cols-1 gap-4 md:grid-cols-[320px_1fr]"
            >
                <!-- Conversations list -->
                <aside
                    class="flex min-h-0 flex-col rounded-md border bg-card"
                >
                    <div
                        class="flex items-center justify-between border-b px-4 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <Inbox class="h-4 w-4" />
                            <span class="text-sm font-semibold">Inbox</span>
                        </div>
                        <Badge variant="secondary">
                            {{ props.conversations.length }}
                        </Badge>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div
                            v-if="props.conversations.length === 0"
                            class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-muted-foreground"
                        >
                            <Inbox
                                class="h-8 w-8 text-muted-foreground/40"
                            />
                            No support messages yet.
                        </div>
                        <ul v-else class="divide-y">
                            <li
                                v-for="c in props.conversations"
                                :key="c.user.id"
                            >
                                <button
                                    type="button"
                                    class="flex w-full flex-col gap-1 px-4 py-3 text-left transition-colors hover:bg-muted"
                                    :class="{
                                        'bg-muted':
                                            props.selectedUser?.id ===
                                            c.user.id,
                                    }"
                                    @click="selectConversation(c.user.id)"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ c.user.name }}
                                        </span>
                                        <span
                                            class="shrink-0 text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatTime(
                                                    c.latest_message.created_at,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        <Badge
                                            v-if="c.user.role"
                                            variant="outline"
                                            class="h-4 px-1.5 text-[10px] capitalize"
                                        >
                                            {{ c.user.role }}
                                        </Badge>
                                        <span class="truncate">
                                            <span
                                                v-if="c.latest_message.is_own"
                                                class="text-muted-foreground"
                                            >
                                                You:
                                            </span>
                                            {{
                                                truncate(
                                                    c.latest_message.message,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </div>
                </aside>

                <!-- Thread + composer -->
                <section
                    class="flex min-h-0 flex-col rounded-md border bg-card"
                >
                    <template v-if="props.selectedUser">
                        <header class="flex items-center gap-3 border-b px-4 py-3">
                            <div class="flex flex-col">
                                <span class="text-sm font-semibold">
                                    {{ props.selectedUser.name }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ props.selectedUser.email }}
                                </span>
                            </div>
                            <Badge
                                v-if="props.selectedUser.role"
                                variant="outline"
                                class="ml-auto capitalize"
                            >
                                {{ props.selectedUser.role }}
                            </Badge>
                        </header>

                        <div
                            ref="threadScrollRef"
                            class="flex-1 space-y-3 overflow-y-auto bg-muted/30 p-4"
                        >
                            <div
                                v-if="!props.thread || props.thread.length === 0"
                                class="flex h-full items-center justify-center text-sm text-muted-foreground"
                            >
                                No messages in this thread yet.
                            </div>
                            <template v-else>
                                <div
                                    v-for="msg in props.thread"
                                    :key="msg.id"
                                    class="flex"
                                    :class="
                                        msg.is_own
                                            ? 'justify-end'
                                            : 'justify-start'
                                    "
                                >
                                    <div
                                        class="max-w-[75%] rounded-lg px-3 py-2 text-sm shadow-xs"
                                        :class="
                                            msg.is_own
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-background border'
                                        "
                                    >
                                        <p class="whitespace-pre-wrap break-words">
                                            {{ msg.message }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] opacity-70"
                                            :title="formatFull(msg.created_at)"
                                        >
                                            {{ formatTime(msg.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <form
                            class="flex items-end gap-2 border-t p-3"
                            @submit.prevent="handleSend"
                        >
                            <textarea
                                v-model="replyText"
                                rows="2"
                                maxlength="5000"
                                placeholder="Type a reply..."
                                class="flex min-h-[44px] w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                @keydown.enter.exact.prevent="handleSend"
                            />
                            <Button
                                type="submit"
                                :disabled="!canSend || sending"
                                class="cursor-pointer"
                            >
                                <Loader2
                                    v-if="sending"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Send v-else class="mr-2 h-4 w-4" />
                                Send
                            </Button>
                        </form>
                    </template>

                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center gap-2 p-12 text-center text-sm text-muted-foreground"
                    >
                        <MessageSquare
                            class="h-10 w-10 text-muted-foreground/40"
                        />
                        <p>Select a conversation from the inbox to view messages.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
