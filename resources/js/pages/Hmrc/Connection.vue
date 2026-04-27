<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { toast } from '@/components/ui/sonner';
import { Link2, Loader2, Plug, Power, ShieldCheck, Wand2 } from 'lucide-vue-next';

interface ConnectionStatus {
    connected: boolean;
    connected_at: string | null;
    expires_at: string | null;
    refresh_expires_at: string | null;
    scopes: string[];
    days_until_refresh_expiry: number | null;
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

const props = defineProps<{
    environment: string;
    connection: ConnectionStatus;
    helloWorldResponse: Record<string, unknown> | null;
}>();

const page = usePage<PageProps>();

watch(
    () => page.props.flash?.success,
    (value) => {
        if (value) {
            toast.success(value);
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (value) => {
        if (value) {
            toast.error(value);
        }
    },
    { immediate: true },
);

const testing = ref(false);
const disconnecting = ref(false);

const formatDate = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
};

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const expiryBadgeVariant = computed<BadgeVariant>(() => {
    const days = props.connection.days_until_refresh_expiry;
    if (days === null) return 'secondary';
    if (days <= 7) return 'destructive';
    if (days <= 30) return 'outline';
    return 'secondary';
});

const helloWorldJson = computed(() =>
    props.helloWorldResponse ? JSON.stringify(props.helloWorldResponse, null, 2) : '',
);

const handleTestHelloWorld = () => {
    testing.value = true;
    router.post(
        '/hmrc/test/hello-world',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testing.value = false;
            },
        },
    );
};

const handleDisconnect = () => {
    if (!window.confirm('Disconnect from HMRC? You will need to reconnect to file again.')) {
        return;
    }
    disconnecting.value = true;
    router.post(
        '/hmrc/disconnect',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                disconnecting.value = false;
            },
        },
    );
};

const breadcrumbs = [{ title: 'HMRC / Tax' }];
</script>

<template>
    <Head title="HMRC / Tax" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <ShieldCheck class="h-8 w-8" />
                    HMRC / Tax
                </h2>
                <p class="text-muted-foreground">
                    Connect your HMRC account to file Income Tax (and VAT, if applicable) directly from
                    {{ $page.props.name as string }}.
                </p>
                <div>
                    <Badge variant="outline" class="uppercase">{{ environment }}</Badge>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Link2 class="h-5 w-5" />
                        Connection status
                    </CardTitle>
                    <CardDescription v-if="connection.connected">
                        Your HMRC connection is active. We'll keep it refreshed in the background.
                    </CardDescription>
                    <CardDescription v-else>
                        Connect your HMRC account to begin filing.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="connection.connected" class="flex flex-col gap-4">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm text-muted-foreground">Connected since</dt>
                                <dd class="font-medium">{{ formatDate(connection.connected_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-muted-foreground">Access token expires</dt>
                                <dd class="font-medium">{{ formatDate(connection.expires_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-muted-foreground">Refresh token expires</dt>
                                <dd class="font-medium flex items-center gap-2">
                                    {{ formatDate(connection.refresh_expires_at) }}
                                    <Badge :variant="expiryBadgeVariant">
                                        <span v-if="connection.days_until_refresh_expiry !== null">
                                            {{ connection.days_until_refresh_expiry }} days
                                        </span>
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm text-muted-foreground">Scopes granted</dt>
                                <dd class="flex flex-wrap gap-1 mt-1">
                                    <Badge v-for="scope in connection.scopes" :key="scope" variant="secondary">
                                        {{ scope }}
                                    </Badge>
                                    <span v-if="!connection.scopes.length" class="text-sm text-muted-foreground">
                                        none
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-2">
                            <Button variant="destructive" :disabled="disconnecting" @click="handleDisconnect">
                                <Loader2 v-if="disconnecting" class="mr-2 h-4 w-4 animate-spin" />
                                <Power v-else class="mr-2 h-4 w-4" />
                                Disconnect
                            </Button>
                        </div>
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <p class="text-sm text-muted-foreground">
                            You are not currently connected to HMRC. Connecting opens a secure HMRC sign-in page in
                            your browser.
                        </p>
                        <div>
                            <Button as-child>
                                <a href="/hmrc/connect">
                                    <Plug class="mr-2 h-4 w-4" />
                                    Connect to HMRC ({{ environment }})
                                </a>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wand2 class="h-5 w-5" />
                        Diagnostic — Hello World
                    </CardTitle>
                    <CardDescription>
                        Calls HMRC's user-restricted Hello World endpoint to confirm the OAuth round-trip is working.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-col gap-4">
                        <div>
                            <Button :disabled="!connection.connected || testing" @click="handleTestHelloWorld">
                                <Loader2 v-if="testing" class="mr-2 h-4 w-4 animate-spin" />
                                Test Hello World
                            </Button>
                        </div>

                        <pre
                            v-if="helloWorldJson"
                            class="rounded-md border bg-muted p-3 text-xs overflow-auto"
                        >{{ helloWorldJson }}</pre>
                        <p v-else class="text-sm text-muted-foreground">
                            Run the test to see HMRC's response here.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
