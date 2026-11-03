<script setup lang="ts">
import { ref, computed } from 'vue'
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { toast } from '@/components/ui/toast'
import { Loader2, MailWarning, SkipForward, Trash2, Undo2 } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import {
    cancelDeletion,
    forceCompleteAppOnboarding,
    resendWelcomeEmail,
} from '@/actions/App/Http/Controllers/InstructorController'

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
const welcomeEmailLastSentAt = ref(props.instructor.welcome_email_last_sent_at ?? null)
const hasSignedUp = Boolean(props.instructor.signed_up)
const showOnboardingEmailBanner = computed(() => welcomeEmailPending.value || !hasSignedUp)
const welcomeEmailLastSentLabel = computed(() =>
    welcomeEmailLastSentAt.value
        ? new Date(welcomeEmailLastSentAt.value).toLocaleString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : null,
)

const resendInvite = async () => {
    if (isResendingInvite.value) {
        return
    }

    isResendingInvite.value = true

    try {
        const { data } = await axios.post(resendWelcomeEmail.url(props.instructor.id))
        welcomeEmailPending.value = Boolean(data?.welcome_email_pending)
        welcomeEmailLastSentAt.value = data?.welcome_email_last_sent_at ?? welcomeEmailLastSentAt.value
        toast({ title: data?.message ?? 'Onboarding email resent.' })
    } catch (error: any) {
        const message = error?.response?.data?.message ?? 'Failed to resend the welcome email.'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isResendingInvite.value = false
    }
}

const pendingDeletion = computed(() => props.instructor.pending_deletion ?? null)
const upcomingLessonsCount = computed(() => props.instructor.upcoming_lessons_count ?? 0)
const pendingDeletionDate = computed(() =>
    pendingDeletion.value
        ? new Date(pendingDeletion.value.scheduled_for).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '',
)
const isCancellingDeletion = ref(false)

const cancelScheduledDeletion = () => {
    if (isCancellingDeletion.value) {
        return
    }

    isCancellingDeletion.value = true

    router.post(cancelDeletion.url(props.instructor.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash as { success?: string | null; error?: string | null } | undefined
            if (flash?.error) {
                toast({ title: flash.error, variant: 'destructive' })
                return
            }
            toast({ title: flash?.success ?? 'Deletion cancelled.' })
        },
        onError: () => {
            toast({ title: 'Failed to cancel the deletion. Please try again.', variant: 'destructive' })
        },
        onFinish: () => {
            isCancellingDeletion.value = false
        },
    })
}

const APP_ONBOARDING_TOTAL_STEPS = 5
const isCompletingAppOnboarding = ref(false)
const appOnboardingComplete = ref(Boolean(props.instructor.app_onboarding_complete))
const appOnboardingStep = ref(props.instructor.app_onboarding_step ?? 0)

const completeAppOnboarding = async () => {
    if (isCompletingAppOnboarding.value) {
        return
    }

    isCompletingAppOnboarding.value = true

    try {
        const { data } = await axios.post(forceCompleteAppOnboarding.url(props.instructor.id))
        appOnboardingComplete.value = Boolean(data?.app_onboarding_complete)
        appOnboardingStep.value = data?.app_onboarding_step ?? APP_ONBOARDING_TOTAL_STEPS
        toast({ title: data?.message ?? 'App onboarding marked as complete.' })
    } catch (error: any) {
        const message = error?.response?.data?.message ?? 'Failed to mark app onboarding as complete.'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isCompletingAppOnboarding.value = false
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

            <!-- Scheduled deletion banner -->
            <Alert v-if="pendingDeletion" variant="destructive">
                <Trash2 class="h-4 w-4" />
                <AlertTitle>Scheduled for deletion on {{ pendingDeletionDate }}</AlertTitle>
                <AlertDescription>
                    <p v-if="pendingDeletion.staff_requested">
                        This instructor is suspended and can't sign in. On {{ pendingDeletionDate }} their personal
                        details are removed and their pupils unassigned.
                    </p>
                    <p v-else>
                        The instructor asked to delete their account in the app. They can still sign in and
                        cancel it there until {{ pendingDeletionDate }}.
                    </p>
                    <p v-if="upcomingLessonsCount > 0" class="font-medium">
                        {{ upcomingLessonsCount }} upcoming {{ upcomingLessonsCount === 1 ? 'lesson is' : 'lessons are' }}
                        still booked. Move or cancel them before then.
                    </p>
                    <Button
                        v-if="isOwner && pendingDeletion.staff_requested"
                        variant="outline"
                        size="sm"
                        class="mt-3 min-w-[160px]"
                        :disabled="isCancellingDeletion"
                        @click="cancelScheduledDeletion"
                    >
                        <Loader2 v-if="isCancellingDeletion" class="mr-2 h-4 w-4 animate-spin" />
                        <Undo2 v-else class="mr-2 h-4 w-4" />
                        Cancel deletion
                    </Button>
                </AlertDescription>
            </Alert>

            <!-- Onboarding email banner (failed send or not signed up yet) — owners only -->
            <div
                v-if="isOwner && showOnboardingEmailBanner"
                class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <MailWarning class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div class="text-sm">
                        <template v-if="welcomeEmailPending">
                            <p class="font-medium">Welcome email hasn't been delivered yet</p>
                            <p class="text-amber-800">
                                We couldn't confirm the password-setup email was sent to
                                <strong>{{ instructor.email }}</strong>. Resend it so they can
                                access their account.
                            </p>
                        </template>
                        <template v-else>
                            <p class="font-medium">Instructor hasn't signed up yet</p>
                            <p class="text-amber-800">
                                {{ instructor.name }} hasn't set their password from the onboarding email sent to
                                <strong>{{ instructor.email }}</strong>. Resend it with a fresh setup link.
                            </p>
                        </template>
                        <p v-if="welcomeEmailLastSentLabel" class="mt-1 text-xs text-amber-700">
                            Last sent {{ welcomeEmailLastSentLabel }}
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
                    {{ isResendingInvite ? 'Resending…' : 'Resend onboarding email' }}
                </Button>
            </div>

            <!-- App onboarding incomplete banner — owners only -->
            <div
                v-if="isOwner && !appOnboardingComplete"
                class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <SkipForward class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div class="text-sm">
                        <p class="font-medium">App onboarding not finished</p>
                        <p class="text-amber-800">
                            {{ instructor.name }} has completed {{ appOnboardingStep }} of
                            {{ APP_ONBOARDING_TOTAL_STEPS }} onboarding steps in the app. If they're stuck,
                            you can push them past it.
                        </p>
                    </div>
                </div>
                <Button
                    variant="outline"
                    class="border-amber-300 bg-white text-amber-900 hover:bg-amber-100"
                    :disabled="isCompletingAppOnboarding"
                    @click="completeAppOnboarding"
                >
                    <Loader2 v-if="isCompletingAppOnboarding" class="mr-2 h-4 w-4 animate-spin" />
                    {{ isCompletingAppOnboarding ? 'Saving…' : 'Mark onboarding complete' }}
                </Button>
            </div>

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

        <!-- Edit Instructor Sheet -->
        <AddInstructorSheet
            v-model:open="isEditSheetOpen"
            :instructor="instructor"
            :form-options="formOptions"
            @instructor-updated="isEditSheetOpen = false"
        />
    </AppLayout>
</template>
