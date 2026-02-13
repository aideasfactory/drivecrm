<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { MessageSquare, Send, Loader2 } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

interface MessageSender {
    id: number
    name: string
}

interface ChatMessage {
    id: number
    from: number
    to: number
    message: string
    created_at: string
    sender: MessageSender
}

interface ConversationContext {
    instructor_user_id: number
    instructor_name: string
    student_user_id: number
    student_name: string
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

// State
const messages = ref<ChatMessage[]>([])
const meta = ref<PaginationMeta | null>(null)
const context = ref<ConversationContext | null>(null)
const loading = ref(true)
const isLoadingMore = ref(false)
const newMessage = ref('')
const isSending = ref(false)
const hasError = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)

// Computed
const hasMorePages = computed(() => {
    if (!meta.value) return false
    return meta.value.current_page < meta.value.last_page
})

const isStudentMessage = (msg: ChatMessage): boolean => {
    if (!context.value) return false
    return msg.from === context.value.student_user_id
}

// Utilities
const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const formatMessageTime = (dateString: string): string => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

    const time = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })

    if (diffDays === 0) return `Today, ${time}`
    if (diffDays === 1) return `Yesterday, ${time}`
    if (diffDays < 7) return `${date.toLocaleDateString('en-GB', { weekday: 'long' })}, ${time}`

    return `${date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}, ${time}`
}

// Scroll to bottom of messages
const scrollToBottom = async () => {
    await nextTick()
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
}

// Load messages
const loadMessages = async (page = 1, prepend = false) => {
    if (page === 1) {
        loading.value = true
    } else {
        isLoadingMore.value = true
    }

    try {
        const response = await axios.get(`/students/${props.studentId}/messages`, {
            params: { page },
        })

        const newMessages: ChatMessage[] = response.data.messages || []
        // API returns newest first — reverse for chronological display
        const chronological = [...newMessages].reverse()

        if (prepend) {
            // Save scroll position before prepending
            const container = messagesContainer.value
            const previousScrollHeight = container?.scrollHeight || 0

            messages.value = [...chronological, ...messages.value]

            // Restore scroll position after prepending older messages
            await nextTick()
            if (container) {
                container.scrollTop = container.scrollHeight - previousScrollHeight
            }
        } else {
            messages.value = chronological
        }

        meta.value = response.data.meta
        context.value = response.data.context
        hasError.value = false

        if (!prepend) {
            scrollToBottom()
        }
    } catch (error: any) {
        if (error.response?.status === 422) {
            hasError.value = true
        } else {
            toast.error('Failed to load messages')
        }
    } finally {
        loading.value = false
        isLoadingMore.value = false
    }
}

const loadMore = () => {
    if (!meta.value || !hasMorePages.value) return
    loadMessages(meta.value.current_page + 1, true)
}

// Send message
const handleSend = async () => {
    if (!newMessage.value.trim() || isSending.value) return

    isSending.value = true
    try {
        const response = await axios.post(`/students/${props.studentId}/messages`, {
            message: newMessage.value.trim(),
        })

        messages.value.push(response.data.message)
        if (meta.value) {
            meta.value.total++
        }
        newMessage.value = ''
        toast.success('Message sent')
        scrollToBottom()
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to send message'
        toast.error(message)
    } finally {
        isSending.value = false
    }
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault()
        handleSend()
    }
}

onMounted(() => {
    loadMessages()
})
</script>

<template>
    <div class="flex flex-col" style="height: 600px">
        <!-- Loading Skeleton -->
        <template v-if="loading">
            <!-- Header skeleton -->
            <div class="mb-4 flex items-center gap-3 border-b pb-4">
                <Skeleton class="h-10 w-10 rounded-full" />
                <div class="flex flex-col gap-1">
                    <Skeleton class="h-5 w-32" />
                    <Skeleton class="h-3 w-20" />
                </div>
            </div>
            <!-- Messages skeleton -->
            <div class="flex flex-1 flex-col gap-4 overflow-hidden">
                <div class="flex items-start gap-3">
                    <Skeleton class="h-8 w-8 shrink-0 rounded-full" />
                    <div class="flex flex-col gap-1">
                        <Skeleton class="h-3 w-24" />
                        <Skeleton class="h-16 w-64 rounded-2xl" />
                    </div>
                </div>
                <div class="flex items-start justify-end gap-3">
                    <div class="flex flex-col items-end gap-1">
                        <Skeleton class="h-3 w-24" />
                        <Skeleton class="h-12 w-48 rounded-2xl" />
                    </div>
                    <Skeleton class="h-8 w-8 shrink-0 rounded-full" />
                </div>
                <div class="flex items-start gap-3">
                    <Skeleton class="h-8 w-8 shrink-0 rounded-full" />
                    <div class="flex flex-col gap-1">
                        <Skeleton class="h-3 w-24" />
                        <Skeleton class="h-20 w-72 rounded-2xl" />
                    </div>
                </div>
            </div>
            <!-- Input skeleton -->
            <div class="mt-4 flex gap-3 border-t pt-4">
                <Skeleton class="h-10 flex-1" />
                <Skeleton class="h-10 w-20" />
            </div>
        </template>

        <!-- Error: No user account or no instructor -->
        <template v-else-if="hasError">
            <div class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground">
                <MessageSquare class="h-10 w-10" />
                <div class="text-center">
                    <p class="font-medium">Messages unavailable</p>
                    <p class="mt-1 text-sm">
                        This student needs a user account and an assigned instructor to use messaging.
                    </p>
                </div>
            </div>
        </template>

        <!-- Chat UI -->
        <template v-else-if="context">
            <!-- Messages Area -->
            <div
                ref="messagesContainer"
                class="flex flex-1 flex-col gap-4 overflow-y-auto pr-2"
            >
                <!-- Load More -->
                <div v-if="hasMorePages" class="flex justify-center py-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="loadMore"
                        :disabled="isLoadingMore"
                        class="min-w-[140px]"
                    >
                        <Loader2 v-if="isLoadingMore" class="mr-2 h-4 w-4 animate-spin" />
                        Load older messages
                    </Button>
                </div>

                <!-- Empty State -->
                <div
                    v-if="messages.length === 0"
                    class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <MessageSquare class="h-10 w-10" />
                    <div class="text-center">
                        <p class="font-medium">No messages yet</p>
                        <p class="mt-1 text-sm">Send the first message to start the conversation</p>
                    </div>
                </div>

                <!-- Message Bubbles -->
                <template v-for="msg in messages" :key="msg.id">
                    <!-- Student Message (right-aligned, red/destructive bg) -->
                    <div v-if="isStudentMessage(msg)" class="flex items-start justify-end gap-3">
                        <div class="flex flex-1 flex-col items-end">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">
                                    {{ formatMessageTime(msg.created_at) }}
                                </span>
                                <span class="text-sm font-semibold">
                                    {{ msg.sender.name }}
                                </span>
                            </div>
                            <div class="max-w-lg rounded-2xl rounded-tr-none bg-destructive p-4">
                                <p class="text-sm text-destructive-foreground">{{ msg.message }}</p>
                            </div>
                        </div>
                        <Avatar class="h-8 w-8 shrink-0">
                            <AvatarFallback class="text-xs">
                                {{ getInitials(msg.sender.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </div>

                    <!-- Instructor Message (left-aligned, muted bg) -->
                    <div v-else class="flex items-start gap-3">
                        <Avatar class="h-8 w-8 shrink-0">
                            <AvatarFallback class="text-xs">
                                {{ getInitials(msg.sender.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex flex-1 flex-col">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-sm font-semibold">
                                    {{ msg.sender.name }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatMessageTime(msg.created_at) }}
                                </span>
                            </div>
                            <div class="max-w-lg rounded-2xl rounded-tl-none bg-muted p-4">
                                <p class="text-sm">{{ msg.message }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Message Input -->
            <div class="mt-4 flex items-center gap-3 border-t pt-4">
                <Input
                    v-model="newMessage"
                    placeholder="Type your message..."
                    :disabled="isSending"
                    class="flex-1"
                    @keydown="handleKeydown"
                />
                <Button
                    @click="handleSend"
                    :disabled="isSending || !newMessage.trim()"
                    class="min-w-[100px]"
                >
                    <Loader2 v-if="isSending" class="mr-2 h-4 w-4 animate-spin" />
                    <Send v-else class="mr-2 h-4 w-4" />
                    Send
                </Button>
            </div>
        </template>
    </div>
</template>
