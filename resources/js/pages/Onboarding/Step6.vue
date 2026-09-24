<template>
  <div class="min-h-screen flex flex-col">
    <OnboardingHeader :current-step="6" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1 order-2 lg:order-1">
          <OnboardingLeftSidebar>
            <template #extra-content>
              <Separator class="my-6" />
              <div>
                <h4 class="font-semibold mb-3">Booking Summary</h4>
                <div class="space-y-3 text-sm">
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Package:</span>
                    <span class="font-medium">{{ package?.name || 'No package selected' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Lessons:</span>
                    <span class="font-medium">{{ package?.lessons_count || '0' }} lessons</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Instructor:</span>
                    <span class="font-medium">{{ instructor?.name || 'Not selected' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Start Date:</span>
                    <span class="font-medium">{{ formatDate(schedule?.date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Time:</span>
                    <span class="font-medium">{{ formatTime(schedule?.start_time) }}</span>
                  </div>
                  <div v-if="selectedGuarantee?.included" class="flex justify-between">
                    <span class="text-muted-foreground">Pass Guarantee:</span>
                    <span class="font-medium">{{ selectedGuarantee.is_free ? 'Free' : `£${testPassGuarantee.price}` }}</span>
                  </div>
                  <Separator class="my-3" />
                  <div class="flex justify-between font-semibold">
                    <span>Total:</span>
                    <span>{{ form.payment_mode === 'weekly' ? weeklyTotal : upfrontTotal }}</span>
                  </div>
                </div>
              </div>
            </template>
          </OnboardingLeftSidebar>
        </div>

        <!-- Payment Form -->
        <div class="lg:col-span-2 order-1 lg:order-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-2xl">Complete your booking</CardTitle>
              <CardDescription>
                Choose your preferred payment method to secure your driving lesson booking.
              </CardDescription>
              <Badge v-if="discount" variant="destructive" class="w-fit text-sm mt-2">
                {{ discount.percentage }}% off applied &mdash; {{ discount.label }}
              </Badge>
            </CardHeader>

            <CardContent>
              <!-- Flash error message -->
              <Alert v-if="page.props.flash?.error" variant="destructive" class="mb-6">
                <AlertTitle>Error</AlertTitle>
                <AlertDescription>{{ page.props.flash.error }}</AlertDescription>
              </Alert>

              <Alert v-if="staffBooking" class="mb-6 border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100 [&>svg]:text-green-600">
                <UserCog class="h-4 w-4" />
                <AlertTitle>Booking on behalf of a student</AlertTitle>
                <AlertDescription class="text-green-800 dark:text-green-200">
                  You won't be taken to Stripe. The lessons will be booked and the payment
                  {{ form.payment_mode === 'upfront' ? 'link' : 'invoices' }} will be emailed to
                  <span class="font-medium text-green-900 dark:text-green-100">{{ staffBooking.recipient_email || 'the student' }}</span>.
                </AlertDescription>
              </Alert>

              <form @submit.prevent="processPayment">
                <div class="space-y-8">
                  <!-- Payment Options -->
                  <div>
                    <h3 class="text-lg font-semibold mb-4">Payment Options</h3>

                    <div class="space-y-4">
                      <label
                        class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                        :class="form.payment_mode === 'upfront' ? 'border-primary bg-primary/5' : 'hover:border-primary hover:bg-primary/5'"
                      >
                        <input type="radio" v-model="form.payment_mode" value="upfront" class="sr-only">
                        <div class="flex-1">
                          <div class="flex items-center justify-between">
                            <div>
                              <div class="font-medium">Pay in full</div>
                              <div class="text-sm text-muted-foreground">{{ staffBooking ? 'Stripe payment link emailed to the student' : 'Complete payment now via Stripe' }}</div>
                            </div>
                            <div class="text-xl font-bold">{{ upfrontTotal }}</div>
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="w-5 h-5 border-2 rounded-full flex items-center justify-center"
                               :class="form.payment_mode === 'upfront' ? 'border-primary' : ''">
                            <div v-if="form.payment_mode === 'upfront'" class="w-2.5 h-2.5 bg-primary rounded-full"></div>
                          </div>
                        </div>
                      </label>

                      <label
                        class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                        :class="form.payment_mode === 'weekly' ? 'border-primary bg-primary/5' : 'hover:border-primary hover:bg-primary/5'"
                      >
                        <input type="radio" v-model="form.payment_mode" value="weekly" class="sr-only">
                        <div class="flex-1">
                          <div class="flex items-center justify-between">
                            <div>
                              <div class="font-medium">Pay weekly</div>
                              <div class="text-sm text-muted-foreground">{{ package?.lessons_count || 0 }} weekly invoices</div>
                            </div>
                            <div class="text-xl font-bold">
                              {{ package?.weekly_payment || '0.00' }}<span class="text-sm font-normal text-muted-foreground">/lesson</span>
                            </div>
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="w-5 h-5 border-2 rounded-full flex items-center justify-center"
                               :class="form.payment_mode === 'weekly' ? 'border-primary' : ''">
                            <div v-if="form.payment_mode === 'weekly'" class="w-2.5 h-2.5 bg-primary rounded-full"></div>
                          </div>
                        </div>
                      </label>
                    </div>

                    <!-- Weekly Schedule Info -->
                    <Alert v-if="form.payment_mode === 'weekly'" class="mt-4" variant="default">
                      <Calendar class="h-4 w-4" />
                      <AlertTitle>Weekly Payment Schedule</AlertTitle>
                      <AlertDescription>
                        <p class="mb-2">You will receive {{ package?.lessons_count || 0 }} invoices via email, one for each lesson 24 hours before it's scheduled.</p>
                        <p class="text-xs">First lesson: {{ formatDate(schedule?.date) }}</p>
                        <p class="text-xs">Payment per lesson: {{ pricing?.weekly?.per_lesson || '0.00' }}</p>
                        <p v-if="guaranteeFor('weekly').included" class="text-xs">
                          First payment (includes £{{ testPassGuarantee.price }} Pass Your Test Guarantee): {{ weeklyFirstPayment }}
                        </p>
                      </AlertDescription>
                    </Alert>
                  </div>

                  <!-- Pass Your Test Guarantee -->
                  <Card
                    v-if="testPassGuarantee"
                    class="transition-colors"
                    :class="selectedGuarantee.is_free ? 'border-green-500 bg-green-50 dark:border-green-600 dark:bg-green-950/30' : ''"
                  >
                    <CardHeader>
                      <div class="flex items-center justify-between gap-4">
                        <CardTitle class="flex items-center gap-2 text-lg">
                          <ShieldCheck
                            class="h-5 w-5"
                            :class="selectedGuarantee.is_free ? 'text-green-600 dark:text-green-500' : 'text-primary'"
                          />
                          Pass Your Test Guarantee
                        </CardTitle>
                        <Badge v-if="selectedGuarantee.is_free" class="bg-green-600 text-white hover:bg-green-600">Included free</Badge>
                        <Badge v-else variant="secondary">£{{ testPassGuarantee.price }}</Badge>
                      </div>
                      <CardDescription>
                        <template v-if="selectedGuarantee.is_free">
                          Included free because you're paying in full for {{ formatHours(testPassGuarantee.booked_hours) }} hours of lessons.
                        </template>
                        <template v-else-if="testPassGuarantee.free_when_paid_in_full">
                          Pay in full and it's included free, or add it to your weekly payments for £{{ testPassGuarantee.price }}.
                        </template>
                        <template v-else>
                          Add the guarantee to your booking for £{{ testPassGuarantee.price }}. It's included free on
                          bookings of {{ formatHours(testPassGuarantee.free_minimum_hours) }}+ hours paid in full.
                        </template>
                        <a
                          :href="testPassGuarantee.terms_url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="font-medium text-foreground underline underline-offset-4 hover:text-primary"
                        >Terms apply</a>.
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div class="flex items-start gap-3">
                        <Checkbox
                          id="test-pass-guarantee"
                          v-model="guaranteeChecked"
                          :disabled="selectedGuarantee.is_free"
                          class="mt-0.5 cursor-pointer"
                          :class="selectedGuarantee.is_free ? 'disabled:opacity-100 data-[state=checked]:bg-green-600 data-[state=checked]:border-green-600' : ''"
                        />
                        <label
                          for="test-pass-guarantee"
                          class="text-sm font-medium leading-snug"
                          :class="selectedGuarantee.is_free ? 'cursor-not-allowed' : 'cursor-pointer'"
                        >
                          <template v-if="selectedGuarantee.is_free">
                            Pass Your Test Guarantee included (free)
                          </template>
                          <template v-else>
                            Include Pass Your Test Guarantee (+£{{ testPassGuarantee.price }})
                          </template>
                          <span v-if="!selectedGuarantee.is_free && form.payment_mode === 'weekly'" class="font-normal text-muted-foreground">
                            — added to your first weekly payment
                          </span>
                        </label>
                      </div>
                      <p v-if="form.errors.test_pass_guarantee" class="text-sm text-destructive mt-2">
                        {{ form.errors.test_pass_guarantee }}
                      </p>
                    </CardContent>
                  </Card>

                  <!-- Secure Payment Info -->
                  <Alert>
                    <ShieldCheck class="h-4 w-4" />
                    <AlertTitle>Secure Payment via Stripe</AlertTitle>
                    <AlertDescription>
                      <p v-if="form.payment_mode === 'upfront' && staffBooking">
                        The student will receive a secure Stripe payment link by email. The lessons are
                        held as pending and confirmed as soon as they pay.
                      </p>
                      <p v-else-if="form.payment_mode === 'upfront'">
                        You'll be redirected to Stripe's secure checkout page to complete your payment.
                        We accept all major credit and debit cards, Apple Pay, and Google Pay.
                      </p>
                      <p v-else>
                        Your order will be activated immediately. You'll receive invoice emails 24 hours before each lesson.
                      </p>
                    </AlertDescription>
                  </Alert>

                  <!-- Terms -->
                  <Card>
                    <CardContent class="pt-0">
                      <div class="flex items-start gap-3">
                        <Checkbox
                          id="terms"
                          v-model="termsAccepted"
                          class="mt-0.5 cursor-pointer"
                        />
                        <label for="terms" class="cursor-pointer text-sm leading-relaxed text-muted-foreground">
                          I agree to the
                          <a href="/terms-of-service" target="_blank" rel="noopener noreferrer" class="font-medium text-foreground underline underline-offset-4 hover:text-primary">Terms of Service</a>,
                          <a href="/privacy-policy" target="_blank" rel="noopener noreferrer" class="font-medium text-foreground underline underline-offset-4 hover:text-primary">Privacy Policy</a>, and
                          <a href="/cookie-policy" target="_blank" rel="noopener noreferrer" class="font-medium text-foreground underline underline-offset-4 hover:text-primary">Cookie Policy</a>. I understand the cancellation policy and payment terms.
                        </label>
                      </div>
                    </CardContent>
                  </Card>

                  <!-- Actions -->
                  <div class="flex items-center justify-between pt-6 border-t">
                    <Link :href="step5({ uuid: uuid }).url">
                      <Button variant="outline" class="cursor-pointer">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back
                      </Button>
                    </Link>

                    <Button type="submit" :disabled="!termsAccepted || form.processing" class="cursor-pointer">
                      <Spinner v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                      <Lock v-if="!form.processing" class="mr-2 h-4 w-4" />
                      {{ paymentButtonText }}
                    </Button>
                  </div>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <OnboardingFooter margin-class="mt-auto" />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Spinner } from '@/components/ui/spinner'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import { step5 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step6'
import { ArrowLeft, Lock, Calendar, ShieldCheck, UserCog } from 'lucide-vue-next'

const props = defineProps({
  uuid: String,
  currentStep: { type: Number, default: 6 },
  totalSteps: { type: Number, default: 6 },
  instructor: Object,
  package: Object,
  schedule: Object,
  pricing: Object,
  stepData: Object,
  maxStepReached: { type: Number, default: 6 },
  discount: {
    type: [Object, null],
    default: null
  },
  testPassGuarantee: {
    type: [Object, null],
    default: null
  },
  staffBooking: {
    type: [Object, null],
    default: null
  }
})

const page = usePage()

const form = useForm({
  payment_mode: 'upfront',  // 'upfront' or 'weekly'
  terms_accepted: false,
  test_pass_guarantee: props.testPassGuarantee?.opted_in ?? false
})

// Local ref for checkbox to handle reactivity
const termsAccepted = ref(form.terms_accepted)

// Watch and sync the local ref with the form
watch(termsAccepted, (newValue) => {
  form.terms_accepted = newValue
})

const includeTestPassGuarantee = ref(form.test_pass_guarantee)

watch(includeTestPassGuarantee, (newValue) => {
  form.test_pass_guarantee = newValue
})

const uuid = computed(() => props.uuid || page.props.enquiry?.id)

// Mirrors TestPassGuarantee::resolve() — free when paid in full on a large
// enough booking, otherwise charged only if the learner opts in.
function guaranteeFor(paymentMode) {
  const guarantee = props.testPassGuarantee
  if (!guarantee) {
    return { included: false, charge_pence: 0, is_free: false }
  }

  if (paymentMode === 'upfront' && guarantee.free_when_paid_in_full) {
    return { included: true, charge_pence: 0, is_free: true }
  }

  return includeTestPassGuarantee.value
    ? { included: true, charge_pence: guarantee.price_pence, is_free: false }
    : { included: false, charge_pence: 0, is_free: false }
}

const selectedGuarantee = computed(() => guaranteeFor(form.payment_mode))

// Locked on when it's free; otherwise reflects the learner's own choice, which
// is kept so switching back to weekly restores it.
const guaranteeChecked = computed({
  get: () => selectedGuarantee.value.is_free || includeTestPassGuarantee.value,
  set: (value) => {
    if (!selectedGuarantee.value.is_free) {
      includeTestPassGuarantee.value = value
    }
  }
})

function formatPence(pence) {
  return `£${((pence || 0) / 100).toFixed(2)}`
}

function formatHours(hours) {
  return Number.isInteger(hours) ? hours : Number(hours).toFixed(1)
}

const upfrontTotal = computed(() =>
  formatPence((props.pricing?.package_total_with_fees_pence ?? 0) + guaranteeFor('upfront').charge_pence)
)

const weeklyTotal = computed(() =>
  formatPence((props.pricing?.package_total_with_fees_pence ?? 0) + guaranteeFor('weekly').charge_pence)
)

const weeklyFirstPayment = computed(() =>
  formatPence((props.pricing?.weekly_payment_pence ?? 0) + guaranteeFor('weekly').charge_pence)
)

const paymentButtonText = computed(() => {
  if (form.payment_mode === 'weekly') {
    return 'Confirm Booking (Weekly Payments)'
  }
  if (props.staffBooking) {
    return `Book & Email Payment Link - ${upfrontTotal.value}`
  }
  return `Proceed to Payment - ${upfrontTotal.value}`
})

function formatDate(dateString) {
  if (!dateString) return 'Not selected'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatTime(timeString) {
  if (!timeString) return 'Not selected'
  const [hour, minute] = timeString.split(':')
  const h = parseInt(hour)
  const ampm = h >= 12 ? 'PM' : 'AM'
  const displayHour = h > 12 ? h - 12 : (h === 0 ? 12 : h)
  return `${displayHour}:${minute} ${ampm}`
}

function processPayment() {
  console.log('=== STEP 6: Processing Payment ===')
  console.log('Form data:', {
    payment_mode: form.payment_mode,
    terms_accepted: form.terms_accepted,
    test_pass_guarantee: form.test_pass_guarantee
  })
  console.log('UUID:', uuid.value)
  console.log('Route:', store({ uuid: uuid.value }).url)
  console.log('Package:', props.package)
  console.log('Schedule:', props.schedule)
  console.log('Pricing:', props.pricing)

  // Submit to backend - will redirect to Stripe or success page
  form.post(store({ uuid: uuid.value }).url, {
    onBefore: () => {
      console.log('=== Form submission starting ===')
    },
    onSuccess: (response) => {
      console.log('=== Form submission successful ===', response)
    },
    onError: (errors) => {
      console.error('=== Form submission errors ===', errors)
    },
    onFinish: () => {
      console.log('=== Form submission finished ===')
    }
  })
}
</script>
