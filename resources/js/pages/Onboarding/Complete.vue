<template>
  <div v-if="staffBooking" class="min-h-screen flex flex-col">
    <OnboardingHeader :current-step="6" :total-steps="6" :max-step-reached="6" />

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4 bg-primary/10">
          <CheckCircle class="h-12 w-12 text-primary" />
        </div>
        <h1 class="text-3xl font-bold mb-2">Lessons Booked</h1>
        <p class="text-lg text-muted-foreground">The booking has been created on the student's behalf.</p>
      </div>

      <Card class="mb-6">
        <CardContent class="p-8 space-y-6">
          <Alert variant="default">
            <Mail class="h-4 w-4" />
            <AlertTitle>Payment link emailed</AlertTitle>
            <AlertDescription>
              A Stripe payment link for
              {{ staffBooking.payment_mode === 'weekly' ? 'the first week' : 'the full amount' }} has been sent to
              <span class="font-medium text-foreground">{{ staffBooking.payment_link_sent_to }}</span>.
              The lessons are held until
              {{ staffBooking.hold_deadline || 'midnight' }} and released if unpaid. Once paid, the student
              receives their booking confirmation automatically<template v-if="staffBooking.payment_mode === 'weekly'">,
              and later weeks are invoiced by email</template>.
            </AlertDescription>
          </Alert>

          <p class="text-sm text-muted-foreground">You can close this tab and return to the admin area.</p>
        </CardContent>
      </Card>
    </main>

    <OnboardingFooter margin-class="mt-auto" :links="['Terms & Conditions', 'Privacy Policy', 'Contact Us']" />
  </div>

  <div v-else class="dark min-h-screen flex flex-col bg-background text-foreground">
    <Head title="You're booked" />

    <header class="border-b">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <span class="flex items-center">
          <AppLogoIcon class="h-8 w-8" />
          <span class="text-xl font-bold ml-2">DRIVE</span>
        </span>
        <div class="flex items-center gap-6 text-sm">
          <a v-if="schoolContact.phone" :href="phoneHref" class="flex items-center gap-2 hover:underline">
            <Phone class="h-4 w-4" />
            <span>{{ schoolContact.phone }}</span>
          </a>
          <a v-if="schoolContact.email" :href="`mailto:${schoolContact.email}`" class="hidden sm:flex items-center gap-2 hover:underline">
            <Mail class="h-4 w-4" />
            <span>{{ schoolContact.email }}</span>
          </a>
        </div>
      </div>
    </header>

    <main class="max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-1 space-y-4">
      <section class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-5 bg-primary/15">
          <CircleCheck class="h-9 w-9 text-primary" />
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold mb-3">{{ heading }}</h1>
        <p class="text-muted-foreground max-w-2xl mx-auto">{{ subheading }}</p>
      </section>

      <Card>
        <CardContent class="p-6 grid gap-8 lg:grid-cols-[1fr_auto_320px]">
          <div class="flex flex-col sm:flex-row gap-8 items-center sm:items-start">
            <div class="hidden sm:block shrink-0" aria-hidden="true">
              <div class="w-44 h-80 rounded-[2rem] border-4 border-muted bg-background p-3 shadow-xl -rotate-3">
                <div class="mx-auto mb-4 h-4 w-16 rounded-full bg-muted" />
                <div class="flex flex-col items-center mb-4">
                  <AppLogoIcon class="h-10 w-10" />
                  <span class="text-sm font-bold mt-1">DRIVE</span>
                </div>
                <ul class="space-y-1.5">
                  <li
                    v-for="item in phoneMenu"
                    :key="item.label"
                    class="flex items-center gap-2 rounded-md bg-muted/60 px-2 py-1.5 text-[10px]"
                  >
                    <component :is="item.icon" class="h-3 w-3 text-muted-foreground" />
                    <span class="flex-1">{{ item.label }}</span>
                    <ChevronRight class="h-3 w-3 text-muted-foreground" />
                  </li>
                </ul>
              </div>
            </div>

            <div class="space-y-4">
              <Badge>Get the DRIVE app</Badge>
              <h2 class="text-2xl font-bold">Manage your lessons, talk to your instructor and track your progress.</h2>
              <p class="text-muted-foreground">
                Everything you need is in one place — your lesson schedule, instructor details, messages and more.
              </p>

              <div v-if="hasStoreLinks" class="flex flex-wrap gap-3">
                <Button
                  v-if="appLinks.apple"
                  as="a"
                  :href="appLinks.apple"
                  target="_blank"
                  rel="noopener noreferrer"
                  variant="outline"
                  class="h-14 min-w-[180px] justify-start gap-3 px-4"
                >
                  <Apple class="size-7" />
                  <span class="flex flex-col items-start leading-tight">
                    <span class="text-[10px] font-normal">Download on the</span>
                    <span class="text-lg font-semibold">App Store</span>
                  </span>
                </Button>
                <Button
                  v-if="appLinks.android"
                  as="a"
                  :href="appLinks.android"
                  target="_blank"
                  rel="noopener noreferrer"
                  variant="outline"
                  class="h-14 min-w-[180px] justify-start gap-3 px-4"
                >
                  <Play class="size-7" />
                  <span class="flex flex-col items-start leading-tight">
                    <span class="text-[10px] font-normal uppercase">Get it on</span>
                    <span class="text-lg font-semibold">Google Play</span>
                  </span>
                </Button>
              </div>

              <p class="text-sm text-muted-foreground">
                <span class="font-semibold text-foreground">Already have the app?</span>
                Open DRIVE and sign in with the details you used when booking.
              </p>
            </div>
          </div>

          <Separator orientation="vertical" class="hidden lg:block" />
          <Separator class="lg:hidden" />

          <div v-if="booking?.instructor" class="space-y-5">
            <h2 class="text-lg font-semibold">Your instructor</h2>
            <div class="flex items-center gap-4">
              <Avatar class="size-20 border-2 border-primary/40">
                <AvatarImage v-if="booking.instructor.avatar" :src="booking.instructor.avatar" :alt="booking.instructor.name ?? 'Instructor'" />
                <AvatarFallback class="text-lg">{{ instructorInitials }}</AvatarFallback>
              </Avatar>
              <div class="space-y-1 text-sm">
                <p class="font-semibold text-base">{{ booking.instructor.name }}</p>
                <p class="text-muted-foreground">{{ booking.instructor.qualification }}</p>
                <p v-if="ratingLabel" class="flex items-center gap-1.5 text-muted-foreground">
                  <Star class="h-4 w-4 fill-primary text-primary" />
                  {{ ratingLabel }}
                </p>
              </div>
            </div>

            <dl v-if="hasBookingDetails" class="space-y-2 text-sm">
              <div v-if="booking.package_name" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Package</dt>
                <dd class="font-medium text-right">{{ booking.package_name }}</dd>
              </div>
              <div v-if="booking.lessons_count" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">Lessons</dt>
                <dd class="font-medium text-right">{{ booking.lessons_count }}</dd>
              </div>
              <div v-if="booking.first_lesson" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">First lesson</dt>
                <dd class="font-medium text-right">
                  {{ booking.first_lesson.date }}<template v-if="booking.first_lesson.start_time">, {{ booking.first_lesson.start_time }}</template>
                </dd>
              </div>
              <div v-if="payment" class="flex justify-between gap-4">
                <dt class="text-muted-foreground">{{ isWeekly ? 'Paid today (week 1)' : 'Paid today' }}</dt>
                <dd class="font-medium text-right">{{ formatPence(payment.first_payment.total_pence) }}</dd>
              </div>
              <p v-if="isWeekly && payment?.weekly_instalment" class="text-xs text-muted-foreground">
                Each following week is {{ payment.weekly_instalment }}, invoiced by email before the lesson.
              </p>
            </dl>

            <Separator />

            <p class="text-sm text-muted-foreground">
              You'll be able to chat with {{ instructorFirstName }}, view your lesson details and get in touch through the app.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="p-6">
          <h2 class="text-xl font-semibold mb-6">What you can do in the app</h2>
          <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div
              v-for="(feature, index) in appFeatures"
              :key="feature.title"
              class="space-y-2"
              :class="index > 0 ? 'lg:border-l lg:pl-6' : ''"
            >
              <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-primary-foreground">
                <component :is="feature.icon" class="h-5 w-5" />
              </div>
              <h3 class="font-semibold">{{ feature.title }}</h3>
              <p class="text-sm text-muted-foreground">{{ feature.description }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card v-if="booking?.guarantee.included">
        <CardContent class="p-5 flex flex-col sm:flex-row sm:items-center gap-4">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-primary/15">
            <ShieldCheck class="h-6 w-6 text-primary" />
          </div>
          <div class="flex-1">
            <h2 class="font-semibold">Your Pass Your Test Guarantee is included</h2>
            <p class="text-sm text-muted-foreground">
              If you don't pass first time, DRIVE pays for your next test. Conditions apply.
            </p>
          </div>
          <Button
            v-if="booking.guarantee.terms_url"
            as="a"
            :href="booking.guarantee.terms_url"
            target="_blank"
            rel="noopener noreferrer"
            variant="link"
          >
            View guarantee terms
            <ChevronRight class="h-4 w-4" />
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardContent class="p-5 flex flex-col md:flex-row md:items-center gap-4 md:gap-8">
          <div class="flex items-center gap-3">
            <CircleHelp class="h-8 w-8" />
            <h2 class="font-semibold text-lg">Need help?</h2>
          </div>
          <a v-if="schoolContact.phone" :href="phoneHref" class="flex items-center gap-2 text-sm hover:underline">
            <Phone class="h-4 w-4" />
            {{ schoolContact.phone }}
          </a>
          <a v-if="schoolContact.email" :href="`mailto:${schoolContact.email}`" class="flex items-center gap-2 text-sm hover:underline">
            <Mail class="h-4 w-4" />
            {{ schoolContact.email }}
          </a>
          <Button v-if="schoolContact.phone" as="a" :href="phoneHref" variant="outline" class="md:ml-auto">
            Contact DRIVE
          </Button>
        </CardContent>
      </Card>
    </main>

    <OnboardingFooter margin-class="mt-10" :links="['Terms & Conditions', 'Privacy Policy', 'Contact Us']" />
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import type { AppPageProps } from '@/types'
import { Head, usePage } from '@inertiajs/vue3'
import {
  Apple,
  BookOpen,
  CalendarDays,
  CheckCircle,
  ChevronRight,
  CircleCheck,
  CircleHelp,
  Mail,
  MessageSquare,
  Phone,
  Play,
  ShieldCheck,
  Star,
  TrendingUp,
  UserRound,
} from 'lucide-vue-next'
import { computed } from 'vue'

interface BookingConfirmation {
  greeting_name: string | null
  learner_first_name: string | null
  booked_by_contact: boolean
  package_name: string | null
  lessons_count: number | null
  first_lesson: { date: string; start_time: string | null } | null
  instructor: {
    name: string | null
    first_name: string | null
    avatar: string | null
    qualification: string
    rating: string | number | null
    reviews: string | number | null
  } | null
  guarantee: {
    included: boolean
    terms_url: string
  }
}

interface PaymentSummary {
  payment_mode: string
  includes_test_pass_guarantee: boolean
  first_payment: { total_pence: number }
  weekly_instalment: string | null
}

const props = withDefaults(defineProps<{
  enquiry?: Record<string, unknown>
  booking?: BookingConfirmation | null
  staffBooking?: Record<string, any> | null
  payment?: PaymentSummary | null
}>(), {
  booking: null,
  staffBooking: null,
  payment: null,
})

const page = usePage<AppPageProps>()
const appLinks = computed(() => page.props.appLinks ?? { apple: null, android: null })
const schoolContact = computed(() => page.props.schoolContact)
const hasStoreLinks = computed(() => Boolean(appLinks.value.apple || appLinks.value.android))
const phoneHref = computed(() => `tel:${(schoolContact.value?.phone ?? '').replace(/\s+/g, '')}`)

const isWeekly = computed(() => props.payment?.payment_mode === 'weekly')

const heading = computed(() => {
  const greeting = props.booking?.greeting_name
  const learner = props.booking?.learner_first_name

  if (props.booking?.booked_by_contact && learner) {
    return greeting
      ? `${learner} is booked, ${greeting}. Here's what happens next.`
      : `${learner} is booked. Here's what happens next.`
  }

  return greeting
    ? `You're booked, ${greeting}. Here's what happens next.`
    : "You're booked. Here's what happens next."
})

const subheading = computed(() => {
  const learner = props.booking?.learner_first_name

  if (props.booking?.booked_by_contact && learner) {
    return `Your payment is confirmed and everything is set. ${learner} has been matched with their instructor and can manage everything in the DRIVE app.`
  }

  return "Your payment is confirmed and you're all set. You've been matched with your instructor, and now you can manage everything in the DRIVE app."
})

const instructorFirstName = computed(() => props.booking?.instructor?.first_name || 'your instructor')

const instructorInitials = computed(() => {
  const name = props.booking?.instructor?.name ?? ''

  return name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0]?.toUpperCase())
    .slice(0, 2)
    .join('') || 'DI'
})

const ratingLabel = computed(() => {
  const instructor = props.booking?.instructor
  const rating = instructor?.rating
  if (rating === null || rating === undefined || rating === '') {
    return null
  }

  const reviewCount = Number(instructor?.reviews)
  if (!instructor?.reviews || Number.isNaN(reviewCount) || reviewCount <= 0) {
    return String(rating)
  }

  return `${rating} (${reviewCount} ${reviewCount === 1 ? 'review' : 'reviews'})`
})

const hasBookingDetails = computed(() => Boolean(
  props.booking?.package_name || props.booking?.lessons_count || props.booking?.first_lesson || props.payment
))

const phoneMenu = [
  { label: 'My Lessons', icon: CalendarDays },
  { label: 'Messages', icon: MessageSquare },
  { label: 'My Instructor', icon: UserRound },
  { label: 'Theory & Hazard', icon: BookOpen },
  { label: 'Progress', icon: TrendingUp },
]

const appFeatures = [
  { title: 'View your lessons', description: 'See your full schedule and manage upcoming sessions.', icon: CalendarDays },
  { title: 'Message your instructor', description: 'Get in touch, ask questions and stay in the loop.', icon: MessageSquare },
  { title: 'Access your instructor details', description: 'View their profile, availability and contact information.', icon: UserRound },
  { title: 'Track your progress', description: 'Keep an eye on your learning and get closer to your test.', icon: TrendingUp },
]

function formatPence(pence: number | null | undefined): string {
  return `£${((pence || 0) / 100).toFixed(2)}`
}
</script>
