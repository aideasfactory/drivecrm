<template>
  <div class="min-h-screen flex flex-col">
    <OnboardingHeader :current-step="6" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1">
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
                  <Separator class="my-3" />
                  <div class="flex justify-between font-semibold">
                    <span>Total:</span>
                    <span>£{{ pricing?.upfront?.total || '0.00' }}</span>
                  </div>
                </div>
              </div>
            </template>
          </OnboardingLeftSidebar>
        </div>

        <!-- Payment Form -->
        <div class="lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-2xl">Complete your booking</CardTitle>
              <CardDescription>
                Choose your preferred payment method to secure your driving lesson booking.
              </CardDescription>
            </CardHeader>

            <CardContent>
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
                              <div class="text-sm text-muted-foreground">Complete payment now via Stripe</div>
                            </div>
                            <div class="text-xl font-bold">{{ package?.total_price || '0.00' }}</div>
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
                      <i class="fa-solid fa-calendar-alt"></i>
                      <AlertTitle>Weekly Payment Schedule</AlertTitle>
                      <AlertDescription>
                        <p class="mb-2">You will receive {{ package?.lessons_count || 0 }} invoices via email, one for each lesson 24 hours before it's scheduled.</p>
                        <p class="text-xs">First lesson: {{ formatDate(schedule?.date) }}</p>
                        <p class="text-xs">Payment per lesson: {{ pricing?.weekly?.per_lesson || '0.00' }}</p>
                      </AlertDescription>
                    </Alert>
                  </div>

                  <!-- Secure Payment Info -->
                  <Alert>
                    <i class="fa-solid fa-shield-halved"></i>
                    <AlertTitle>Secure Payment via Stripe</AlertTitle>
                    <AlertDescription>
                      <p v-if="form.payment_mode === 'upfront'">
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
                    <CardContent class="pt-6">
                      <div class="flex items-start space-x-3">
                        <Checkbox
                          v-model:checked="form.terms_accepted"
                          id="terms"
                        />
                        <Label for="terms" class="cursor-pointer">
                          I agree to the <span class="text-primary hover:underline cursor-pointer">Terms & Conditions</span>
                          and <span class="text-primary hover:underline cursor-pointer">Privacy Policy</span>.
                          I understand the cancellation policy and payment terms.
                        </Label>
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

                    <Button type="submit" :disabled="!form.terms_accepted || form.processing" class="cursor-pointer">
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
    <footer class="mt-auto">
      <Card class="rounded-none border-x-0 border-b-0">
        <CardContent class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm">
              © 2024 DRIVE Academy
            </div>

            <div class="flex items-center space-x-6">
              <span class="text-sm cursor-pointer hover:underline">Terms & Conditions</span>
              <span class="text-sm cursor-pointer hover:underline">Privacy Policy</span>
              <span class="text-sm cursor-pointer hover:underline">Cookies</span>
            </div>

            <div class="flex items-center space-x-2">
              <i class="fa-brands fa-cc-visa text-2xl"></i>
              <i class="fa-brands fa-cc-mastercard text-2xl"></i>
              <i class="fa-brands fa-cc-amex text-2xl"></i>
              <i class="fa-brands fa-apple-pay text-2xl"></i>
              <i class="fa-brands fa-google-pay text-2xl"></i>
            </div>
          </div>
        </CardContent>
      </Card>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { Spinner } from '@/components/ui/spinner'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import { step5 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step6'
import { ArrowLeft, Lock } from 'lucide-vue-next'

const props = defineProps({
  uuid: String,
  currentStep: { type: Number, default: 6 },
  totalSteps: { type: Number, default: 6 },
  instructor: Object,
  package: Object,
  schedule: Object,
  pricing: Object,
  stepData: Object,
  maxStepReached: { type: Number, default: 6 }
})

const page = usePage()

const form = useForm({
  payment_mode: 'upfront',  // 'upfront' or 'weekly'
  terms_accepted: false
})

const uuid = computed(() => props.uuid || page.props.enquiry?.id)

const paymentButtonText = computed(() => {
  if (form.payment_mode === 'weekly') {
    return 'Confirm Booking (Weekly Payments)'
  }
  return `Proceed to Payment - ${props.pricing?.upfront?.total || '0.00'}`
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
    terms_accepted: form.terms_accepted
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
