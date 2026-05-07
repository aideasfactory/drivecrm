<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { toast } from '@/components/ui/sonner';
import {
    AlertCircle,
    Briefcase,
    CalendarClock,
    CheckCircle2,
    Fingerprint,
    Info,
    Landmark,
    Link2,
    Loader2,
    Plug,
    Power,
    Save,
    ShieldCheck,
    Wand2,
    Wallet,
} from 'lucide-vue-next';
import { useHmrcAction } from '@/composables/useHmrcAction';
import axios from 'axios';

interface ConnectionStatus {
    connected: boolean;
    connected_at: string | null;
    expires_at: string | null;
    refresh_expires_at: string | null;
    scopes: string[];
    days_until_refresh_expiry: number | null;
}

interface TaxProfile {
    completed_at: string | null;
    business_type: string | null;
    vat_registered: boolean;
    vrn: string | null;
    utr: string | null;
    nino: string | null;
    companies_house_number: string | null;
}

interface ItsaThreshold {
    date: string;
    income: number;
    label: string;
}

interface Applicability {
    profile_complete: boolean;
    business_type: string | null;
    vat: { applies: boolean; vrn: string | null };
    itsa: { applies: boolean; status: string; thresholds: ItsaThreshold[] };
    corporation_tax: { applies: false; reason: string };
    summary: string;
}

interface BusinessTypeOption {
    value: string;
    label: string;
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    errors?: Record<string, string>;
}

const props = defineProps<{
    environment: string;
    connection: ConnectionStatus;
    helloWorldResponse: Record<string, unknown> | null;
    taxProfile: TaxProfile | null;
    applicability: Applicability | null;
    businessTypes: BusinessTypeOption[];
    showHeader?: boolean;
}>();

const showHeader = computed(() => props.showHeader !== false);

const page = usePage<PageProps>();

watch(
    () => page.props.flash?.success,
    (value) => {
        if (value) toast.success(value);
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (value) => {
        if (value) toast.error(value);
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

// Tax profile editing
const profileSheetOpen = ref(false);
const profileSubmitting = ref(false);
const profileErrors = ref<Record<string, string>>({});

const initialProfile = (): {
    business_type: string;
    vat_registered: boolean;
    vrn: string;
    utr: string;
    nino: string;
    companies_house_number: string;
} => ({
    business_type: props.taxProfile?.business_type ?? '',
    vat_registered: props.taxProfile?.vat_registered ?? false,
    vrn: props.taxProfile?.vrn ?? '',
    utr: props.taxProfile?.utr ?? '',
    nino: props.taxProfile?.nino ?? '',
    companies_house_number: props.taxProfile?.companies_house_number ?? '',
});

const profileForm = ref(initialProfile());

const openProfileSheet = () => {
    profileForm.value = initialProfile();
    profileErrors.value = {};
    profileSheetOpen.value = true;
};

const closeProfileSheet = () => {
    profileSheetOpen.value = false;
};

const isSoleTraderOrPartnership = computed(
    () =>
        profileForm.value.business_type === 'sole_trader' ||
        profileForm.value.business_type === 'partnership',
);

const isLimitedCompany = computed(() => profileForm.value.business_type === 'limited_company');

const handleProfileSubmit = () => {
    profileSubmitting.value = true;
    profileErrors.value = {};
    router.post('/hmrc/tax-profile', { ...profileForm.value }, {
        preserveScroll: true,
        onSuccess: () => {
            profileSheetOpen.value = false;
        },
        onError: (errors) => {
            profileErrors.value = errors as Record<string, string>;
        },
        onFinish: () => {
            profileSubmitting.value = false;
        },
    });
};

const businessTypeLabel = (value: string | null): string => {
    if (!value) return '—';
    return props.businessTypes.find((opt) => opt.value === value)?.label ?? value;
};

const maskIdentifier = (value: string | null): string => {
    if (!value) return '—';
    if (value.length <= 4) return '••••';
    return '••••' + value.slice(-3);
};

// Fraud-prevention header validation
interface FraudHeaderIssue {
    code?: string;
    message?: string;
    [key: string]: unknown;
}

interface FraudHeaderResult {
    headers_sent: Record<string, string>;
    errors: FraudHeaderIssue[];
    warnings: FraudHeaderIssue[];
}

const fraudResult = ref<FraudHeaderResult | null>(null);
const fraudErrorMessage = ref<string | null>(null);
const fraudAction = useHmrcAction();

const handleValidateFraudHeaders = async () => {
    fraudErrorMessage.value = null;
    const result = await fraudAction.run(async () => {
        const response = await axios.post<FraudHeaderResult>('/hmrc/test/fraud-headers');
        return response.data;
    });
    if (result) {
        fraudResult.value = result;
        if (result.errors.length === 0) {
            toast.success(
                result.warnings.length === 0
                    ? 'Fraud headers passed validation with no issues.'
                    : `Fraud headers passed (with ${result.warnings.length} warning${result.warnings.length === 1 ? '' : 's'}).`,
            );
        } else {
            toast.error(`HMRC reported ${result.errors.length} fraud-header error${result.errors.length === 1 ? '' : 's'}.`);
        }
    } else {
        fraudErrorMessage.value = fraudAction.error.value;
    }
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="showHeader" class="flex flex-col gap-2">
            <h2 class="text-3xl font-bold flex items-center gap-3">
                <ShieldCheck class="h-8 w-8" />
                HMRC / Tax
            </h2>
            <p class="text-muted-foreground">
                Connect your HMRC account to file Income Tax (and VAT, if applicable) directly from
                {{ ($page.props as unknown as { name?: string }).name }}.
            </p>
            <div>
                <Badge variant="outline" class="uppercase">{{ environment }}</Badge>
            </div>
        </div>

        <!-- Top row: Tax profile + Connection status side by side -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Tax profile -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Briefcase class="h-5 w-5" />
                        Your tax profile
                    </CardTitle>
                    <CardDescription v-if="taxProfile?.completed_at">
                        We use this to know which HMRC services apply to you.
                    </CardDescription>
                    <CardDescription v-else>
                        Tell us about your business so we can show the right HMRC services.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="taxProfile?.completed_at" class="flex flex-col gap-4">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm text-muted-foreground">Business type</dt>
                                <dd class="font-medium">{{ businessTypeLabel(taxProfile.business_type) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-muted-foreground">VAT-registered</dt>
                                <dd class="font-medium">{{ taxProfile.vat_registered ? 'Yes' : 'No' }}</dd>
                            </div>
                            <div v-if="taxProfile.vrn">
                                <dt class="text-sm text-muted-foreground">VRN</dt>
                                <dd class="font-medium">{{ taxProfile.vrn }}</dd>
                            </div>
                            <div v-if="taxProfile.utr">
                                <dt class="text-sm text-muted-foreground">UTR</dt>
                                <dd class="font-medium">{{ maskIdentifier(taxProfile.utr) }}</dd>
                            </div>
                            <div v-if="taxProfile.nino">
                                <dt class="text-sm text-muted-foreground">NINO</dt>
                                <dd class="font-medium">{{ maskIdentifier(taxProfile.nino) }}</dd>
                            </div>
                            <div v-if="taxProfile.companies_house_number">
                                <dt class="text-sm text-muted-foreground">Companies House no.</dt>
                                <dd class="font-medium">{{ taxProfile.companies_house_number }}</dd>
                            </div>
                        </dl>
                        <div>
                            <Button variant="outline" @click="openProfileSheet">
                                <Save class="mr-2 h-4 w-4" />
                                Edit tax profile
                            </Button>
                        </div>
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <p class="text-sm text-muted-foreground">
                            You haven't set up your tax profile yet. We need this before we can show which HMRC
                            services apply.
                        </p>
                        <div>
                            <Button @click="openProfileSheet">
                                <Briefcase class="mr-2 h-4 w-4" />
                                Set up tax profile
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Connection status -->
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
        </div>

        <!-- Available HMRC services -->
        <Card v-if="applicability?.profile_complete">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Landmark class="h-5 w-5" />
                    Available HMRC services
                </CardTitle>
                <CardDescription>{{ applicability.summary }}</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- ITSA -->
                    <div
                        v-if="applicability.itsa.applies"
                        class="rounded-md border bg-muted/30 p-4 flex flex-col gap-2"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <CalendarClock class="h-4 w-4" />
                            MTD Income Tax (ITSA)
                        </div>
                        <p
                            v-if="applicability.itsa.status === 'tbc_by_hmrc'"
                            class="text-sm text-muted-foreground"
                        >
                            ITSA timeline for partnerships is yet to be confirmed by HMRC.
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Submit quarterly updates and view obligations from the ITSA page.
                        </p>
                        <ul
                            v-if="applicability.itsa.status !== 'tbc_by_hmrc'"
                            class="text-xs text-muted-foreground space-y-1"
                        >
                            <li v-for="t in applicability.itsa.thresholds" :key="t.date">
                                {{ new Date(t.date).toLocaleDateString() }} — {{ t.label }}
                            </li>
                        </ul>
                        <Button as-child size="sm" variant="outline" class="w-fit">
                            <a href="/hmrc/itsa">Open ITSA submissions</a>
                        </Button>
                    </div>

                    <!-- VAT -->
                    <div
                        v-if="applicability.vat.applies"
                        class="rounded-md border bg-muted/30 p-4 flex flex-col gap-2"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <Wallet class="h-4 w-4" />
                            MTD VAT
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Submit your quarterly 9-box VAT return for VRN
                            <span class="font-mono">{{ applicability.vat.vrn }}</span>.
                        </p>
                        <Button as-child size="sm" variant="outline" class="w-fit">
                            <a href="/hmrc/vat">Open VAT submissions</a>
                        </Button>
                    </div>

                    <!-- None applicable -->
                    <div
                        v-if="!applicability.itsa.applies && !applicability.vat.applies"
                        class="rounded-md border bg-muted/30 p-4 flex flex-col gap-2 sm:col-span-2"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <Info class="h-4 w-4" />
                            No MTD services apply
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ applicability.corporation_tax.reason }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Diagnostic -->
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
                            <CheckCircle2 v-else class="mr-2 h-4 w-4" />
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

        <!-- Fraud-prevention header validation -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Fingerprint class="h-5 w-5" />
                    Diagnostic — Fraud-prevention headers
                </CardTitle>
                <CardDescription>
                    Captures your device fingerprint, sends the
                    <code class="text-xs">WEB_APP_VIA_SERVER</code> header set to HMRC's validator, and
                    shows what HMRC reports back.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-4">
                    <div>
                        <Button
                            :disabled="!connection.connected || fraudAction.running.value"
                            @click="handleValidateFraudHeaders"
                        >
                            <Loader2
                                v-if="fraudAction.running.value"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <ShieldCheck v-else class="mr-2 h-4 w-4" />
                            Validate fraud headers
                        </Button>
                    </div>

                    <Alert v-if="fraudErrorMessage" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>Couldn't run validation</AlertTitle>
                        <AlertDescription>{{ fraudErrorMessage }}</AlertDescription>
                    </Alert>

                    <template v-if="fraudResult">
                        <Alert
                            v-if="fraudResult.errors.length === 0 && fraudResult.warnings.length === 0"
                            variant="default"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            <AlertTitle>All clean</AlertTitle>
                            <AlertDescription>
                                HMRC's validator returned no errors or warnings for the headers we sent.
                            </AlertDescription>
                        </Alert>

                        <Alert v-if="fraudResult.errors.length > 0" variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertTitle>
                                {{ fraudResult.errors.length }} error{{ fraudResult.errors.length === 1 ? '' : 's' }}
                            </AlertTitle>
                            <AlertDescription>
                                <ul class="list-disc pl-5 mt-2 space-y-1">
                                    <li v-for="(err, i) in fraudResult.errors" :key="`err-${i}`">
                                        <span v-if="err.code" class="font-mono text-xs">{{ err.code }}:</span>
                                        {{ err.message ?? JSON.stringify(err) }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>

                        <Alert v-if="fraudResult.warnings.length > 0" variant="default">
                            <Info class="h-4 w-4" />
                            <AlertTitle>
                                {{ fraudResult.warnings.length }} warning{{ fraudResult.warnings.length === 1 ? '' : 's' }}
                            </AlertTitle>
                            <AlertDescription>
                                <ul class="list-disc pl-5 mt-2 space-y-1">
                                    <li v-for="(warn, i) in fraudResult.warnings" :key="`warn-${i}`">
                                        <span v-if="warn.code" class="font-mono text-xs">{{ warn.code }}:</span>
                                        {{ warn.message ?? JSON.stringify(warn) }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>

                        <details class="text-xs">
                            <summary class="cursor-pointer text-muted-foreground">
                                Show headers we sent ({{ Object.keys(fraudResult.headers_sent).length }})
                            </summary>
                            <pre class="mt-2 rounded-md border bg-muted p-3 overflow-auto">{{ JSON.stringify(fraudResult.headers_sent, null, 2) }}</pre>
                        </details>
                    </template>

                    <p
                        v-if="!fraudResult && !fraudErrorMessage"
                        class="text-sm text-muted-foreground"
                    >
                        Run the validator after connecting to HMRC. Captures your screen size, browser, and
                        timezone (no IP geolocation, no third-party tracking).
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Tax profile sheet -->
    <Sheet :open="profileSheetOpen" @update:open="profileSheetOpen = $event">
        <SheetContent side="right" class="sm:max-w-lg">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Briefcase class="h-5 w-5" />
                    Tax profile
                </SheetTitle>
                <SheetDescription>
                    Tell HMRC who you are. We use these fields when filing on your behalf.
                </SheetDescription>
            </SheetHeader>

            <form class="mt-6 space-y-6 px-6 py-4" @submit.prevent="handleProfileSubmit">
                <div class="space-y-2">
                    <Label for="business_type">Business type *</Label>
                    <select
                        id="business_type"
                        v-model="profileForm.business_type"
                        :disabled="profileSubmitting"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="" disabled>Select your business type…</option>
                        <option v-for="opt in businessTypes" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="profileErrors.business_type" class="text-destructive text-sm">
                        {{ profileErrors.business_type }}
                    </p>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="vat_registered"
                            :model-value="profileForm.vat_registered"
                            :disabled="profileSubmitting"
                            @update:model-value="profileForm.vat_registered = $event === true"
                        />
                        <Label for="vat_registered" class="cursor-pointer">VAT-registered</Label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Tick this if you (or your business) are registered for VAT with HMRC.
                    </p>
                    <p v-if="profileErrors.vat_registered" class="text-destructive text-sm">
                        {{ profileErrors.vat_registered }}
                    </p>
                </div>

                <div v-if="profileForm.vat_registered" class="space-y-2">
                    <Label for="vrn">VAT registration number (VRN) *</Label>
                    <Input
                        id="vrn"
                        v-model="profileForm.vrn"
                        placeholder="9 digits, e.g. 123456789"
                        maxlength="9"
                        :disabled="profileSubmitting"
                    />
                    <p v-if="profileErrors.vrn" class="text-destructive text-sm">
                        {{ profileErrors.vrn }}
                    </p>
                </div>

                <template v-if="isSoleTraderOrPartnership">
                    <div class="space-y-2">
                        <Label for="utr">Unique Taxpayer Reference (UTR) *</Label>
                        <Input
                            id="utr"
                            v-model="profileForm.utr"
                            placeholder="10 digits"
                            maxlength="10"
                            :disabled="profileSubmitting"
                        />
                        <p v-if="profileErrors.utr" class="text-destructive text-sm">
                            {{ profileErrors.utr }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="nino">National Insurance Number (NINO) *</Label>
                        <Input
                            id="nino"
                            v-model="profileForm.nino"
                            placeholder="e.g. AB123456C"
                            maxlength="9"
                            :disabled="profileSubmitting"
                        />
                        <p class="text-xs text-muted-foreground">
                            Stored encrypted at rest. Only the last 3 characters are shown back to you.
                        </p>
                        <p v-if="profileErrors.nino" class="text-destructive text-sm">
                            {{ profileErrors.nino }}
                        </p>
                    </div>
                </template>

                <div v-if="isLimitedCompany" class="space-y-2">
                    <Label for="companies_house_number">Companies House number *</Label>
                    <Input
                        id="companies_house_number"
                        v-model="profileForm.companies_house_number"
                        placeholder="8 characters, e.g. 12345678"
                        maxlength="8"
                        :disabled="profileSubmitting"
                    />
                    <p v-if="profileErrors.companies_house_number" class="text-destructive text-sm">
                        {{ profileErrors.companies_house_number }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="profileSubmitting"
                        @click="closeProfileSheet"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="profileSubmitting" class="min-w-[140px]">
                        <Loader2 v-if="profileSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        Save profile
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
