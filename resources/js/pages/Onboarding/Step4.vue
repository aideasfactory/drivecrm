<template>
  <div class="bg-gray-50 min-h-screen">
    <OnboardingHeader :current-step="4" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Instructor Card -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
              <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Your Instructor</h3>
              <button 
                v-if="!showInstructorDropdown && selectedInstructor"
                @click="showInstructorDropdown = true"
                class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center"
              >
                <i class="fa-solid fa-rotate mr-1"></i>
                Change
              </button>
            </div>
            
            <div v-if="!showInstructorDropdown" class="space-y-4">
              <div v-if="selectedInstructor" class="space-y-4">
                <div class="flex items-start space-x-4">
                  <img 
                    :src="selectedInstructor.avatar" 
                    :alt="selectedInstructor.name" 
                    class="w-16 h-16 rounded-full object-cover"
                  >
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-900 mb-1">{{ selectedInstructor.name }}</h4>
                    <div class="flex items-center space-x-1 mb-2">
                      <div class="flex">
                        <i v-for="i in 5" :key="i" 
                           :class="['fa-star text-xs', i <= Math.floor(selectedInstructor.rating) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300']"></i>
                      </div>
                      <span class="text-xs text-gray-600 ml-1">{{ selectedInstructor.rating }}</span>
                    </div>
                  </div>
                </div>
                
                <p class="text-sm text-gray-600 leading-relaxed">{{ selectedInstructor.bio }}</p>
                
                <div class="flex flex-wrap gap-2">
                  <span v-for="tag in selectedInstructor.tags" :key="tag" 
                        class="px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded-full">
                    {{ tag }}
                  </span>
                </div>
              </div>
              <div v-else class="text-center py-8">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                  <i class="fa-solid fa-user-graduate text-gray-400 text-2xl"></i>
                </div>
                <p class="text-gray-500 text-sm">No instructor assigned yet</p>
                <button 
                  @click="showInstructorDropdown = true"
                  class="mt-3 text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                  Select an instructor
                </button>
              </div>
            </div>
            
            <!-- Instructor Dropdown -->
            <div v-else class="mt-4 space-y-3 max-h-96 overflow-y-auto">
              <div class="text-sm text-gray-600 mb-3 pb-3 border-b">
                <p class="font-medium text-gray-900 mb-1">Select a different instructor</p>
                <p class="text-xs">Choose from available instructors in your area</p>
              </div>
              
              <div v-for="instructor in availableInstructors" :key="instructor.id"
                   @click="selectInstructor(instructor)"
                   class="cursor-pointer p-3 border-2 rounded-lg transition-colors"
                   :class="selectedInstructor?.id === instructor.id ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-600'">
                <div class="flex items-start space-x-3">
                  <img :src="instructor.avatar" :alt="instructor.name" 
                       class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                      <h5 class="font-semibold text-gray-900 text-sm">{{ instructor.name }}</h5>
                      <span v-if="selectedInstructor?.id === instructor.id" 
                            class="px-2 py-0.5 bg-blue-600 text-white text-xs rounded-full flex-shrink-0">
                        Current
                      </span>
                      <span v-else 
                            class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full flex-shrink-0">
                        Available
                      </span>
                    </div>
                    <div class="flex items-center space-x-1">
                      <div class="flex">
                        <i v-for="i in 5" :key="i" 
                           :class="['fa-star text-xs', i <= Math.floor(instructor.rating) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300']"></i>
                      </div>
                      <span class="text-xs text-gray-600 ml-1">{{ instructor.rating }}</span>
                    </div>
                  </div>
                </div>
              </div>
              
              <button @click="showInstructorDropdown = false" 
                      class="w-full mt-2 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
              </button>
            </div>
          </div>

          <!-- Main Sidebar -->
          <OnboardingLeftSidebar />
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
              <h1 class="text-3xl font-bold text-gray-900 mb-2">Choose your lesson start date</h1>
              <p class="text-lg text-gray-600">Select when you'd like to begin your driving lessons. We'll coordinate exact times with your instructor after payment.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
              <!-- Loading Overlay -->
              <div v-if="loadingCalendar" class="relative">
                <div class="absolute inset-0 bg-white bg-opacity-75 z-10 flex items-center justify-center rounded-lg">
                  <div class="text-center">
                    <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-600 mb-2"></i>
                    <p class="text-sm text-gray-600">Loading calendar for {{ selectedInstructor?.firstName }}...</p>
                  </div>
                </div>
              </div>
              
              <!-- Date Selection -->
              <div>
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-gray-900">Select a date for your lessons</h3>
                  
                  <button @click.prevent="showCalendarSheet = true" 
                          type="button"
                          class="flex items-center space-x-2 px-4 py-2 text-sm text-gray-600 hover:text-blue-600 border border-gray-300 rounded-lg hover:border-blue-600 transition-colors">
                    <i class="fa-regular fa-calendar"></i>
                    <span>Month View</span>
                  </button>
                </div>
                
                <div class="flex items-center">
                  <button @click.prevent="previousWeek" :disabled="weekOffset === 0"
                          type="button"
                          class="p-2 rounded-full hover:bg-gray-100 mr-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left text-gray-600"></i>
                  </button>
                  
                  <div class="flex-1 overflow-hidden">
                    <div class="flex space-x-3">
                      <div v-for="date in visibleDates" :key="date.dateString"
                           @click="date.available && selectDate(date.dateString)"
                           class="flex-shrink-0 p-4 border-2 rounded-lg text-center min-w-[100px] transition-colors"
                           :class="getDateClasses(date)">
                        <div class="text-xs mb-1" :class="getDateTextClasses(date)">
                          {{ date.dayName }}
                          <span v-if="date.isToday" class="text-xs font-normal"></span>
                          <span v-else-if="date.isTomorrow" class="text-xs font-normal"></span>
                        </div>
                        <div class="text-lg font-semibold" :class="getDateTextClasses(date)">{{ date.day }}</div>
                        <div class="text-xs" :class="getDateTextClasses(date)">{{ date.monthName }}</div>
                      </div>
                    </div>
                  </div>
                  
                  <button @click.prevent="nextWeek" 
                          type="button"
                          class="p-2 rounded-full hover:bg-gray-100 ml-2">
                    <i class="fa-solid fa-chevron-right text-gray-600"></i>
                  </button>
                </div>
              </div>

              <!-- Time Slots -->
              <div v-if="form.date">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                  Available time slots{{ selectedInstructor ? ' with ' + selectedInstructor.firstName : '' }}
                </h3>
                <p class="text-sm text-gray-600 mb-4">{{ formatSelectedDate }} • Select your preferred lesson times</p>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                  <button v-for="slot in timeSlots" :key="slot.id"
                          @click.prevent="!slot.booked && selectTime(slot)"
                          type="button"
                          :disabled="slot.booked"
                          class="p-3 border-2 rounded-lg transition-colors text-center"
                          :class="getTimeSlotClasses(slot)">
                    <div class="font-medium" :class="form.calendar_item_id === slot.id ? 'text-blue-600' : (slot.booked ? 'text-gray-500' : 'text-gray-900')">
                      {{ slot.displayTime }}
                    </div>
                    <div class="text-xs" :class="getTimeSlotStatusClasses(slot)">
                      {{ getTimeSlotStatus(slot) }}
                    </div>
                  </button>
                </div>
              </div>

              <!-- Reservation Info -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                  <i class="fa-solid fa-info-circle text-blue-600 mt-0.5 mr-3"></i>
                  <div class="text-sm text-blue-800">
                    <p class="font-medium mb-1">Lesson slots reserved</p>
                    <p>We'll hold your lesson slots for up to 24 hours while you complete payment.{{ selectedInstructor ? ' Your instructor ' + selectedInstructor.firstName + ' will coordinate exact times with you after booking confirmation.' : '' }}</p>
                  </div>
                </div>
              </div>

              <!-- Error Messages -->
              <div v-if="form.errors.date || form.errors.calendar_item_id || form.errors.start_time || form.errors.end_time || form.errors.instructor_id" class="rounded-md bg-red-50 p-4">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <i class="fa-solid fa-times-circle text-red-400"></i>
                  </div>
                  <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">
                      There were errors with your submission
                    </h3>
                    <div class="mt-2 text-sm text-red-700">
                      <ul class="list-disc pl-5 space-y-1">
                        <li v-if="form.errors.date">{{ form.errors.date }}</li>
                        <li v-if="form.errors.calendar_item_id">{{ form.errors.calendar_item_id }}</li>
                        <li v-if="form.errors.start_time">{{ form.errors.start_time }}</li>
                        <li v-if="form.errors.end_time">{{ form.errors.end_time }}</li>
                        <li v-if="form.errors.instructor_id">{{ form.errors.instructor_id }}</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Navigation -->
              <div class="flex justify-between items-center pt-6 border-t">
                <Link 
                  :href="step3({ uuid: page.props.enquiry?.id || page.props.uuid }).url"
                  class="text-gray-600 hover:text-gray-800 font-medium flex items-center"
                >
                  <i class="fa-solid fa-arrow-left mr-2"></i>
                  Back
                </Link>
                
                <button 
                  type="submit"
                  class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center"
                  :disabled="!form.date || !form.calendar_item_id || form.processing"
                >
                  <span v-if="form.processing">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                    Processing...
                  </span>
                  <span v-else>
                    Continue
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

    <!-- Calendar Sheet Modal -->
    <Teleport to="body">
      <div v-if="showCalendarSheet" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showCalendarSheet = false"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl shadow-2xl transform transition-transform duration-300 max-h-[80vh] overflow-y-auto"
             :class="showCalendarSheet ? 'translate-y-0' : 'translate-y-full'">
          <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 rounded-t-2xl">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-xl font-semibold text-gray-900">Select a date</h3>
              <button @click="showCalendarSheet = false" 
                      class="p-2 hover:bg-gray-100 rounded-full">
                <i class="fa-solid fa-times text-gray-600"></i>
              </button>
            </div>
            <div class="flex items-center justify-between">
              <button @click="previousMonth" class="p-2 hover:bg-gray-100 rounded-full">
                <i class="fa-solid fa-chevron-left text-gray-600"></i>
              </button>
              <h4 class="text-lg font-semibold text-gray-900">{{ currentMonthYear }}</h4>
              <button @click="nextMonth" class="p-2 hover:bg-gray-100 rounded-full">
                <i class="fa-solid fa-chevron-right text-gray-600"></i>
              </button>
            </div>
          </div>
          
          <div class="p-6">
            <div class="grid grid-cols-7 gap-2 mb-2">
              <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                   class="text-center text-xs font-medium text-gray-500 py-2">
                {{ day }}
              </div>
            </div>
            
            <div class="grid grid-cols-7 gap-2">
              <div v-for="n in firstDayOfMonth" :key="`empty-${n}`"></div>
              <div v-for="day in daysInMonth" :key="day"
                   @click="isDateAvailable(day) && selectDateFromCalendar(day)"
                   class="p-3 text-center rounded-lg transition-colors"
                   :class="getCalendarDayClasses(day)">
                {{ day }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage, Link, useForm } from '@inertiajs/vue3'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import { step3 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step4'

const props = defineProps({
  uuid: String,
  availability: Object,
  instructor: Object,
  availableInstructors: Array,
  disabledDates: Array,
  maxStepReached: { type: Number, default: 4 }
})

const page = usePage()

const showInstructorDropdown = ref(false)
const showCalendarSheet = ref(false)
const showToast = ref(false)
const weekOffset = ref(0)
const currentViewMonth = ref(new Date())
const loadingCalendar = ref(false)

const form = useForm({
  date: page.props.enquiry?.data?.steps?.step4?.date || '',
  calendar_item_id: page.props.enquiry?.data?.steps?.step4?.calendar_item_id || null,
  start_time: page.props.enquiry?.data?.steps?.step4?.start_time || '',
  end_time: page.props.enquiry?.data?.steps?.step4?.end_time || '',
  instructor_id: page.props.enquiry?.data?.steps?.step4?.instructor_id || props.instructor?.id || 1
})

const selectedInstructor = ref(props.instructor || null)

console.log(selectedInstructor.value)

// Use instructors from database
const availableInstructors = ref(props.availableInstructors || [])

// Get time slots for the selected date
const timeSlots = computed(() => {
  if (!form.date || !props.availability?.dates) {
    return []
  }
  
  const selectedDateData = props.availability.dates.find(d => d.date === form.date)
  if (!selectedDateData || !selectedDateData.slots) {
    return []
  }
  
  return selectedDateData.slots.map(slot => ({
    id: slot.id,
    time: slot.start_time,
    endTime: slot.end_time,
    displayTime: formatTimeDisplay(slot.start_time) + ' - ' + formatTimeDisplay(slot.end_time),
    booked: false // All returned slots are available
  }))
})

function formatTimeDisplay(time) {
  if (!time) return ''
  const parts = time.split(':')
  if (parts.length !== 2) return time
  
  const [hour, minute] = parts
  const h = parseInt(hour)
  const ampm = h >= 12 ? 'PM' : 'AM'
  const displayHour = h > 12 ? h - 12 : (h === 0 ? 12 : h)
  return `${displayHour}:${minute} ${ampm}`
}

const visibleDates = computed(() => {
  const dates = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  // Always show 7 days starting from today (even if not available)
  const startDate = new Date(today)
  startDate.setDate(startDate.getDate() + (weekOffset.value * 7))
  
  for (let i = 0; i < 7; i++) {
    const date = new Date(startDate)
    date.setDate(date.getDate() + i)
    const dateString = formatDateString(date)
    
    // Check if this date is in our availability data
    let available = false
    if (props.availability?.dates) {
      const availableDate = props.availability.dates.find(d => d.date === dateString)
      available = availableDate?.has_availability || false
    }
    
    // Enforce 2-day minimum advance booking
    const daysDiff = Math.floor((date - today) / (1000 * 60 * 60 * 24))
    if (daysDiff < 2) {
      available = false
    }
    
    dates.push({
      date: date,
      dateString: dateString,
      dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
      day: date.getDate(),
      monthName: date.toLocaleDateString('en-US', { month: 'short' }),
      available: available,
      isToday: daysDiff === 0,
      isTomorrow: daysDiff === 1
    })
  }
  
  return dates
})

const formatSelectedDate = computed(() => {
  if (!form.date) return ''
  const date = new Date(form.date)
  return date.toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
})

const currentMonthYear = computed(() => {
  return currentViewMonth.value.toLocaleDateString('en-US', { 
    month: 'long', 
    year: 'numeric' 
  })
})

const firstDayOfMonth = computed(() => {
  const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), 1)
  return date.getDay()
})

const daysInMonth = computed(() => {
  const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth() + 1, 0)
  return date.getDate()
})

// Watch for changes and show toast
watch(() => form.date, () => {
  if (form.date) {
    // Reset time selection when date changes
    form.calendar_item_id = null
    form.start_time = ''
    form.end_time = ''
    showToast.value = true
    setTimeout(() => {
      showToast.value = false
    }, 2000)
  }
})

watch(() => form.calendar_item_id, () => {
  if (form.calendar_item_id) {
    showToast.value = true
    setTimeout(() => {
      showToast.value = false
    }, 2000)
  }
})

function formatDateString(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function getDateClasses(date) {
  if (!date.available) {
    return 'border-gray-300 bg-gray-100 cursor-not-allowed opacity-60'
  }
  if (form.date === date.dateString) {
    return 'border-blue-600 bg-blue-50 cursor-pointer'
  }
  return 'border-gray-200 hover:border-blue-600 hover:bg-blue-50 cursor-pointer'
}

function getDateTextClasses(date) {
  if (!date.available) {
    return 'text-gray-400'
  }
  if (form.date === date.dateString) {
    return 'text-blue-600'
  }
  return 'text-gray-600'
}

function getTimeSlotClasses(slot) {
  if (slot.booked) {
    return 'border-gray-300 bg-gray-100 cursor-not-allowed opacity-60'
  }
  if (form.calendar_item_id === slot.id) {
    return 'border-blue-600 bg-blue-50'
  }
  return 'border-gray-200 hover:border-blue-600 hover:bg-blue-50'
}

function getTimeSlotStatus(slot) {
  if (slot.booked) return 'Booked'
  if (form.calendar_item_id === slot.id) return 'Selected'
  return 'Available'
}

function getTimeSlotStatusClasses(slot) {
  if (slot.booked) return 'text-gray-500'
  if (form.calendar_item_id === slot.id) return 'text-blue-600'
  return 'text-green-600'
}

function getCalendarDayClasses(day) {
  const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), day)
  const dateString = formatDateString(date)
  
  // Check if date is available in our data
  const isAvailable = isDateAvailable(day)
  
  if (!isAvailable) {
    return 'text-gray-300 cursor-not-allowed'
  }
  
  if (form.date === dateString) {
    return 'bg-blue-600 text-white font-semibold cursor-pointer'
  }
  
  return 'hover:bg-blue-50 text-gray-900 cursor-pointer'
}

function isDateAvailable(day) {
  const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), day)
  date.setHours(0, 0, 0, 0)
  const dateString = formatDateString(date)
  
  // First check 2-day minimum requirement
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const daysDiff = Math.floor((date - today) / (1000 * 60 * 60 * 24))
  
  if (daysDiff < 2) {
    return false // Cannot book today or tomorrow
  }
  
  // Then check if date exists in availability data
  if (props.availability?.dates) {
    const availableDate = props.availability.dates.find(d => d.date === dateString)
    return availableDate?.has_availability || false
  }
  
  return false // No availability data means not available
}

function selectDate(dateString) {
  form.date = dateString
}

function selectTime(slot) {
  form.calendar_item_id = slot.id
  form.start_time = slot.time
  form.end_time = slot.endTime
}

async function selectInstructor(instructor) {
  selectedInstructor.value = instructor
  showInstructorDropdown.value = false
  form.instructor_id = instructor.id
  
  // Show loading state
  loadingCalendar.value = true
  
  // Fetch calendar for the selected instructor
  try {
    const response = await fetch(`/onboarding/${props.uuid}/instructor/${instructor.id}/availability`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      
      // Update availability data
      if (data.availability) {
        // Update the props availability data
        props.availability.dates = data.availability.dates
        props.availability.default_selected_index = data.availability.default_selected_index
        
        // Reset date selection to force re-selection with new availability
        form.date = ''
        form.calendar_item_id = null
        form.start_time = ''
        form.end_time = ''
        
        // Automatically select first available date
        if (data.availability.dates && data.availability.dates.length > 0) {
          const defaultIndex = data.availability.default_selected_index
          if (defaultIndex !== null && data.availability.dates[defaultIndex]) {
            selectDate(data.availability.dates[defaultIndex].date)
          } else {
            const firstAvailable = data.availability.dates.find(d => d.has_availability)
            if (firstAvailable) {
              selectDate(firstAvailable.date)
            }
          }
        }
        
        // Show confirmation toast
        showToast.value = true
        setTimeout(() => {
          showToast.value = false
        }, 2000)
      }
    }
  } catch (error) {
    console.error('Failed to fetch instructor availability:', error)
  } finally {
    loadingCalendar.value = false
  }
}

function selectDateFromCalendar(day) {
  const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), day)
  const dateString = formatDateString(date)
  selectDate(dateString)
  showCalendarSheet.value = false
}

function previousWeek() {
  if (weekOffset.value > 0) {
    weekOffset.value--
  }
}

function nextWeek() {
  // Only allow next if we have more dates to show
  const maxWeeks = Math.ceil((props.availability?.dates?.length || 0) / 7) - 1
  if (weekOffset.value < maxWeeks) {
    weekOffset.value++
  }
}

function previousMonth() {
  currentViewMonth.value = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth() - 1, 1)
}

function nextMonth() {
  currentViewMonth.value = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth() + 1, 1)
}

function submit() {
  form.post(store({ uuid: props.uuid || page.props.enquiry?.id || page.props.uuid }).url)
}

onMounted(() => {
  // Initialize with first available date if not set
  if (!form.date && props.availability?.dates?.length > 0) {
    // Use default_selected_index if provided, otherwise find first available
    const defaultIndex = props.availability.default_selected_index
    if (defaultIndex !== null && props.availability.dates[defaultIndex]) {
      selectDate(props.availability.dates[defaultIndex].date)
    } else {
      const firstAvailable = props.availability.dates.find(d => d.has_availability)
      if (firstAvailable) {
        selectDate(firstAvailable.date)
      }
    }
  }
  // Initialize with first available time slot if not set
  if (!form.calendar_item_id && form.date) {
    const firstAvailable = timeSlots.value.find(s => !s.booked)
    if (firstAvailable) {
      selectTime(firstAvailable)
    }
  }
})
</script>