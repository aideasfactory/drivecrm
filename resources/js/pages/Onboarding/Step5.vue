<template>
  <div class="bg-gray-50 min-h-screen">
    <OnboardingHeader :current-step="5" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1">
          <OnboardingLeftSidebar />
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
              <h1 class="text-3xl font-bold text-gray-900 mb-2">Review your booking</h1>
              <p class="text-lg text-gray-600">Please review all details before proceeding to payment. You can edit any section if needed.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
              <!-- Instructor Summary -->
              <div class="bg-gray-50 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-900">Your Instructor</h3>
                  <Link :href="step2({ uuid: uuid }).url" 
                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Edit
                  </Link>
                </div>
                <div class="flex items-center space-x-4">
                  <img :src="instructor?.avatar || 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-5.jpg'" 
                       :alt="instructor?.name" 
                       class="w-16 h-16 rounded-full object-cover">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-900">{{ instructor?.name || 'No instructor selected' }}</h4>
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mt-1">
                      <span class="flex items-center">
                        <i class="fa-solid fa-car mr-1"></i>
                        {{ instructor?.transmission || 'Manual' }}
                      </span>
                      <span class="flex items-center">
                        <i class="fa-solid fa-map-marker-alt mr-1"></i>
                        {{ postcode || 'Area not set' }}
                      </span>
                      <span class="flex items-center">
                        <i class="fa-solid fa-star text-yellow-400 mr-1"></i>
                        {{ instructor?.rating || '4.9' }} ({{ instructor?.reviews || '127' }} reviews)
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Package Summary -->
              <div class="bg-gray-50 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-900">Package Details</h3>
                  <Link :href="step3({ uuid: uuid }).url" 
                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Edit
                  </Link>
                </div>
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="font-semibold text-gray-900">{{ package?.name || 'No package selected' }}</h4>
                    <p class="text-sm text-gray-600">{{ package?.lessons_count || '0' }} lessons</p>
                  </div>
                  <div class="text-right">
                    <div class="text-xl font-bold text-gray-900">{{ package?.formatted_total_price || '0' }}</div>
                    <div class="text-sm text-gray-500">{{ package?.formatted_lesson_price || '0' }}/lesson</div>
                  </div>
                </div>
              </div>

              <!-- Schedule Summary -->
              <div class="bg-gray-50 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-900">Start Date & Time</h3>
                  <Link :href="step4({ uuid: uuid }).url" 
                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Edit
                  </Link>
                </div>
                <div class="flex items-center space-x-4">
                  <div class="w-12 h-12 bg-blue-600 text-white rounded-lg flex items-center justify-center font-semibold">
                    {{ selectedDay }}
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900">{{ formatDate(schedule?.date) }}</h4>
                    <p class="text-sm text-gray-600">{{ formatTimeSlot(schedule?.start_time, schedule?.end_time) }}</p>
                  </div>
                </div>
              </div>

              <!-- Pickup Location -->
              <!-- <div class="bg-gray-50 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-900">Pickup Location</h3>
                  <button type="button" @click="editingAddress = true" 
                          class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Edit
                  </button>
                </div>
                
                <div v-if="!editingAddress" class="flex items-start space-x-3">
                  <i class="fa-solid fa-map-marker-alt text-blue-600 mt-1"></i>
                  <div>
                    <p class="font-medium text-gray-900">{{ form.pickup_address_line_1 || contact?.pickup_location?.address_line_1 || 'No address provided' }}</p>
                    <p v-if="form.pickup_address_line_2 || contact?.pickup_location?.address_line_2" class="text-sm text-gray-600">{{ form.pickup_address_line_2 || contact?.pickup_location?.address_line_2 }}</p>
                    <p class="text-sm text-gray-600">{{ form.pickup_city || contact?.pickup_location?.city || 'London' }}, {{ form.pickup_postcode || contact?.pickup_location?.postcode }}</p>
                  </div>
                </div>
                
                <div v-else class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                    <input v-model="form.pickup_address_line_1" type="text" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2 (optional)</label>
                    <input v-model="form.pickup_address_line_2" type="text" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                      <input v-model="form.pickup_city" type="text" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                      <input v-model="form.pickup_postcode" type="text" 
                             class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                  </div>
                  <button type="button" @click="editingAddress = false" 
                          class="px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200">
                    Done
                  </button>
                </div>
              </div> -->

              <!-- Contact Details -->
              <div class="bg-gray-50 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-900">Contact Details</h3>
                  <Link :href="step1({ uuid: uuid }).url" 
                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Edit
                  </Link>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <p class="text-sm text-gray-500 mb-1">Name</p>
                    <p class="font-medium text-gray-900">{{ learner?.is_self ? 'You' : (learner?.first_name + ' ' + learner?.last_name) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500 mb-1">Phone</p>
                    <p class="font-medium text-gray-900">{{ contact?.phone }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500 mb-1">Email</p>
                    <p class="font-medium text-gray-900">{{ contact?.email }}</p>
                  </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                  <div class="flex items-start">
                    <input type="checkbox" v-model="form.booking_for_someone_else" 
                           id="booking-for-someone-else" 
                           class="mt-1 mr-3 h-4 w-4 text-blue-600 focus:ring-blue-600 border-gray-300 rounded">
                    <label for="booking-for-someone-else" class="text-sm font-medium text-gray-700 cursor-pointer">
                      I'm booking for someone else
                    </label>
                  </div>
                  
                  <div v-if="form.booking_for_someone_else" class="mt-4">
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                      <h4 class="font-semibold text-gray-900 mb-4">Learner Details</h4>
                      <div class="grid grid-cols-2 gap-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                          <input v-model="form.learner_first_name" type="text" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                          <input v-model="form.learner_last_name" type="text" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                          <input v-model="form.learner_phone" type="tel" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                          <input v-model="form.learner_email" type="email" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                          <input v-model="form.learner_dob" type="date" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Promo Code -->
              <div class="border-t pt-6">
                <div class="flex items-center space-x-4">
                  <input v-model="promoCode" type="text" 
                         placeholder="Enter promo code" 
                         class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent">
                  <button type="button" @click="applyPromoCode" 
                          class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                    Apply
                  </button>
                </div>
                <p v-if="promoDiscount" class="text-green-600 text-sm mt-2">
                  Promo code applied! You saved £{{ promoDiscount }}
                </p>
              </div>

              <!-- Pricing Summary -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Pricing Summary</h3>
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-gray-600">{{ package?.name }} ({{ package?.lessons_count }} lessons)</span>
                    <span class="font-medium text-gray-900">{{ package?.formatted_total_price || '0.00' }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-600">Booking fee</span>
                    <span class="font-medium text-gray-900">£{{ pricing?.booking_fee || '19.99' }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-gray-600">Digital Fee</span>
                    <span class="font-medium text-gray-900">{{ package?.digital_fee || '3.99' }}</span>
                  </div>
                  <div v-if="promoDiscount" class="flex items-center justify-between text-green-600">
                    <span>Promo discount</span>
                    <span class="font-medium">-£{{ promoDiscount }}.00</span>
                  </div>
                  <div class="border-t border-blue-200 pt-3 flex items-center justify-between">
                    <span class="text-lg font-semibold text-gray-900">Total</span>
                    <span class="text-xl font-bold text-gray-900">{{ package?.total_price || '0.00' }}</span>
                  </div>
                  <div class="text-sm text-gray-600 mt-2">
                    Or pay <span class="font-semibold text-gray-900">{{ package?.weekly_payment || '0.00' }} weekly</span>
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="flex items-center justify-between pt-6 border-t">
                <Link :href="step4({ uuid: uuid }).url" 
                      class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                  <i class="fa-solid fa-arrow-left mr-2"></i>
                  Back
                </Link>
                
                <button type="submit" 
                        :disabled="form.processing"
                        class="px-8 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed">
                  <span v-if="form.processing">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                    Processing...
                  </span>
                  <span v-else>
                    Confirm & Continue to Payment
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                  </span>
                </button>
              </div>
            </form>
          </div>

          <!-- Auto-save toast -->
          <div v-if="showToast" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg transition-opacity">
            <i class="fa-solid fa-check mr-2"></i>
            Saved
          </div>
        </div>
      </div>
    </main>

    <footer class="bg-white border-t border-gray-200 mt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <div class="text-sm text-gray-600 mb-4 md:mb-0">
            © 2024 DRIVE Academy
          </div>
          <div class="flex items-center space-x-6 mb-4 md:mb-0">
            <span class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer">Terms & Conditions</span>
            <span class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer">Privacy Policy</span>
            <span class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer">Cookies</span>
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
import { ref, computed, watch } from 'vue'
import { usePage, Link, useForm } from '@inertiajs/vue3'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import { step1, step2, step3, step4 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step5'

const props = defineProps({
  uuid: String,
  currentStep: Number,
  totalSteps: Number,
  stepData: Object,
  instructor: Object,
  package: Object,
  schedule: Object,
  contact: Object,
  learner: Object,
  pricing: Object,
  available_promos: Array,
  pickup_address_line_1: String,
  pickup_address_line_2: String,
  pickup_city: String,
  pickup_postcode: String,
  postcode: String,
  maxStepReached: { type: Number, default: 5 }
})

console.log(props.package)

const page = usePage()

const form = useForm({
  pickup_address_line_1: props.stepData?.pickup_address_line_1 || props.pickup_address_line_1 || '',
  pickup_address_line_2: props.stepData?.pickup_address_line_2 || props.pickup_address_line_2 || '',
  pickup_city: props.stepData?.pickup_city || props.pickup_city || 'London',
  pickup_postcode: props.stepData?.pickup_postcode || props.pickup_postcode || props.postcode || '',
  booking_for_someone_else: props.stepData?.booking_for_someone_else || false,
  learner_first_name: props.stepData?.learner_first_name || '',
  learner_last_name: props.stepData?.learner_last_name || '',
  learner_phone: props.stepData?.learner_phone || '',
  learner_email: props.stepData?.learner_email || '',
  learner_dob: props.stepData?.learner_dob || '',
  promo_code: props.stepData?.promo_code || ''
})

const editingAddress = ref(false)
const showToast = ref(false)
const promoCode = ref('')
const promoDiscount = ref(0)
const bookingFee = 5

const uuid = computed(() => props.uuid || page.props.enquiry?.id)

const selectedDay = computed(() => {
  if (!props.schedule?.date) return ''
  const date = new Date(props.schedule.date)
  return date.getDate()
})

 

function formatDate(dateString) {
  if (!dateString) return 'No date selected'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { 
    weekday: 'long', 
    month: 'long', 
    day: 'numeric', 
    year: 'numeric' 
  })
}

function formatTimeSlot(startTime, endTime) {
  if (!startTime || !endTime) return 'No time selected'
  
  const formatTime = (time) => {
    const [hour, minute] = time.split(':')
    const h = parseInt(hour)
    const ampm = h >= 12 ? 'PM' : 'AM'
    const displayHour = h > 12 ? h - 12 : (h === 0 ? 12 : h)
    return `${displayHour}:${minute} ${ampm}`
  }
  
  return `${formatTime(startTime)} - ${formatTime(endTime)}`
}

function applyPromoCode() {
  // Simulate promo code application
  if (promoCode.value.toLowerCase() === 'save10') {
    promoDiscount.value = 10
    showToastMessage()
  } else if (promoCode.value.toLowerCase() === 'save20') {
    promoDiscount.value = 20
    showToastMessage()
  } else {
    promoDiscount.value = 0
  }
}

function showToastMessage() {
  showToast.value = true
  setTimeout(() => {
    showToast.value = false
  }, 2000)
}

// Auto-save functionality
let saveTimeout = null
function autoSave() {
  if (!uuid.value) return
  
  clearTimeout(saveTimeout)
  saveTimeout = setTimeout(() => {
    form.post(store({ uuid: uuid.value }).url, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        showToastMessage()
      },
      onError: () => {
        // Silently handle errors for auto-save
      }
    })
  }, 1500) // 1.5 second debounce
}

// Watch for changes and auto-save
watch(() => form.booking_for_someone_else, () => {
  autoSave()
})

// Watch learner fields only if booking for someone else
watch(() => form.learner_first_name, () => {
  if (form.booking_for_someone_else) autoSave()
})

watch(() => form.learner_last_name, () => {
  if (form.booking_for_someone_else) autoSave()
})

watch(() => form.learner_email, () => {
  if (form.booking_for_someone_else) autoSave()
})

watch(() => form.learner_phone, () => {
  if (form.booking_for_someone_else) autoSave()
})

watch(() => form.learner_dob, () => {
  if (form.booking_for_someone_else) autoSave()
})

// Watch address fields
watch(() => form.pickup_address_line_1, () => autoSave())
watch(() => form.pickup_address_line_2, () => autoSave())
watch(() => form.pickup_city, () => autoSave())
watch(() => form.pickup_postcode, () => autoSave())

watch(() => form.promo_code, () => autoSave())

watch(editingAddress, () => {
  if (!editingAddress.value) {
    autoSave()
  }
})

function submit() {
  form.post(store({ uuid: uuid.value }).url)
}
</script>