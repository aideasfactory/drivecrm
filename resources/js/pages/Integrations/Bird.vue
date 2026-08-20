<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import {
    Bird,
    Download,
    Loader2,
    MessageSquare,
    RefreshCw,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/sonner';
import { index as integrationsIndex, bird } from '@/routes/integrations';

interface Conversation {
    id: string;
    contact_name: string;
    contact_email: string;
    contact_phone: string;
    last_message: string;
    last_contact_message: string;
    status: string;
    channel_id: string;
    messages_count: number;
    last_contact_message_at: string;
    updated_at: string;
}

interface Message {
    id: string;
    sender_name: string;
    sender_type: string;
    text: string;
    sent_at: string;
}

interface ExportMessage extends Message {
    conversation_id: string;
    contact_name: string;
    contact_email: string;
    contact_phone: string;
}

interface Props {
    configured: boolean;
    channelFiltered: boolean;
}

defineProps<Props>();

const conversations = ref<Conversation[]>([]);
const lastSyncedAt = ref<string | null>(null);
const loading = ref(true);
const syncing = ref(false);
const exporting = ref(false);
const apiError = ref<string | null>(null);
const filter = ref('');

const sheetOpen = ref(false);
const activeConversation = ref<Conversation | null>(null);
const messages = ref<Message[]>([]);
const messagesLoading = ref(false);

const filteredConversations = computed(() => {
    const term = filter.value.trim().toLowerCase();

    if (term === '') {
        return conversations.value;
    }

    return conversations.value.filter(
        (conversation) =>
            conversation.id.toLowerCase().includes(term) ||
            conversation.contact_name.toLowerCase().includes(term) ||
            conversation.contact_email.toLowerCase().includes(term) ||
            conversation.contact_phone.toLowerCase().includes(term),
    );
});

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

const isCustomer = (message: Message): boolean =>
    message.sender_type === 'contact';

// CSV generation happens entirely client-side — the server only hands the
// mirrored rows back as JSON.
const downloadCsv = (rows: string[][], filename: string): void => {
    const escapeCell = (value: string): string =>
        `"${(value ?? '').replace(/"/g, '""')}"`;

    const csv = rows
        .map((row) => row.map(escapeCell).join(','))
        .join('\r\n');

    // BOM so Excel opens it as UTF-8
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
};

const today = (): string => new Date().toISOString().slice(0, 10);

const downloadAllMessagesCsv = async (): Promise<void> => {
    exporting.value = true;

    try {
        const response = await axios.get(bird.messages.all.url());
        const rows: ExportMessage[] = response.data.messages;

        downloadCsv(
            [
                [
                    'Name',
                    'Email',
                    'Phone',
                    'Sender',
                    'Message',
                    'Conversation ID',
                    'Sent At',
                ],
                ...rows.map((message) => [
                    message.contact_name,
                    message.contact_email,
                    message.contact_phone,
                    message.sender_name ||
                        (message.sender_type === 'contact'
                            ? 'Customer'
                            : 'AI Employee'),
                    message.text,
                    message.conversation_id,
                    message.sent_at,
                ]),
            ],
            `bird-ai-messages-${today()}.csv`,
        );
        toast.success(`Exported ${rows.length} messages`);
    } catch {
        toast.error('Failed to export messages');
    } finally {
        exporting.value = false;
    }
};

const downloadMessagesCsv = (): void => {
    const conversation = activeConversation.value;

    if (!conversation) {
        return;
    }

    downloadCsv(
        [
            [
                'Name',
                'Email',
                'Phone',
                'Sender',
                'Message',
                'Conversation ID',
                'Sent At',
            ],
            ...messages.value.map((message) => [
                conversation.contact_name,
                conversation.contact_email,
                conversation.contact_phone,
                message.sender_name ||
                    (isCustomer(message) ? 'Customer' : 'AI Employee'),
                message.text,
                conversation.id,
                message.sent_at,
            ]),
        ],
        `bird-conversation-${conversation.id}-${today()}.csv`,
    );
};

const loadConversations = async (): Promise<void> => {
    loading.value = true;

    try {
        const response = await axios.get(bird.conversations.url());
        conversations.value = response.data.conversations;
        lastSyncedAt.value = response.data.last_synced_at;
    } catch {
        apiError.value = 'Failed to load conversations.';
    } finally {
        loading.value = false;
    }
};

const syncNow = async (): Promise<void> => {
    syncing.value = true;
    apiError.value = null;

    try {
        const response = await axios.post(bird.sync.url());
        toast.success(
            `Synced ${response.data.conversations} conversations (${response.data.messages} messages)`,
        );
        await loadConversations();
    } catch (error: any) {
        apiError.value =
            error.response?.data?.message ?? 'Failed to sync from Bird.';
        toast.error('Sync failed');
    } finally {
        syncing.value = false;
    }
};

const openConversation = async (conversation: Conversation): Promise<void> => {
    activeConversation.value = conversation;
    sheetOpen.value = true;
    messagesLoading.value = true;
    messages.value = [];

    try {
        const response = await axios.get(bird.messages.url(conversation.id));
        messages.value = response.data.messages;
    } catch {
        toast.error('Failed to load messages');
        sheetOpen.value = false;
    } finally {
        messagesLoading.value = false;
    }
};

onMounted(() => {
    loadConversations();
});
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Integrations', href: integrationsIndex.url() },
            { title: 'Bird' },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                    >
                        <Bird class="h-6 w-6" />
                        Bird AI Conversations
                    </h1>
                    <p class="text-muted-foreground">
                        AI Employee chat history mirrored from Bird. Last
                        synced: {{ formatDate(lastSyncedAt) }} (auto-syncs
                        nightly).
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        class="min-w-[130px]"
                        :disabled="syncing"
                        @click="syncNow"
                    >
                        <Loader2
                            v-if="syncing"
                            class="h-4 w-4 animate-spin"
                        />
                        <RefreshCw v-else class="h-4 w-4" />
                        Sync now
                    </Button>
                    <Button
                        v-if="conversations.length > 0"
                        variant="outline"
                        class="min-w-[190px]"
                        :disabled="exporting"
                        @click="downloadAllMessagesCsv"
                    >
                        <Loader2
                            v-if="exporting"
                            class="h-4 w-4 animate-spin"
                        />
                        <Download v-else class="h-4 w-4" />
                        Download all messages
                    </Button>
                </div>
            </div>

            <Alert v-if="!configured" variant="destructive">
                <AlertTitle>Bird is not configured</AlertTitle>
                <AlertDescription>
                    Set BIRD_CONVERSATIONS_API_KEY (or BIRD_API_KEY) and
                    BIRD_WORKSPACE_ID in the environment to enable syncing.
                </AlertDescription>
            </Alert>

            <Alert v-else-if="apiError" variant="destructive">
                <AlertTitle>Bird API error</AlertTitle>
                <AlertDescription class="space-y-2">
                    <p>{{ apiError }}</p>
                    <p>
                        If this is a 403 or 404, the access key is missing
                        Conversations read permissions — in Bird go to Settings
                        → Access keys and create a dedicated key with the AI
                        Employees Viewer and Inbox Agent roles, then set it as
                        BIRD_CONVERSATIONS_API_KEY.
                    </p>
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <CardTitle class="flex items-center gap-2">
                            <MessageSquare class="h-5 w-5" />
                            Conversations
                            <Badge v-if="!loading" variant="secondary">
                                {{ filteredConversations.length }}
                            </Badge>
                        </CardTitle>
                        <Input
                            v-model="filter"
                            placeholder="Filter by conversation ID, name, email or phone…"
                            class="w-72"
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="loading" class="space-y-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-3/4" />
                    </div>

                    <div
                        v-else-if="filteredConversations.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        <p v-if="conversations.length === 0">
                            No conversations mirrored yet — click Sync now to
                            pull them from Bird.
                        </p>
                        <p v-else>No conversations match the filter.</p>
                    </div>

                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email / Phone</TableHead>
                                <TableHead>Last customer message</TableHead>
                                <TableHead class="text-right">
                                    Messages
                                </TableHead>
                                <TableHead>Conversation ID</TableHead>
                                <TableHead>Received at</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="conversation in filteredConversations"
                                :key="conversation.id"
                                class="cursor-pointer"
                                @click="openConversation(conversation)"
                            >
                                <TableCell class="font-medium">
                                    {{ conversation.contact_name || '—' }}
                                </TableCell>
                                <TableCell>
                                    {{
                                        conversation.contact_email ||
                                        conversation.contact_phone ||
                                        '—'
                                    }}
                                </TableCell>
                                <TableCell
                                    class="max-w-md truncate text-muted-foreground"
                                >
                                    {{ conversation.last_contact_message || '—' }}
                                </TableCell>
                                <TableCell class="text-right">
                                    {{ conversation.messages_count }}
                                </TableCell>
                                <TableCell class="font-mono text-xs">
                                    {{ conversation.id }}
                                </TableCell>
                                <TableCell class="whitespace-nowrap">
                                    {{
                                        formatDate(
                                            conversation.last_contact_message_at ||
                                                conversation.updated_at,
                                        )
                                    }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        {{ activeConversation?.contact_name || 'Conversation' }}
                    </SheetTitle>
                    <SheetDescription>
                        <span
                            v-if="
                                activeConversation?.contact_email ||
                                activeConversation?.contact_phone
                            "
                        >
                            {{
                                activeConversation.contact_email ||
                                activeConversation.contact_phone
                            }}
                            ·
                        </span>
                        <span class="font-mono text-xs">
                            {{ activeConversation?.id }}
                        </span>
                    </SheetDescription>
                </SheetHeader>

                <div class="space-y-4 px-6 py-4">
                    <Button
                        v-if="!messagesLoading && messages.length > 0"
                        variant="outline"
                        size="sm"
                        @click="downloadMessagesCsv"
                    >
                        <Download class="h-4 w-4" />
                        Download conversation CSV
                    </Button>

                    <div v-if="messagesLoading" class="space-y-3">
                        <Skeleton class="h-16 w-full" />
                        <Skeleton class="h-16 w-3/4" />
                        <Skeleton class="h-16 w-full" />
                    </div>

                    <p
                        v-else-if="messages.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No messages in this conversation.
                    </p>

                    <div
                        v-for="message in messages"
                        v-else
                        :key="message.id"
                        class="flex flex-col gap-1 rounded-lg border p-3"
                        :class="isCustomer(message) ? 'bg-muted/50' : ''"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <Badge
                                :variant="
                                    isCustomer(message)
                                        ? 'secondary'
                                        : 'default'
                                "
                            >
                                {{
                                    message.sender_name ||
                                    (isCustomer(message)
                                        ? 'Customer'
                                        : 'AI Employee')
                                }}
                            </Badge>
                            <span class="text-xs text-muted-foreground">
                                {{ formatDate(message.sent_at) }}
                            </span>
                        </div>
                        <p class="whitespace-pre-wrap text-sm">
                            {{ message.text }}
                        </p>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
