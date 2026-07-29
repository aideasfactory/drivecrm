<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { toast } from '@/components/ui/toast'
import {
    ArrowRight,
    Check,
    CreditCard,
    HelpCircle,
    Loader2,
    Lock,
    Smartphone,
} from 'lucide-vue-next'
import {
    stripeStatus,
    startStripeOnboarding,
    refreshStripeOnboarding,
} from '@/actions/App/Http/Controllers/InstructorController'
import type { AppPageProps } from '@/types'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
}

interface StripeStatus {
    connected: boolean
    onboarding_complete: boolean
    charges_enabled: boolean
    payouts_enabled: boolean
    stripe_account_id?: string
}

const props = defineProps<Props>()

const page = usePage<AppPageProps>()

// Driven by APPLE_APP_LINK / ANDROID_APP_LINK env vars (config/app_links.php),
// shared via HandleInertiaRequests. Null until the apps are published.
const appStoreUrl = computed(() => page.props.appLinks?.apple ?? null)
const playStoreUrl = computed(() => page.props.appLinks?.android ?? null)

const loading = ref(false)
const checkingStatus = ref(true)
const status = ref<StripeStatus>({
    connected: false,
    onboarding_complete: false,
    charges_enabled: false,
    payouts_enabled: false,
})

const firstName = computed(() => props.instructor.name.split(' ')[0])

interface JourneyStep {
    title: string
    description: string
    state: 'complete' | 'current' | 'upcoming'
}

const steps = computed<JourneyStep[]>(() => [
    {
        title: "You've been invited",
        description: 'We sent you an invite to join our platform.',
        state: 'complete',
    },
    {
        title: 'Create your account',
        description: "You've created your account. Great!",
        state: 'complete',
    },
    {
        title: 'Connect Stripe',
        description: 'Connect your Stripe account to receive payments and unlock your dashboard.',
        state: 'current',
    },
    {
        title: 'Download the app',
        description: "Once connected, you'll get full access and can download our mobile app.",
        state: 'upcoming',
    },
])

const checkStripeStatus = async () => {
    try {
        checkingStatus.value = true
        const response = await axios.get(stripeStatus.url(props.instructor.id))
        if (response?.data) {
            status.value = response.data
        }
    } catch {
        // Keep default (not connected) status on error
    } finally {
        checkingStatus.value = false
    }
}

const handleStripeConnect = async () => {
    loading.value = true

    try {
        let response

        if (!status.value.connected) {
            response = await axios.post(startStripeOnboarding.url(props.instructor.id))
            toast({ title: 'Redirecting to Stripe...' })
        } else if (!status.value.onboarding_complete) {
            response = await axios.post(refreshStripeOnboarding.url(props.instructor.id))
            toast({ title: 'Redirecting to Stripe...' })
        }

        if (response?.data?.url) {
            window.location.href = response.data.url
        }
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to start Stripe onboarding'
        toast({ title: message, variant: 'destructive' })
        loading.value = false
    }
}

const openStore = (url: string | null) => {
    if (url) {
        window.open(url, '_blank', 'noopener')
    } else {
        toast({ title: 'App download links are coming soon.' })
    }
}

onMounted(() => {
    checkStripeStatus()
})
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <!-- Welcome heading -->
        <div class="flex flex-col gap-2">
            <h1 class="text-3xl font-bold">Welcome, {{ firstName }}! 👋</h1>
            <p class="text-muted-foreground">
                Your dashboard unlocks once you
                <span class="font-semibold text-foreground">connect your Stripe account</span>.
            </p>
        </div>

        <!-- Connect Stripe + onboarding journey -->
        <Card>
            <CardContent class="grid gap-8 p-6 md:grid-cols-[3fr_2fr] md:gap-0 md:p-0">
                <!-- Left: Connect Stripe CTA -->
                <div class="flex flex-col items-center justify-center gap-4 text-center md:p-10">
                    <div class="flex h-14 w-14 items-center justify-center rounded-lg bg-muted">
                        <CreditCard class="h-7 w-7 text-muted-foreground" />
                    </div>

                    <h2 class="text-2xl font-semibold">Connect your Stripe account</h2>

                    <p class="max-w-sm text-muted-foreground">
                        We use Stripe to securely handle payments and payouts.
                        This only takes a few minutes.
                    </p>

                    <div v-if="checkingStatus" class="w-full max-w-sm">
                        <Skeleton class="h-11 w-full" />
                    </div>
                    <Button
                        v-else
                        size="lg"
                        class="w-full max-w-sm"
                        :disabled="loading"
                        @click="handleStripeConnect"
                    >
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <CreditCard v-else class="mr-2 h-4 w-4" />
                        {{ !status.connected ? 'Connect Stripe' : 'Complete Onboarding' }}
                        <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>

                    <p class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Lock class="h-4 w-4" />
                        Secure. Encrypted. Trusted by thousands of instructors.
                    </p>
                </div>

                <!-- Right: Onboarding journey -->
                <div class="flex flex-col gap-1 md:border-l md:p-8">
                    <h3 class="mb-4 text-center font-semibold md:text-left">
                        Your onboarding journey
                    </h3>

                    <div
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="flex gap-4"
                    >
                        <!-- Number / check circle + connector line -->
                        <div class="flex flex-col items-center">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                :class="{
                                    'border border-primary text-primary': step.state === 'complete',
                                    'bg-primary text-primary-foreground': step.state === 'current',
                                    'border text-muted-foreground': step.state === 'upcoming',
                                }"
                            >
                                <Check v-if="step.state === 'complete'" class="h-4 w-4" />
                                <template v-else>{{ index + 1 }}</template>
                            </div>
                            <div
                                v-if="index < steps.length - 1"
                                class="w-px flex-1 border-l border-dashed"
                            />
                        </div>

                        <!-- Step copy -->
                        <div class="flex flex-col gap-1 pb-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ step.title }}</span>
                                <Badge v-if="step.state === 'current'" variant="outline">
                                    Current step
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Download the app -->
        <Card>
            <CardContent class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <Smartphone class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="font-semibold">Next: Download the app</span>
                        <p class="max-w-md text-sm text-muted-foreground">
                            Once your Stripe account is connected, you'll be able to download
                            our app and start managing your business on the go.
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <Button variant="outline" @click="openStore(appStoreUrl)">
                        <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.53 4.08zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z" />
                        </svg>
                        App Store
                    </Button>
                    <Button variant="outline" @click="openStore(playStoreUrl)">
                        <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3.61 1.81a1.05 1.05 0 0 0-.48.9v18.58c0 .38.18.71.48.9l.07.04L14.1 11.8v-.24L3.68 1.77l-.07.04zm14.09 6.9-2.9 2.9 2.9 2.9 3.32-1.9c.95-.54.95-1.42 0-1.96l-3.32-1.94zM4.9 22.05l11.5-6.56-2.42-2.42-9.08 8.98zm0-20.1 9.08 8.98 2.42-2.42L4.9 1.95z" />
                        </svg>
                        Google Play
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Help footer -->
        <p class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
            <HelpCircle class="h-4 w-4" />
            Need help? Contact our support team.
        </p>
    </div>
</template>
