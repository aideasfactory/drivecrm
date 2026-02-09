<template>
  <div class="bg-gray-50 min-h-screen">
    <OnboardingHeader :current-step="3" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-24">
            <div class="mb-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-2">DRIVE Driving School</h3>
              <div class="space-y-2 text-sm text-gray-600">
                <div class="flex items-center">
                  <i class="fa-solid fa-map-marker-alt mr-2 text-gray-400"></i>
                  <span>London & Surrounding Areas</span>
                </div>
                <div class="flex items-center">
                  <i class="fa-solid fa-phone mr-2 text-gray-400"></i>
                  <span>020 1234 5678</span>
                </div>
                <div class="flex items-center">
                  <i class="fa-solid fa-envelope mr-2 text-gray-400"></i>
                  <span>hello@DRIVE.com</span>
                </div>
              </div>
            </div>

            <div class="mb-6">
              <div class="flex items-center space-x-3 mb-4">
                <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium flex items-center">
                  <i class="fa-solid fa-shield-alt mr-1"></i>
                  DVSA Approved
                </div>
                <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium flex items-center">
                  <i class="fa-solid fa-lock mr-1"></i>
                  Secure Checkout
                </div>
              </div>
            </div>

            <div>
              <h4 class="font-semibold text-gray-900 mb-3">Why book with us?</h4>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Full refund policy - cancel up to 24 hours before</span>
                </li>
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Flexible scheduling - reschedule anytime</span>
                </li>
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Qualified DVSA approved instructors</span>
                </li>
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Modern dual-control vehicles</span>
                </li>
              </ul>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
              <h4 class="font-semibold text-gray-900 mb-3">Your selection</h4>
              <div class="space-y-2 text-sm">
                <!-- <div class="flex justify-between">
                  <span class="text-gray-600">Learner:</span>
                  <span class="font-medium">{{ learnerName }}</span>
                </div> -->
                <div class="flex justify-between">
                  <span class="text-gray-600">Area:</span>
                  <span class="font-medium">{{ postcode }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Instructor:</span>
                  <span class="font-medium">{{ selectedInstructor?.name || 'None selected' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
              <h1 class="text-3xl font-bold text-gray-900 mb-2">Pick a package</h1>
              <p class="text-lg text-gray-600">Choose the lesson package that works best for you</p>
            </div>

            <form @submit.prevent="submit">
              <div v-if="packages && packages.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div 
                  v-for="(pkg, index) in packages" 
                  :key="pkg.id"
                  class="package-card relative"
                  :class="{ 'transform md:scale-105 md:-mt-4': pkg.promoted }"
                >
                  <!-- Intro Offer Badge -->
                  <div 
                    v-if="pkg.promoted" 
                    class="absolute -top-3 left-1/2 transform -translate-x-1/2 z-10"
                  >
                    <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-medium">Offer</span>
                  </div>

                  <input 
                    type="radio" 
                    :id="`package-${pkg.id}`" 
                    v-model="form.package_id"
                    :value="pkg.id" 
                    class="sr-only peer"
                  >
                  <label 
                    :for="`package-${pkg.id}`" 
                    :class="[
                      'block cursor-pointer transition-all duration-200 h-full',
                      pkg.promoted 
                        ? 'p-8 border-4 border-red-600 rounded-lg hover:border-red-700 peer-checked:border-red-700 bg-red-600 shadow-lg'
                        : 'p-6 border-2 border-gray-200 rounded-lg hover:border-gray-300 peer-checked:border-blue-600 peer-checked:bg-blue-50'
                    ]"
                  >
                    <div class="text-center">
                      <div class="mb-4">
                        <i 
                          :class="[
                            'fa-solid text-3xl',
                            getPackageIcon(pkg),
                            pkg.promoted ? 'text-white' : 'text-gray-300'
                          ]"
                        ></i>
                      </div>
                      <h3 :class="[
                        'text-xl font-semibold mb-2',
                        pkg.promoted ? 'text-white' : 'text-gray-900'
                      ]">{{ pkg.name }}</h3>
                      <div class="flex-row items-baseline justify-center mb-3">
                        <span :class="[
                          'text-3xl font-bold flex-row items-center',
                          pkg.promoted ? 'text-white' : 'text-gray-900'
                        ]">{{ pkg.formatted_total_price }}</span>
                        <span :class="[
                          'text-lg ml-2 flex-row items-center',
                          pkg.promoted ? 'text-white' : 'text-gray-500'
                        ]">{{ pkg.lessons_count }} lessons</span>
                      </div>
                      <div :class="[
                        'text-sm mb-2',
                        pkg.promoted ? 'text-white' : 'text-gray-600'
                      ]">
                        <span class="font-medium">{{ pkg.formatted_lesson_price }}</span> per hour
                      </div>
                   
                      <p :class="[
                        'text-sm',
                        pkg.promoted ? 'text-white' : 'text-gray-600'
                      ]">{{ pkg.description }}</p>
                    </div>
                  </label>
                </div>
              </div>

              <div v-else class="text-center py-12 mb-8">
                <i class="fa-solid fa-exclamation-triangle text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No packages available for the selected instructor.</p>
                <Link 
                  :href="step2({ uuid: uuid }).url"
                  class="text-blue-600 hover:text-blue-700 font-medium mt-4 inline-block"
                >
                  Go back and select another instructor
                </Link>
              </div>

              <div v-if="packages && packages.length > 0" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-8">
                <div class="flex items-start">
                  <i class="fa-solid fa-info-circle text-amber-600 mr-3 mt-0.5"></i>
                  <div class="text-sm text-amber-800">
                    <p><strong>Pricing Note:</strong> Prices may vary by area and instructor. Your final price will be confirmed before payment.</p>
                  </div>
                </div>
              </div>

              <div class="flex justify-between items-center">
                <Link 
                  :href="step2({ uuid: uuid }).url"
                  class="text-gray-600 hover:text-gray-800 font-medium flex items-center"
                >
                  <i class="fa-solid fa-arrow-left mr-2"></i>
                  Back
                </Link>
                <div class="flex items-center space-x-4">
                  <div class="text-sm text-gray-500">
                    <i class="fa-solid fa-save mr-1"></i>
                    Progress automatically saved
                  </div>
                  <button 
                    v-if="packages && packages.length > 0"
                    type="submit"
                    class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed"
                    :disabled="!form.package_id || form.processing"
                  >
                    <span v-if="form.processing">
                      <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                      Processing...
                    </span>
                    <span v-else>
                      Next
                      <i class="fa-solid fa-arrow-right ml-2"></i>
                    </span>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>

    <footer class="bg-white border-t border-gray-200 mt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <div class="text-sm text-gray-600 mb-4 md:mb-0">
            © 2024 DRIVE Driving School
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

    <!-- Toast Notifications -->
    <div 
      v-if="showToast" 
      class="fixed top-20 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50"
    >
      <i class="fa-solid fa-check mr-2"></i>Package selected
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, useForm, Link, router } from '@inertiajs/vue3'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import { step2 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step3'

const props = defineProps({
  uuid: String,
  currentStep: Number,
  totalSteps: Number,
  stepData: Object,
  postcode: String,
  selectedInstructor: Object,
  packages: Array,
  maxStepReached: { type: Number, default: 3 }
})

const page = usePage()
const showToast = ref(false)

const form = useForm({
  package_id: props.stepData?.package_id || null
})

const learnerName = computed(() => {
  const step1Data = page.props.enquiry?.data?.steps?.step1
  if (step1Data?.first_name && step1Data?.last_name) {
    return `${step1Data.first_name} ${step1Data.last_name}`
  }
  return 'Not provided'
})

const postcode = computed(() => {
  return props.postcode || page.props.postcode || 'Not provided'
})

const selectedInstructor = computed(() => {
  return props.selectedInstructor || null
})

// Get appropriate icon for package
function getPackageIcon(pkg) {
  if (pkg.hours <= 2) return 'fa-car'
  if (pkg.hours <= 10) return 'fa-graduation-cap'
  if (pkg.hours <= 20) return 'fa-trophy'
  return 'fa-rocket'
}

 
// Show toast when package is selected
watch(() => form.package_id, (newValue) => {
  if (newValue) {
    showToast.value = true
    setTimeout(() => {
      showToast.value = false
    }, 2000)
  }
})

function submit() {
  form.post(store({ uuid: props.uuid }).url)
}
</script>