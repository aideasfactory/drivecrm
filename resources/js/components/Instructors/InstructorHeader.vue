<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Mail, Phone, MapPin, Edit, CreditCard, Loader2, CheckCircle } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import type { InstructorDetail } from '@/types/instructor'
import { stripeStatus, startStripeOnboarding, refreshStripeOnboarding } from '@/actions/App/Http/Controllers/InstructorController'

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

const loading = ref(false)
const checkingStatus = ref(true)
const status = ref<StripeStatus>({
    connected: false,
    onboarding_complete: false,
    charges_enabled: false,
    payouts_enabled: false
})

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
            <div class="flex items-center gap-2">
                <!-- Stripe Connection Status & Button -->
                <div v-if="!checkingStatus" class="flex items-center gap-2">
                    <!-- Fully Connected Badge -->
                    <Badge
                        v-if="status.connected && status.onboarding_complete && status.charges_enabled"
                        variant="outline"
                        class="gap-1.5 border-green-600 text-green-600 py-2.5"
                    >
                        <CheckCircle class="h-3.5 w-3.5" />
                        Stripe Connected
                    </Badge>

                    <!-- Connect/Complete Button -->
                    <Button
                        v-if="!status.connected || !status.onboarding_complete"
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

                <!-- Edit Profile Button -->
                <Button @click="emit('edit')">
                    <Edit class="mr-2 h-4 w-4" />
                    Edit Profile
                </Button>
            </div>
        </div>
    </div>
</template>
