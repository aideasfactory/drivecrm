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
    Archive,
    Briefcase,
    CalendarClock,
    Car,
    CheckCircle2,
    ChevronDown,
    Fingerprint,
    Info,
    Landmark,
    Link2,
    Loader2,
    Lock,
    Plug,
    Power,
    Save,
    Settings,
    ShieldCheck,
    Wand2,
    Wallet,
} from 'lucide-vue-next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import VehicleSheet from '@/components/Hmrc/Vehicles/Sheet.vue';
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
    vehicles: { required: boolean; configured: boolean; active_count: number };
    summary: string;
}

interface BusinessTypeOption {
    value: string;
    label: string;
}

interface MethodOption {
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
    methodOptions?: MethodOption[];
    showHeader?: boolean;
    showDiagnostics?: boolean;
    instructorId?: number;
}>();

// When the panel is mounted inside the instructor profile (HmrcTab.vue),
// "Open X" buttons route to ?tab=hmrc&service=X so the service panel stays
// embedded within the instructor layout. Standalone /hmrc usage keeps the
// flat URLs (/hmrc/itsa etc.).
const serviceUrl = (service: 'itsa' | 'vat' | 'vehicles' | 'archive'): string => {
    if (props.instructorId) {
        return `/instructors/${props.instructorId}?tab=hmrc&service=${service}`;
    }
    return `/hmrc/${service}`;
};

const showHeader = computed(() => props.showHeader !== false);
const showDiagnostics = computed(() => props.showDiagnostics === true);

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

// Progressive step gating
const profileComplete = computed<boolean>(() => !!props.taxProfile?.completed_at);
const connectionActive = computed<boolean>(() => props.connection.connected);

// Vehicle step (Step 3) only applies when ITSA applies — VAT-only setups
// (e.g. limited companies) don't need a vehicle to file.
const vehiclesRequired = computed<boolean>(() => props.applicability?.vehicles?.required ?? false);
const vehiclesConfigured = computed<boolean>(() => props.applicability?.vehicles?.configured ?? false);
const vehiclesActiveCount = computed<number>(() => props.applicability?.vehicles?.active_count ?? 0);

const totalSteps = computed<number>(() => (vehiclesRequired.value ? 3 : 2));

const setupComplete = computed<boolean>(() => {
    if (!profileComplete.value) return false;
    if (!connectionActive.value) return false;
    if (vehiclesRequired.value && !vehiclesConfigured.value) return false;
    return true;
});

type StepState = 'locked' | 'active' | 'completed';

const step1State = computed<StepState>(() => (profileComplete.value ? 'completed' : 'active'));
const step2State = computed<StepState>(() => {
    if (connectionActive.value) return 'completed';
    return profileComplete.value ? 'active' : 'locked';
});
const step3State = computed<StepState>(() => {
    if (vehiclesConfigured.value) return 'completed';
    if (profileComplete.value && connectionActive.value) return 'active';
    return 'locked';
});

const completedStepCount = computed<number>(() => {
    let n = 0;
    if (profileComplete.value) n++;
    if (connectionActive.value) n++;
    if (vehiclesRequired.value && vehiclesConfigured.value) n++;
    return n;
});

const activeStepDescription = computed<string>(() => {
    if (!profileComplete.value) return `Step 1 of ${totalSteps.value} — tell us about your business.`;
    if (!connectionActive.value) return `Step 2 of ${totalSteps.value} — connect your HMRC account.`;
    return `Step 3 of ${totalSteps.value} — add your tuition vehicle.`;
});

const profileCardOpen = ref<boolean>(true);
const connectionCardOpen = ref<boolean>(true);

// Setup section is hidden by default once setupComplete; user reveals it
// via the cog button on the services card.
const showSetupSection = ref<boolean>(false);

const toggleSetupSection = () => {
    showSetupSection.value = !showSetupSection.value;
};

// Vehicle Sheet (opened from Step 3 to avoid an unnecessary page navigation)
const vehicleSheetOpen = ref<boolean>(false);

const openVehicleSheet = () => {
    vehicleSheetOpen.value = true;
};

const handleVehicleSheetClose = (saved: boolean) => {
    vehicleSheetOpen.value = false;
    if (saved) {
        // Reload the current page's Inertia props so the new vehicle flips
        // Step 3 from active to completed and unlocks the services card.
        router.reload({ only: ['hmrc', 'applicability'] });
    }
};

watch(
    [setupComplete, profileComplete, connectionActive],
    ([sc, pc, ca]) => {
        // Auto-collapse the completed step cards once all setup steps are done
        if (sc) {
            profileCardOpen.value = false;
            connectionCardOpen.value = false;
        } else {
            profileCardOpen.value = !pc;
            connectionCardOpen.value = pc && !ca;
        }
    },
    { immediate: true },
);

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

        <!-- Progress banner (hidden once setup is complete — services card at the top conveys "ready" on its own) -->
        <div
            v-if="!setupComplete"
            class="flex flex-col gap-3 rounded-lg border bg-muted/30 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-primary bg-primary/5 text-primary">
                    <span class="text-sm font-semibold">{{ completedStepCount + 1 }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-medium">Set up HMRC filing</span>
                    <span class="text-xs text-muted-foreground">{{ activeStepDescription }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-2 w-32 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full bg-primary transition-all duration-300"
                        :style="{ width: `${(completedStepCount / totalSteps) * 100}%` }"
                    />
                </div>
                <span class="text-xs font-medium text-muted-foreground">{{ completedStepCount }} / {{ totalSteps }}</span>
            </div>
        </div>

        <!-- Services group (PROMINENT — shown at top when setup complete) -->
        <Card v-if="setupComplete && applicability?.profile_complete" class="border-none -m-6 shadow-none">
            <CardHeader>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Landmark class="h-5 w-5" />
                            HMRC services
                        </CardTitle>
                        <CardDescription class="mt-1">{{ applicability.summary }}</CardDescription>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="showSetupSection ? 'Hide setup details' : 'Show setup details'"
                            :aria-expanded="showSetupSection"
                            @click="toggleSetupSection"
                        >
                            <Settings class="h-4 w-4" :class="{ 'text-primary': showSetupSection }" />
                        </Button>
                    </div>
                </div>
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
                            <a :href="serviceUrl('itsa')">Open ITSA submissions</a>
                        </Button>
                    </div>

                    <!-- Year-end archives -->
                    <div
                        v-if="applicability.itsa.applies || applicability.vat.applies"
                        class="rounded-md border bg-muted/30 p-4 flex flex-col gap-2"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <Archive class="h-4 w-4" />
                            Year-end archives
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Download a ZIP of your tax year — finance records, mileage, receipts, HMRC submission payloads
                            and a cover-sheet PDF. For your accountant or as an HMRC enquiry pack. Retained 6 years.
                        </p>
                        <Button as-child size="sm" variant="outline" class="w-fit">
                            <a :href="serviceUrl('archive')">Open Archives</a>
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
                            <a :href="serviceUrl('vat')">Open VAT submissions</a>
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

  
        <!-- Step 1 + Step 2 (+ Step 3 if ITSA applies) — hidden once setupComplete unless user toggles cog -->
        <div
            v-if="!setupComplete || showSetupSection"
            class="grid gap-6"
            :class="vehiclesRequired ? 'lg:grid-cols-3' : 'lg:grid-cols-2'"
        >
            <!-- Step 1: Tax profile -->
            <Collapsible v-model:open="profileCardOpen">
                <Card
                    class="transition-all"
                    :class="{
                        'border-primary/60 ring-1 ring-primary/20': step1State === 'active',
                        'opacity-100': step1State === 'completed',
                    }"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-3 min-w-0">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                    :class="step1State === 'completed'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400'
                                        : 'bg-primary/10 text-primary'"
                                >
                                    <CheckCircle2 v-if="step1State === 'completed'" class="h-4 w-4" />
                                    <span v-else>1</span>
                                </div>
                                <div class="min-w-0">
                                    <CardTitle class="flex items-center gap-2 text-base">
                                        <Briefcase class="h-4 w-4" />
                                        Your tax profile
                                    </CardTitle>
                                    <CardDescription class="mt-1">
                                        <template v-if="step1State === 'completed'">
                                            {{ businessTypeLabel(taxProfile?.business_type ?? null) }}{{ taxProfile?.vat_registered ? ' · VAT registered' : '' }}
                                        </template>
                                        <template v-else>
                                            Tell us about your business so we can show the right HMRC services.
                                        </template>
                                    </CardDescription>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <Badge
                                    v-if="step1State === 'completed'"
                                    variant="secondary"
                                    class="gap-1 bg-green-100 text-green-700 hover:bg-green-100 dark:bg-green-950 dark:text-green-400"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Done
                                </Badge>
                                <Badge v-else variant="default" class="gap-1">Next</Badge>
                                <CollapsibleTrigger v-if="step1State === 'completed'" as-child>
                                    <Button variant="ghost" size="icon" class="h-7 w-7">
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': profileCardOpen }" />
                                    </Button>
                                </CollapsibleTrigger>
                            </div>
                        </div>
                    </CardHeader>

                    <!-- Active state body (always visible when step1 active) -->
                    <CardContent v-if="step1State === 'active'">
                        <div class="flex flex-col gap-4">
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

                    <!-- Completed state body (collapsible details) -->
                    <CollapsibleContent v-if="step1State === 'completed'">
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
                                    <Button variant="outline" size="sm" @click="openProfileSheet">
                                        <Save class="mr-2 h-4 w-4" />
                                        Edit tax profile
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>

            <!-- Step 2: Connection -->
            <Collapsible v-model:open="connectionCardOpen">
                <Card
                    class="transition-all"
                    :class="{
                        'border-primary/60 ring-1 ring-primary/20': step2State === 'active',
                        'opacity-60': step2State === 'locked',
                    }"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-3 min-w-0">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                    :class="step2State === 'completed'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400'
                                        : step2State === 'active'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-muted-foreground'"
                                >
                                    <CheckCircle2 v-if="step2State === 'completed'" class="h-4 w-4" />
                                    <Lock v-else-if="step2State === 'locked'" class="h-4 w-4" />
                                    <span v-else>2</span>
                                </div>
                                <div class="min-w-0">
                                    <CardTitle class="flex items-center gap-2 text-base">
                                        <Link2 class="h-4 w-4" />
                                        Connect to HMRC
                                    </CardTitle>
                                    <CardDescription class="mt-1">
                                        <template v-if="step2State === 'completed'">
                                            Connected — token refreshes automatically.
                                        </template>
                                        <template v-else-if="step2State === 'active'">
                                            Connect your HMRC account to begin filing.
                                        </template>
                                        <template v-else>
                                            Complete Step 1 first to unlock.
                                        </template>
                                    </CardDescription>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <Badge
                                    v-if="step2State === 'completed'"
                                    variant="secondary"
                                    class="gap-1 bg-green-100 text-green-700 hover:bg-green-100 dark:bg-green-950 dark:text-green-400"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Done
                                </Badge>
                                <Badge v-else-if="step2State === 'active'" variant="default" class="gap-1">Next</Badge>
                                <Badge v-else variant="outline" class="gap-1">
                                    <Lock class="h-3 w-3" />
                                    Locked
                                </Badge>
                                <CollapsibleTrigger v-if="step2State === 'completed'" as-child>
                                    <Button variant="ghost" size="icon" class="h-7 w-7">
                                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': connectionCardOpen }" />
                                    </Button>
                                </CollapsibleTrigger>
                            </div>
                        </div>
                    </CardHeader>

                    <!-- Locked state body -->
                    <CardContent v-if="step2State === 'locked'">
                        <p class="text-sm text-muted-foreground flex items-center gap-2">
                            <Lock class="h-4 w-4" />
                            Set up your tax profile first.
                        </p>
                    </CardContent>

                    <!-- Active state body -->
                    <CardContent v-else-if="step2State === 'active'">
                        <div class="flex flex-col gap-4">
                            <p class="text-sm text-muted-foreground">
                                Connecting opens a secure HMRC sign-in page in your browser.
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

                    <!-- Completed state body (collapsible details) -->
                    <CollapsibleContent v-if="step2State === 'completed'">
                        <CardContent>
                            <div class="flex flex-col gap-4">
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
                                        <dd class="font-medium flex items-center gap-2 flex-wrap">
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
                                    <Button variant="destructive" size="sm" :disabled="disconnecting" @click="handleDisconnect">
                                        <Loader2 v-if="disconnecting" class="mr-2 h-4 w-4 animate-spin" />
                                        <Power v-else class="mr-2 h-4 w-4" />
                                        Disconnect
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>

            <!-- Step 3: Vehicle (only when ITSA applies) -->
            <Card
                v-if="vehiclesRequired"
                class="transition-all"
                :class="{
                    'border-primary/60 ring-1 ring-primary/20': step3State === 'active',
                    'opacity-60': step3State === 'locked',
                }"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                :class="step3State === 'completed'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400'
                                    : step3State === 'active'
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'"
                            >
                                <CheckCircle2 v-if="step3State === 'completed'" class="h-4 w-4" />
                                <Lock v-else-if="step3State === 'locked'" class="h-4 w-4" />
                                <span v-else>3</span>
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Car class="h-4 w-4" />
                                    Add your vehicle
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    <template v-if="step3State === 'completed'">
                                        {{ vehiclesActiveCount }} active vehicle{{ vehiclesActiveCount === 1 ? '' : 's' }} configured.
                                    </template>
                                    <template v-else-if="step3State === 'active'">
                                        Choose Simplified or Advanced for each tuition vehicle — this drives every ITSA quarterly.
                                    </template>
                                    <template v-else>
                                        Complete Steps 1 and 2 first to unlock.
                                    </template>
                                </CardDescription>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <Badge
                                v-if="step3State === 'completed'"
                                variant="secondary"
                                class="gap-1 bg-green-100 text-green-700 hover:bg-green-100 dark:bg-green-950 dark:text-green-400"
                            >
                                <CheckCircle2 class="h-3 w-3" />
                                Done
                            </Badge>
                            <Badge v-else-if="step3State === 'active'" variant="default" class="gap-1">Next</Badge>
                            <Badge v-else variant="outline" class="gap-1">
                                <Lock class="h-3 w-3" />
                                Locked
                            </Badge>
                        </div>
                    </div>
                </CardHeader>

                <!-- Locked state body -->
                <CardContent v-if="step3State === 'locked'">
                    <p class="text-sm text-muted-foreground flex items-center gap-2">
                        <Lock class="h-4 w-4" />
                        Finish your tax profile and HMRC connection first.
                    </p>
                </CardContent>

                <!-- Active state body -->
                <CardContent v-else-if="step3State === 'active'">
                    <div class="flex flex-col gap-4">
                        <p class="text-sm text-muted-foreground">
                            We need at least one tuition vehicle with a Simplified or Advanced method choice
                            before HMRC filings can be calculated.
                        </p>
                        <div>
                            <Button @click="openVehicleSheet">
                                <Car class="mr-2 h-4 w-4" />
                                Add a vehicle
                            </Button>
                        </div>
                    </div>
                </CardContent>

                <!-- Completed state body -->
                <CardContent v-else>
                    <div class="flex flex-col gap-4">
                        <p class="text-sm text-muted-foreground">
                            All active vehicles have a method chosen and are ready to drive ITSA calculations.
                        </p>
                        <div>
                            <Button as-child variant="outline" size="sm">
                                <a :href="serviceUrl('vehicles')">
                                    <Car class="mr-2 h-4 w-4" />
                                    Manage vehicles
                                </a>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Step 3: Services — locked placeholder when setup not yet complete -->
        <Card v-if="!setupComplete" class="opacity-60">
            <CardHeader>
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground text-sm font-semibold">
                            <Lock class="h-4 w-4" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Landmark class="h-4 w-4" />
                                HMRC services
                            </CardTitle>
                            <CardDescription class="mt-1">
                                Finish the steps above to unlock ITSA submissions, VAT submissions, vehicle management and year-end archives.
                            </CardDescription>
                        </div>
                    </div>
                    <Badge variant="outline" class="gap-1 shrink-0">
                        <Lock class="h-3 w-3" />
                        Locked
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="flex items-center gap-2 rounded-md border border-dashed bg-muted/20 p-3 text-sm text-muted-foreground">
                        <CalendarClock class="h-4 w-4 shrink-0" />
                        MTD Income Tax (ITSA)
                    </div>
                    <div class="flex items-center gap-2 rounded-md border border-dashed bg-muted/20 p-3 text-sm text-muted-foreground">
                        <Archive class="h-4 w-4 shrink-0" />
                        Year-end archives
                    </div>
                    <div class="flex items-center gap-2 rounded-md border border-dashed bg-muted/20 p-3 text-sm text-muted-foreground">
                        <Wallet class="h-4 w-4 shrink-0" />
                        MTD VAT
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Diagnostic -->
        <Card v-if="showDiagnostics">
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
        <Card v-if="showDiagnostics">
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

    <!-- Vehicle Sheet — opened from Step 3 instead of deep-linking to /hmrc/vehicles -->
    <VehicleSheet
        :open="vehicleSheetOpen"
        :vehicle="null"
        :method-options="methodOptions ?? []"
        @close="handleVehicleSheetClose"
    />
</template>
