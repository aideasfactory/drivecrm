<template>
  <div class="bg-gray-50 min-h-screen">
    <OnboardingHeader :current-step="currentStep" :total-steps="totalSteps" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
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
          </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
              <h1 class="text-3xl font-bold text-gray-900 mb-2">Select your instructor</h1>
              <p class="text-lg text-gray-600">Choose an instructor that suits your preferences and location</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <!-- Map Section -->
              <div class="order-2 lg:order-1">
                <div class="bg-gray-100 rounded-lg h-[500px] relative overflow-hidden">
                  <InstructorMap 
                    v-if="googleMapsApiKey"
                    :api-key="googleMapsApiKey"
                    :user-postcode="postcode"
                    :instructors="filteredInstructors"
                    :selected-instructor-id="selectedInstructor"
                    @instructor-selected="selectInstructor"
                    @map-loaded="onMapLoaded"
                  />
                  <div v-else class="absolute inset-0 bg-gradient-to-br from-blue-50 to-green-50 flex items-center justify-center">
                    <div class="text-center">
                      <i class="fa-solid fa-map-marked-alt text-6xl text-gray-400 mb-4"></i>
                      <p class="text-gray-600">Map unavailable</p>
                      <p class="text-sm text-gray-500">Google Maps API key not configured</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Instructor List Section -->
              <div class="order-1 lg:order-2">
                <!-- Transmission Filter -->
                <div class="mb-6">
                  <h3 class="text-sm font-semibold text-gray-900 mb-3">Transmission Type</h3>
                  <div class="flex space-x-2">
                    <button 
                      v-for="filter in transmissionFilters"
                      :key="filter.value"
                      @click="setTransmissionFilter(filter.value)"
                      :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition-colors',
                        selectedTransmission === filter.value 
                          ? 'bg-blue-600 text-white' 
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                      ]"
                    >
                      {{ filter.label }}
                    </button>
                  </div>
                </div>

                <!-- Available Instructors -->
                <div class="mb-4">
                  <h3 class="text-sm font-semibold text-gray-900">Available Instructors</h3>
                </div>

                <!-- Instructor Cards -->
                <div class="space-y-3">
                  <div 
                    v-for="instructor in filteredInstructors" 
                    :key="instructor.id"
                    :class="[
                      'border rounded-xl p-4 shadow-sm cursor-pointer transition-all',
                      selectedInstructor === instructor.id 
                        ? 'border-blue-600 bg-blue-50' 
                        : instructor.priority 
                          ? 'border-2 border-orange-300 bg-gradient-to-r from-orange-50 to-yellow-50'
                          : 'border border-gray-200 bg-white'
                    ]"
                    @click="selectInstructor(instructor.id)"
                  >
                    <div class="flex items-start space-x-3">
                      <img 
                        :src="instructor.avatar" 
                        :alt="instructor.name" 
                        :class="[
                          'w-16 h-16 rounded-full object-cover flex-shrink-0',
                          instructor.priority ? 'ring-2 ring-orange-200' : ''
                        ]"
                      >
                      <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between mb-2">
                          <div class="flex-1">
                            <div class="flex items-center space-x-2">
                              <h4 class="font-semibold text-gray-900 text-base">{{ instructor.name }}</h4>
                              <button 
                                @click.stop="showInstructorInfo(instructor)"
                                class="text-blue-600 hover:text-blue-700 transition-colors"
                              >
                                <i class="fa-solid fa-info-circle text-sm"></i>
                              </button>
                            </div>
                            <div class="flex items-center space-x-2 mt-1">
                              <div class="flex items-center">
                                <div class="flex">
                                  <i v-for="i in 5" :key="i" 
                                     :class="['fa-star text-xs', i <= Math.floor(instructor.rating) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300']"></i>
                                </div>
                                <span class="text-xs text-gray-600 ml-1">{{ instructor.rating }}</span>
                              </div>
                            </div>
                            <div class="flex items-center space-x-1 mt-1">
                              <span 
                                v-for="transmission in instructor.transmissions"
                                :key="transmission"
                                :class="[
                                  'px-2 py-0.5 rounded-full text-xs font-medium',
                                  transmission === 'manual' 
                                    ? 'bg-blue-100 text-blue-800' 
                                    : 'bg-green-100 text-green-800'
                                ]"
                              >
                                {{ transmission === 'manual' ? 'Manual' : 'Auto' }}
                              </span>
                            </div>
                          </div>
                          <span 
                            v-if="instructor.priority" 
                            class="px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap ml-2 shadow-sm bg-orange-500 text-white"
                          >
                            Top Pick
                          </span>
                        </div>

                        <!-- Special Offer -->
                        <!-- <div 
                          v-if="instructor.specialOffer"
                          class="border rounded-lg px-2 py-1.5 mb-2 bg-orange-100 border-orange-200"
                        >
                          <p class="text-xs font-semibold flex items-center text-orange-900">
                            <i :class="[
                              'mr-1.5',
                              instructor.specialOffer.includes('gift') ? 'fa-solid fa-gift' : 'fa-solid fa-tag'
                            ]"></i>
                            {{ instructor.specialOffer }}
                          </p>
                        </div> -->

                        <div class="space-y-1 text-sm">
                          <div class="flex items-center text-gray-600">
                            <i class="fa-solid fa-map-marker-alt mr-2 text-xs text-gray-400"></i>
                            <span>{{ instructor.address }} • {{ instructor.postcode }}</span>
                          </div>
                          <div class="flex items-center text-green-600 font-medium">
                            <i class="fa-solid fa-calendar-check mr-2 text-xs"></i>
                            <span>Next: {{ instructor.next_available }}</span>
                          </div>
                        </div>

                        <button 
                          @click.stop="selectInstructor(instructor.id)"
                          :class="[
                            'w-full mt-3 py-2.5 rounded-lg text-sm font-semibold transition-colors',
                            selectedInstructor === instructor.id
                              ? 'bg-blue-600 text-white'
                              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                          ]"
                        >
                          <template v-if="selectedInstructor === instructor.id">
                            <i class="fa-solid fa-check mr-1"></i>Selected
                          </template>
                          <template v-else>
                            Select Instructor
                          </template>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Load More -->
                <div class="mt-4 text-center">
                  <button 
                    @click="loadMoreInstructors"
                    class="text-blue-600 hover:text-blue-700 font-medium text-sm"
                    :disabled="loadingMore"
                  >
                    {{ loadingMore ? 'Loading...' : 'Load more instructors' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
              <button 
                @click="goBack"
                class="text-gray-600 hover:text-gray-800 font-medium flex items-center"
              >
                <i class="fa-solid fa-arrow-left mr-2"></i>
                Back
              </button>
              <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-500">
                  <i class="fa-solid fa-save mr-1"></i>
                  Progress automatically saved
                </div>
                <button 
                  @click="submit"
                  :disabled="!selectedInstructor || form.processing"
                  class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed"
                >
                  <template v-if="form.processing">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                    Processing...
                  </template>
                  <template v-else>
                    Next
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                  </template>
                </button>
              </div>
            </div>
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

    <!-- Instructor Info Modal -->
    <div 
      v-if="showModal" 
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
      @click="closeModal"
    >
      <div 
        class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl"
        @click.stop
      >
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-t-2xl">
          <h3 class="text-xl font-bold text-gray-900">Instructor Details</h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        <div v-if="modalInstructor" class="p-6">
          <div class="flex items-start space-x-4 mb-6">
            <img 
              :src="modalInstructor.image" 
              :alt="modalInstructor.name" 
              class="w-24 h-24 rounded-full object-cover"
            >
            <div class="flex-1">
              <h4 class="text-2xl font-bold text-gray-900 mb-1">{{ modalInstructor.name }}</h4>
              <div class="flex items-center mb-3">
                <div class="flex">
                  <i v-for="i in 5" :key="i" 
                     :class="['fa-star text-sm', i <= Math.floor(modalInstructor.rating) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300']"></i>
                </div>
                <span class="text-sm text-gray-600 ml-2">{{ modalInstructor.rating }}</span>
              </div>
              <div class="flex flex-wrap gap-2">
                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium">
                  <i class="fa-solid fa-clock mr-1"></i>{{ modalInstructor.experience }} experience
                </span>
                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">
                  <i class="fa-solid fa-chart-line mr-1"></i>{{ modalInstructor.passRate }} pass rate
                </span>
                <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-xs font-medium">
                  <i class="fa-solid fa-users mr-1"></i>{{ modalInstructor.totalStudents }} students
                </span>
              </div>
            </div>
          </div>
          
          <div class="mb-6">
            <h5 class="font-semibold text-gray-900 mb-3 flex items-center">
              <i class="fa-solid fa-user-tie text-blue-600 mr-2"></i>
              About
            </h5>
            <p class="text-gray-600 leading-relaxed">{{ modalInstructor.bio }}</p>
          </div>
          
          <div class="mb-6">
            <h5 class="font-semibold text-gray-900 mb-3 flex items-center">
              <i class="fa-solid fa-star text-blue-600 mr-2"></i>
              Specialties
            </h5>
            <div class="flex flex-wrap gap-2">
              <span 
                v-for="specialty in modalInstructor.specialties"
                :key="specialty"
                class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-sm"
              >
                {{ specialty }}
              </span>
            </div>
          </div>
          
          <div class="mb-6">
            <h5 class="font-semibold text-gray-900 mb-3 flex items-center">
              <i class="fa-solid fa-certificate text-blue-600 mr-2"></i>
              Qualifications
            </h5>
            <ul class="space-y-2">
              <li 
                v-for="qualification in modalInstructor.qualifications"
                :key="qualification"
                class="flex items-start text-gray-600"
              >
                <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                <span>{{ qualification }}</span>
              </li>
            </ul>
          </div>
          
          <div>
            <h5 class="font-semibold text-gray-900 mb-3 flex items-center">
              <i class="fa-solid fa-language text-blue-600 mr-2"></i>
              Languages
            </h5>
            <div class="flex flex-wrap gap-2">
              <span 
                v-for="language in modalInstructor.languages"
                :key="language"
                class="bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg text-sm font-medium"
              >
                {{ language }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import InstructorMap from '@/components/Onboarding/InstructorMap.vue'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'

const props = defineProps({
  uuid: String,
  currentStep: Number,
  totalSteps: Number,
  stepData: Object,
  postcode: String,
  instructors: Array,
  googleMapsApiKey: String,
  maxStepReached: { type: Number, default: 2 }
})

const page = usePage()

const selectedInstructor = ref(null)
const selectedTransmission = ref('all')
const showModal = ref(false)
const modalInstructor = ref(null)
const loadingMore = ref(false)

const form = useForm({
  instructor_id: null
})

const postcode = computed(() => {
  return props.postcode || page.props.postcode || 'SW1A 1AA'
})

const transmissionFilters = [
  { value: 'all', label: 'All' },
  { value: 'manual', label: 'Manual' },
  { value: 'automatic', label: 'Automatic' }
]

// Use instructor data from props
const instructors = computed(() => props.instructors || [])

const filteredInstructors = computed(() => {
  if (selectedTransmission.value === 'all') {
    return instructors.value
  }
  return instructors.value.filter(instructor => 
    instructor.transmissions && instructor.transmissions.includes(selectedTransmission.value)
  )
})

function setTransmissionFilter(filter) {
  selectedTransmission.value = filter
}

function selectInstructor(instructorId) {
  selectedInstructor.value = instructorId
  form.instructor_id = instructorId
  
  // Show success toast
  setTimeout(() => {
    const toast = document.createElement('div')
    toast.className = 'fixed top-20 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50'
    toast.innerHTML = '<i class="fa-solid fa-check mr-2"></i>Instructor selected'
    document.body.appendChild(toast)
    
    setTimeout(() => {
      toast.remove()
    }, 2000)
  }, 300)
}

function showInstructorInfo(instructor) {
  modalInstructor.value = instructor
  showModal.value = true
  document.body.style.overflow = 'hidden'
}

function closeModal() {
  showModal.value = false
  modalInstructor.value = null
  document.body.style.overflow = 'auto'
}

function loadMoreInstructors() {
  loadingMore.value = true
  setTimeout(() => {
    loadingMore.value = false
  }, 1000)
}

function onMapLoaded() {
  console.log('Map loaded successfully')
}

function goBack() {
  router.get(`/onboarding/${props.uuid}/step/1`)
}

function submit() {
  if (!selectedInstructor.value) {
    alert('Please select an instructor before continuing')
    return
  }
  
  console.log('Submitting Step 2 with instructor_id:', form.instructor_id)
  console.log('Form data:', form.data())
  
  form.post(`/onboarding/${props.uuid}/step/2`, {
    onSuccess: () => {
      console.log('Step 2 submitted successfully - redirecting to step 3')
    },
    onError: (errors) => {
      console.error('Step 2 submission errors:', errors)
      alert('Error: ' + JSON.stringify(errors))
    }
  })
}

// Load existing selection if any
onMounted(() => {
  const existingSelection = props.stepData?.instructor_id
  if (existingSelection) {
    selectedInstructor.value = parseInt(existingSelection)
    form.instructor_id = parseInt(existingSelection)
  }
})
</script>