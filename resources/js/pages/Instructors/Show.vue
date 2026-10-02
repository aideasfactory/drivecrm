<script setup lang="ts">
import { ref, computed } from 'vue'
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { toast } from '@/components/ui/toast'
import { Loader2, MailWarning, Smartphone } from 'lucide-vue-next'
import { useRole } from '@/composables/useRole'
import AppLayout from '@/layouts/AppLayout.vue'
import InstructorHeader from '@/components/Instructors/InstructorHeader.vue'
import ScheduleTab from '@/components/Instructors/Tabs/ScheduleTab.vue'
import DetailsTab from '@/components/Instructors/Tabs/DetailsTab.vue'
import ActivePupilsTab from '@/components/Instructors/Tabs/ActivePupilsTab.vue'
import ActionsTab from '@/components/Instructors/Tabs/ActionsTab.vue'
import ReportsTab from '@/components/Instructors/Tabs/ReportsTab.vue'
import FinancesTab from '@/components/Instructors/Tabs/FinancesTab.vue'
import StudentTab from '@/components/Instructors/Tabs/StudentTab.vue'
import HmrcTab from '@/components/Instructors/Tabs/HmrcTab.vue'
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'
import StripeOnboardingWelcome from '@/components/Instructors/StripeOnboardingWelcome.vue'
import type { InstructorDetail, InstructorFormOptions } from '@/types/instructor'

interface ConnectionStatus {
    connected: boolean
    connected_at: string | null
    expires_at: string | null
    refresh_expires_at: string | null
    scopes: string[]
    days_until_refresh_expiry: number | null
}

interface TaxProfile {
    completed_at: string | null
    business_type: string | null
    vat_registered: boolean
    vrn: string | null
    utr: string | null
    nino: string | null
    companies_house_number: string | null
}

interface ItsaThreshold {
    date: string
    income: number
    label: string
}

interface Applicability {
    profile_complete: boolean
    business_type: string | null
    vat: { applies: boolean; vrn: string | null }
    itsa: { applies: boolean; status: string; thresholds: ItsaThreshold[] }
    corporation_tax: { applies: false; reason: string }
    summary: string
}

interface BusinessTypeOption {
    value: string
    label: string
}

interface HmrcData {
    environment: string
    connection: ConnectionStatus
    helloWorldResponse: Record<string, unknown> | null
    taxProfile: TaxProfile | null
    applicability: Applicability | null
    businessTypes: BusinessTypeOption[]
}

interface HmrcServicePayload {
    name: string
    data: Record<string, unknown> | null
}

interface Props {
    instructor: InstructorDetail
    tab?: string
    subtab?: string
    student?: number
    hmrc?: HmrcData | null
    hmrcService?: HmrcServicePayload | null
    formOptions: InstructorFormOptions
}

const props = withDefaults(defineProps<Props>(), {
    tab: 'schedule',
    subtab: 'summary',
})

const isEditSheetOpen = ref(false)
const { isOwner } = useRole()
const isResendingInvite = ref(false)
const welcomeEmailPending = ref(Boolean(props.instructor.welcome_email_pending))
const appOnboardingComplete = ref(Boolean(props.instructor.app_onboarding_complete))
const isBypassDialogOpen = ref(false)
const isMarkingOnboardingComplete = ref(false)

const resendInvite = async () => {
    if (isResendingInvite.value) {
        return
    }

    isResendingInvite.value = true

    try {
        const { data } = await axios.post(`/instructors/${props.instructor.id}/resend-invite`)
        welcomeEmailPending.value = Boolean(data?.welcome_email_pending)
        toast({ title: data?.message ?? 'Welcome email resent.' })
    } catch (error: any) {
        const message = error?.response?.data?.message ?? 'Failed to resend the welcome email.'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isResendingInvite.value = false
    }
}

const markAppOnboardingComplete = async () => {
    if (isMarkingOnboardingComplete.value) {
        return
    }

    isMarkingOnboardingComplete.value = true

    try {
        const { data } = await axios.post(
            `/instructors/${props.instructor.id}/app-onboarding/complete`,
        )
        appOnboardingComplete.value = Boolean(data?.app_onboarding_complete)
        isBypassDialogOpen.value = false
        toast({ title: data?.message ?? 'In-app onboarding marked complete.' })
    } catch (error: any) {
        const message =
            error?.response?.data?.message ?? 'Failed to mark in-app onboarding complete.'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isMarkingOnboardingComplete.value = false
    }
}

type TabType = 'schedule' | 'details' | 'active-pupils' | 'reports' | 'finances' | 'actions' | 'student' | 'hmrc'

const tabs: { key: TabType; label: string }[] = [
    { key: 'schedule', label: 'Schedule' },
    { key: 'details', label: 'Details' },
    { key: 'active-pupils', label: 'Pupils' },
    { key: 'reports', label: 'Reports' },
    { key: 'finances', label: 'Finances' },
    // { key: 'actions', label: 'Actions' }, // Temporarily hidden — will revisit once functionality is defined
]

const activeTab = computed(() => props.tab || 'schedule')

const switchTab = (tabKey: TabType) => {
    router.visit(`/instructors/${props.instructor.id}`, {
        data: { tab: tabKey },
        preserveState: true,
        preserveScroll: true,
    })
}

const isActiveTab = (tabKey: TabType) => {
    return activeTab.value === tabKey
}

const breadcrumbs = [
    { title: 'Instructors', href: '/instructors' },
    { title: props.instructor.name },
]
</script>

<template>
    <Head :title="instructor.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Instructor Header -->
            <InstructorHeader
                :instructor="instructor"
                @edit="isEditSheetOpen = true"
            />

            <!-- Welcome email pending banner — owners only -->
            <div
                v-if="isOwner && welcomeEmailPending"
                class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <MailWarning class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div class="text-sm">
                        <p class="font-medium">Welcome email hasn't been delivered yet</p>
                        <p class="text-amber-800">
                            We couldn't confirm the password-setup email was sent to
                            <strong>{{ instructor.email }}</strong>. Resend it so they can
                            access their account.
                        </p>
                    </div>
                </div>
                <Button
                    variant="outline"
                    class="border-amber-300 bg-white text-amber-900 hover:bg-amber-100"
                    :disabled="isResendingInvite"
                    @click="resendInvite"
                >
                    <Loader2 v-if="isResendingInvite" class="mr-2 h-4 w-4 animate-spin" />
                    {{ isResendingInvite ? 'Resending…' : 'Resend welcome email' }}
                </Button>
            </div>

            <!-- In-app onboarding bypass — owners only, independent of Stripe -->
            <Card v-if="isOwner && !appOnboardingComplete">
                <CardContent class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <Smartphone class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
                        <div class="text-sm">
                            <p class="font-medium">Mobile app onboarding is not finished</p>
                            <p class="text-muted-foreground">
                                The app still holds
                                <strong>{{ instructor.name }}</strong>
                                on the in-app onboarding steps. Mark them complete so the app lets them through.
                                This does not change their Stripe setup.
                            </p>
                        </div>
                    </div>
                    <Button
                        class="min-w-[240px]"
                        :disabled="isMarkingOnboardingComplete"
                        @click="isBypassDialogOpen = true"
                    >
                        <Smartphone class="mr-2 h-4 w-4" />
                        Mark in-app onboarding complete
                    </Button>
                </CardContent>
            </Card>

            <!-- Onboarding welcome (shown until Stripe is connected) -->
            <StripeOnboardingWelcome
                v-if="!instructor.onboarding_complete"
                :instructor="instructor"
                class="py-6"
            />

            <!-- Tab Navigation (hidden when viewing a student or HMRC sub-page) -->
            <div class="flex gap-1 border-b" v-if="instructor.onboarding_complete && activeTab !== 'student' && activeTab !== 'hmrc'">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="switchTab(tab.key)"
                    :class="[
                        'px-4 py-2 font-medium text-sm transition-colors',
                        isActiveTab(tab.key)
                            ? 'border-b-2 border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab Content -->
            <div class="flex flex-col gap-6" v-if="instructor.onboarding_complete">
                <!-- Schedule Tab -->
                <ScheduleTab v-if="activeTab === 'schedule'" :instructor-id="instructor.id" />

                <!-- Details Tab -->
                <DetailsTab
                    v-if="activeTab === 'details'"
                    :instructor="instructor"
                    :subtab="subtab"
                />

                <!-- Reports Tab -->
                <ReportsTab
                    v-if="activeTab === 'reports'"
                    :instructor="instructor"
                />

                <!-- Active Pupils Tab -->
                <ActivePupilsTab
                    v-if="activeTab === 'active-pupils'"
                    :instructor="instructor"
                />

                <!-- Finances Tab -->
                <FinancesTab
                    v-if="activeTab === 'finances'"
                    :instructor="instructor"
                />

                <!-- Actions Tab -->
                <ActionsTab v-if="activeTab === 'actions'" />

                <!-- Student Detail Tab -->
                <StudentTab
                    v-if="activeTab === 'student' && student"
                    :instructor="instructor"
                    :student-id="student"
                    :subtab="subtab"
                />

                <!-- HMRC Tab -->
                <HmrcTab
                    v-if="activeTab === 'hmrc' && hmrc"
                    :instructor-id="instructor.id"
                    :hmrc="hmrc"
                    :hmrc-service="hmrcService"
                />
            </div>
        </div>

        <Dialog v-model:open="isBypassDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Smartphone class="h-5 w-5" />
                        Mark in-app onboarding complete?
                    </DialogTitle>
                    <DialogDescription>
                        This tells the mobile app that
                        <strong>{{ instructor.name }}</strong>
                        has finished onboarding. They will no longer be stopped on that step.
                        Their Stripe setup is left as it is.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isMarkingOnboardingComplete"
                        @click="isBypassDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        class="min-w-[160px]"
                        :disabled="isMarkingOnboardingComplete"
                        @click="markAppOnboardingComplete"
                    >
                        <Loader2
                            v-if="isMarkingOnboardingComplete"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Smartphone v-else class="mr-2 h-4 w-4" />
                        {{ isMarkingOnboardingComplete ? 'Saving…' : 'Mark complete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Instructor Sheet -->
        <AddInstructorSheet
            v-model:open="isEditSheetOpen"
            :instructor="instructor"
            :form-options="formOptions"
            @instructor-updated="isEditSheetOpen = false"
        />
    </AppLayout>
</template>
