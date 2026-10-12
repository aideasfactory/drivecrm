<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Mail, Phone, MapPin, Edit, CreditCard, Loader2, CheckCircle, LogOut, ShieldCheck, Copy, Send, RotateCcw } from 'lucide-vue-next'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from '@/components/ui/toast'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import type { InstructorDetail } from '@/types/instructor'
import { stripeStatus, startStripeOnboarding, refreshStripeOnboarding, resetStripe } from '@/actions/App/Http/Controllers/InstructorController'
import { logout } from '@/routes'
import { useRole } from '@/composables/useRole'
import { useStripeSetupLink } from '@/composables/useStripeSetupLink'

interface Props {
    instructor: InstructorDetail
}

interface Emits {
    (e: 'edit'): void
}

interface StripeStatus {
    connected: boolean
    onboarding_complete: boolean
    charges_enabled: boolean
    payouts_enabled: boolean
    stripe_account_id?: string
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()
const { isOwner, isInstructor } = useRole()
const { sendingSetupLink, sendSetupLink } = useStripeSetupLink(() => props.instructor.id)
const isResetStripeDialogOpen = ref(false)
const resettingStripe = ref(false)

const page = usePage()
const showMtdButton = computed<boolean>(
    () => Boolean((page.props as { hmrcFlags?: { show_mtd_button?: boolean } }).hmrcFlags?.show_mtd_button),
)

const loading = ref(false)
const checkingStatus = ref(true)
const status = ref<StripeStatus>({
    connected: false,
    onboarding_complete: false,
    charges_enabled: false,
    payouts_enabled: false
})

const copyPin = async () => {
    if (!props.instructor.pin) {
        return
    }

    try {
        await navigator.clipboard.writeText(props.instructor.pin)
        toast({ title: 'Instructor code copied to clipboard' })
    } catch {
        toast({ title: 'Failed to copy instructor code', variant: 'destructive' })
    }
}

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const checkStripeStatus = async () => {
    try {
        checkingStatus.value = true
        const url = stripeStatus.url(props.instructor.id)
        const response = await axios.get(url)
        if (response && response.data) {
            status.value = response.data
        }
    } catch (error) {
        console.error('Failed to check Stripe status:', error)
        // Keep default status values on error
        status.value = {
            connected: false,
            onboarding_complete: false,
            charges_enabled: false,
            payouts_enabled: false
        }
    } finally {
        checkingStatus.value = false
    }
}

const handleStripeConnect = async () => {
    loading.value = true

    try {
        let response

        if (!status.value.connected) {
            // Start new onboarding
            const url = startStripeOnboarding.url(props.instructor.id)
            response = await axios.post(url)
            toast({ title: 'Redirecting to Stripe...' })
        } else if (!status.value.onboarding_complete) {
            // Refresh incomplete onboarding
            const url = refreshStripeOnboarding.url(props.instructor.id)
            response = await axios.post(url)
            toast({ title: 'Redirecting to Stripe...' })
        }

        if (response?.data?.url) {
            // Redirect to Stripe
            window.location.href = response.data.url
        }
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to start Stripe onboarding'
        toast({ title: message, variant: 'destructive' })
        loading.value = false
    }
}

const confirmResetStripe = async () => {
    if (resettingStripe.value) {
        return
    }

    resettingStripe.value = true

    try {
        const { data } = await axios.post(resetStripe.url(props.instructor.id))
        status.value = {
            connected: false,
            onboarding_complete: false,
            charges_enabled: false,
            payouts_enabled: false,
        }
        isResetStripeDialogOpen.value = false
        toast({ title: data?.message ?? 'Stripe connection reset.' })
        router.reload({ preserveScroll: true })
    } catch (error: any) {
        const message = error?.response?.data?.message ?? 'Failed to reset the Stripe connection.'
        toast({ title: message, variant: 'destructive' })
    } finally {
        resettingStripe.value = false
    }
}

onMounted(() => {
    checkStripeStatus()
})
</script>

<template>
    <div class="flex flex-col gap-4 border-b pb-6">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <!-- Large Avatar -->
                <Avatar class="h-20 w-20">
                    <AvatarImage v-if="instructor.avatar" :src="instructor.avatar" :alt="instructor.name" />
                    <AvatarFallback class="text-2xl">
                        {{ getInitials(instructor.name) }}
                    </AvatarFallback>
                </Avatar>

                <!-- Instructor Info -->
                <div class="flex flex-col gap-3">
                    <h2 class="text-3xl font-bold">{{ instructor.name }}</h2>

                    <!-- Contact Info Row -->
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div
                            v-if="instructor.pin"
                            class="flex items-center gap-2"
                        >
                            <span class="text-muted-foreground">Your instructor code</span>
                            <Badge
                                variant="secondary"
                                class="px-3 py-1.5 font-mono text-base tracking-wider"
                            >
                                {{ instructor.pin }}
                            </Badge>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                aria-label="Copy instructor code"
                                @click="copyPin"
                            >
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>

                        <div
                            v-if="instructor.phone"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <Phone class="h-4 w-4" />
                            <span>{{ instructor.phone }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Mail class="h-4 w-4" />
                            <span>{{ instructor.email }}</span>
                        </div>

                        <div
                            v-if="instructor.postcode"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <MapPin class="h-4 w-4" />
                            <span>{{ instructor.postcode }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center justify-end gap-2">
                <!-- Stripe Connection Status & Button -->
                <div v-if="!checkingStatus" class="flex items-center gap-2">
                    <!-- Fully Connected — visual label only -->
                    <Button
                        v-if="status.connected && status.onboarding_complete && status.charges_enabled"
                        variant="outline"
                        class="min-w-[180px] border-green-600 text-green-600 py-2.5 cursor-default hover:bg-transparent hover:text-green-600"
                        tabindex="-1"
                    >
                        <CheckCircle class="mr-2 h-4 w-4" />
                        Stripe Connected
                    </Button>

                    <!-- Owners email the instructor a setup link instead of onboarding on their behalf -->
                    <Button
                        v-if="isOwner && !checkingStatus && (!status.connected || !status.onboarding_complete)"
                        @click="sendSetupLink"
                        :disabled="sendingSetupLink"
                        variant="outline"
                        class="min-w-[180px] border-red-600 text-red-600 py-2.5 hover:bg-red-50 hover:text-red-600 cursor-pointer"
                    >
                        <Loader2 v-if="sendingSetupLink" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Send Stripe link
                    </Button>

                    <!-- Connect/Complete Button -->
                    <Button
                        v-else-if="!isOwner && (!status.connected || !status.onboarding_complete)"
                        @click="handleStripeConnect"
                        :disabled="loading"
                        variant="outline"
                        class="min-w-[180px] border-red-600 text-red-600 py-2.5 hover:bg-red-50 hover:text-red-600 cursor-pointer"
                    >
                        <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                        <CreditCard v-else class="mr-2 h-4 w-4" />
                        {{ !status.connected ? 'Connect Stripe' : 'Complete Onboarding' }}
                    </Button>
                </div>

                <!-- HMRC Connected Button (instructor only, when token exists) -->
                <Button
                    v-if="showMtdButton && isInstructor && instructor.hmrc_connected"
                    variant="outline"
                    class="min-w-[180px] border-green-600 text-green-600 py-2.5 hover:bg-green-50 hover:text-green-600 cursor-pointer"
                    @click="router.visit(`/instructors/${instructor.id}`, { data: { tab: 'hmrc' }, preserveScroll: true })"
                >
                    <CheckCircle class="mr-2 h-4 w-4" />
                    HMRC Connected
                </Button>

                <!-- HMRC / Tax Button (instructor only, when not connected) -->
                <Button
                    v-if="showMtdButton && isInstructor && !instructor.hmrc_connected"
                    variant="outline"
                    class="min-w-[180px] py-2.5 cursor-pointer"
                    @click="router.visit(`/instructors/${instructor.id}`, { data: { tab: 'hmrc' }, preserveScroll: true })"
                >
                    <ShieldCheck class="mr-2 h-4 w-4" />
                    HMRC / Tax
                </Button>

                <!-- Owner-only: clear a stale Stripe Connect id so the next connect creates a new account -->
                <Button
                    v-if="isOwner"
                    variant="outline"
                    class="min-w-[160px] cursor-pointer"
                    :disabled="resettingStripe"
                    @click="isResetStripeDialogOpen = true"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    Reset Stripe
                </Button>

                <!-- Edit Profile Button -->
                <Button @click="emit('edit')">
                    <Edit class="mr-2 h-4 w-4" />
                    Edit Profile
                </Button>

                <!-- Logout Button (hidden for owners — they use the sidebar menu) -->
                <Button v-if="!isOwner" variant="outline" @click="router.flushAll(); router.post(logout.url())">
                    <LogOut class="mr-2 h-4 w-4" />
                    Logout
                </Button>
            </div>
        </div>

        <Dialog v-model:open="isResetStripeDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reset Stripe connection?</DialogTitle>
                    <DialogDescription>
                        This clears the saved Stripe account for {{ instructor.name }} in Drive.
                        Nothing is deleted on Stripe. The next time they connect, from the website
                        or the app, a new Stripe account is created.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="resettingStripe"
                        @click="isResetStripeDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        class="min-w-[140px]"
                        :disabled="resettingStripe"
                        @click="confirmResetStripe"
                    >
                        <Loader2 v-if="resettingStripe" class="mr-2 h-4 w-4 animate-spin" />
                        <RotateCcw v-else class="mr-2 h-4 w-4" />
                        Reset Stripe
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
