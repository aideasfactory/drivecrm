<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <OnboardingHeader :current-step="6" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1">
          <OnboardingLeftSidebar>
            <template #extra-content>
              <div class="border-t pt-6">
                <h4 class="font-semibold text-gray-900 mb-3">Booking Summary</h4>
                <div class="space-y-3 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Package:</span>
                    <span class="font-medium">{{ package?.name || 'No package selected' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Lessons:</span>
                    <span class="font-medium">{{ package?.lessons_count || '0' }} lessons</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Instructor:</span>
                    <span class="font-medium">{{ instructor?.name || 'Not selected' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Start Date:</span>
                    <span class="font-medium">{{ formatDate(schedule?.date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Time:</span>
                    <span class="font-medium">{{ formatTime(schedule?.start_time) }}</span>
                  </div>
                  <div class="border-t pt-3 flex justify-between font-semibold">
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
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
              <h1 class="text-2xl font-bold text-gray-900 mb-2">Complete your booking</h1>
              <p class="text-gray-600">Choose your preferred payment method to secure your driving lesson booking.</p>
            </div>

            <form @submit.prevent="processPayment">
              <div class="space-y-8">
                <!-- Payment Options -->
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Options</h3>

                  <div class="space-y-4">
                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer"
                           :class="form.payment_mode === 'upfront' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-600 hover:bg-blue-50'">
                      <input type="radio" v-model="form.payment_mode" value="upfront" class="sr-only">
                      <div class="flex-1">
                        <div class="flex items-center justify-between">
                          <div>
                            <div class="font-medium text-gray-900">Pay in full</div>
                            <div class="text-sm text-gray-600">Complete payment now via Stripe</div>
                          </div>
                          <div class="text-xl font-bold text-gray-900">{{ package?.total_price || '0.00' }}</div>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="w-5 h-5 border-2 rounded-full flex items-center justify-center"
                             :class="form.payment_mode === 'upfront' ? 'border-blue-600' : 'border-gray-300'">
                          <div v-if="form.payment_mode === 'upfront'" class="w-2.5 h-2.5 bg-blue-600 rounded-full"></div>
                        </div>
                      </div>
                    </label>

                    <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer"
                           :class="form.payment_mode === 'weekly' ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-600 hover:bg-blue-50'">
                      <input type="radio" v-model="form.payment_mode" value="weekly" class="sr-only">
                      <div class="flex-1">
                        <div class="flex items-center justify-between">
                          <div>
                            <div class="font-medium text-gray-900">Pay weekly</div>
                            <div class="text-sm text-gray-600">{{ package?.lessons_count || 0 }} weekly invoices</div>
                          </div>
                          <div class="text-xl font-bold text-gray-900">
                            {{ package?.weekly_payment || '0.00' }}<span class="text-sm font-normal text-gray-600">/lesson</span>
                          </div>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="w-5 h-5 border-2 rounded-full flex items-center justify-center"
                             :class="form.payment_mode === 'weekly' ? 'border-blue-600' : 'border-gray-300'">
                          <div v-if="form.payment_mode === 'weekly'" class="w-2.5 h-2.5 bg-blue-600 rounded-full"></div>
                        </div>
                      </div>
                    </label>
                  </div>

                  <!-- Weekly Schedule Info -->
                  <div v-if="form.payment_mode === 'weekly'" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="flex items-start">
                      <i class="fa-solid fa-calendar-alt text-amber-600 mt-0.5 mr-3"></i>
                      <div class="text-sm text-amber-800">
                        <p class="font-medium mb-2">Weekly Payment Schedule</p>
                        <p class="mb-2">You will receive {{ package?.lessons_count || 0 }} invoices via email, one for each lesson 24 hours before it's scheduled.</p>
                        <p class="text-xs">First lesson: {{ formatDate(schedule?.date) }}</p>
                        <p class="text-xs">Payment per lesson: {{ pricing?.weekly?.per_lesson || '0.00' }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Secure Payment Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <div class="flex items-start">
                    <i class="fa-solid fa-shield-halved text-blue-600 mt-0.5 mr-3"></i>
                    <div class="text-sm text-blue-800">
                      <p class="font-medium mb-1">Secure Payment via Stripe</p>
                      <p v-if="form.payment_mode === 'upfront'">
                        You'll be redirected to Stripe's secure checkout page to complete your payment.
                        We accept all major credit and debit cards, Apple Pay, and Google Pay.
                      </p>
                      <p v-else>
                        Your order will be activated immediately. You'll receive invoice emails 24 hours before each lesson.
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Terms -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                  <label class="flex items-start cursor-pointer">
                    <input v-model="form.terms_accepted" type="checkbox"
                           class="mt-1 mr-3 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <div class="text-sm text-gray-700">
                      I agree to the <span class="text-blue-600 hover:underline cursor-pointer">Terms & Conditions</span>
                      and <span class="text-blue-600 hover:underline cursor-pointer">Privacy Policy</span>.
                      I understand the cancellation policy and payment terms.
                    </div>
                  </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-6 border-t">
                  <Link :href="step5({ uuid: uuid }).url"
                        class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Back
                  </Link>

                  <button type="submit" :disabled="!form.terms_accepted || form.processing"
                          class="px-8 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                    <span v-if="form.processing">
                      <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                      Processing...
                    </span>
                    <span v-else>
                      <i class="fa-solid fa-lock mr-2"></i>
                      {{ paymentButtonText }}
                    </span>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
          <div class="text-sm text-gray-600">
            © 2024 DRIVE Academy
          </div>

          <div class="flex items-center space-x-6">
            <span class="text-sm text-gray-600 hover:text-gray-900 cursor-pointer">Terms & Conditions</span>
            <span class="text-sm text-gray-600 hover:text-gray-900 cursor-pointer">Privacy Policy</span>
            <span class="text-sm text-gray-600 hover:text-gray-900 cursor-pointer">Cookies</span>
          </div>

          <div class="flex items-center space-x-2">
            <i class="fa-brands fa-cc-visa text-2xl text-gray-400"></i>
            <i class="fa-brands fa-cc-mastercard text-2xl text-gray-400"></i>
            <i class="fa-brands fa-cc-amex text-2xl text-gray-400"></i>
            <i class="fa-brands fa-apple-pay text-2xl text-gray-400"></i>
            <i class="fa-brands fa-google-pay text-2xl text-gray-400"></i>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import { step5 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step6'

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
