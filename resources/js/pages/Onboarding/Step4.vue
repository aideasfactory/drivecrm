<template>
  <div class="min-h-screen">
    <OnboardingHeader :current-step="4" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1 space-y-6 order-2 lg:order-1">
          <!-- Instructor Card -->
          <Card>
            <CardHeader>
              <div class="flex items-start justify-between">
                <CardTitle class="text-sm uppercase tracking-wide">Your Instructor</CardTitle>
                <Button
                  v-if="!showInstructorDropdown && selectedInstructor"
                  @click="showInstructorDropdown = true"
                  variant="ghost"
                  size="sm"
                >
                  <RefreshCw class="mr-1 h-4 w-4" />
                  Change
                </Button>
              </div>
            </CardHeader>

            <CardContent v-if="!showInstructorDropdown" class="space-y-4">
              <div v-if="selectedInstructor" class="space-y-4">
                <div class="flex items-start space-x-4">
                  <Avatar class="h-16 w-16">
                    <AvatarImage :src="selectedInstructor.avatar" :alt="selectedInstructor.name" />
                    <AvatarFallback>{{ selectedInstructor.name?.charAt(0) }}</AvatarFallback>
                  </Avatar>
                  <div class="flex-1">
                    <h4 class="font-semibold mb-1">{{ selectedInstructor.name }}</h4>
                    <div class="flex items-center space-x-1 mb-2">
                      <div class="flex">
                        <Star
                          v-for="i in 5"
                          :key="i"
                          :class="['h-3 w-3', i <= Math.floor(selectedInstructor.rating) ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground']"
                        />
                      </div>
                      <span class="text-xs ml-1">{{ selectedInstructor.rating }}</span>
                    </div>
                  </div>
                </div>

                <p class="text-sm text-muted-foreground leading-relaxed">{{ selectedInstructor.bio }}</p>

                <div class="flex flex-wrap gap-2">
                  <Badge v-for="tag in selectedInstructor.tags" :key="tag" variant="secondary">
                    {{ tag }}
                  </Badge>
                </div>
              </div>
              <div v-else class="text-center py-8">
                <Avatar class="h-16 w-16 mx-auto mb-4">
                  <AvatarFallback>
                    <GraduationCap class="h-8 w-8" />
                  </AvatarFallback>
                </Avatar>
                <p class="text-muted-foreground text-sm">No instructor assigned yet</p>
                <Button
                  @click="showInstructorDropdown = true"
                  variant="link"
                  size="sm"
                  class="mt-3"
                >
                  Select an instructor
                </Button>
              </div>
            </CardContent>

            <!-- Instructor Dropdown -->
            <CardContent v-else class="space-y-3 max-h-96 overflow-y-auto">
              <div class="text-sm mb-3 pb-3 border-b">
                <p class="font-medium mb-1">Select a different instructor</p>
                <p class="text-xs text-muted-foreground">Choose from available instructors in your area</p>
              </div>

              <button
                v-for="instructor in availableInstructors"
                :key="instructor.id"
                @click="selectInstructor(instructor)"
                class="w-full cursor-pointer p-3 border-2 rounded-lg transition-colors text-left"
                :class="selectedInstructor?.id === instructor.id ? 'border-primary bg-primary/5' : 'hover:border-primary'"
              >
                <div class="flex items-start space-x-3">
                  <Avatar class="h-12 w-12 flex-shrink-0">
                    <AvatarImage :src="instructor.avatar" :alt="instructor.name" />
                    <AvatarFallback>{{ instructor.name?.charAt(0) }}</AvatarFallback>
                  </Avatar>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                      <h5 class="font-semibold text-sm">{{ instructor.name }}</h5>
                      <Badge v-if="selectedInstructor?.id === instructor.id" variant="default">
                        Current
                      </Badge>
                      <Badge v-else variant="secondary">
                        Available
                      </Badge>
                    </div>
                    <div class="flex items-center space-x-1">
                      <div class="flex">
                        <Star
                          v-for="i in 5"
                          :key="i"
                          :class="['h-3 w-3', i <= Math.floor(instructor.rating) ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground']"
                        />
                      </div>
                      <span class="text-xs ml-1">{{ instructor.rating }}</span>
                    </div>
                  </div>
                </div>
              </button>

              <Button
                @click="showInstructorDropdown = false"
                variant="outline"
                class="w-full mt-2"
              >
                Cancel
              </Button>
            </CardContent>
          </Card>

          <!-- Main Sidebar -->
          <OnboardingLeftSidebar />
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 order-1 lg:order-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-3xl">Choose your lesson start date</CardTitle>
              <CardDescription class="text-lg">
                Select when you'd like to begin your driving lessons. We'll coordinate exact times with your instructor after payment.
              </CardDescription>
            </CardHeader>

            <CardContent>
              <form @submit.prevent="submit" class="space-y-8">
                <!-- Loading Overlay -->
                <div v-if="loadingCalendar" class="relative">
                  <div class="absolute inset-0 bg-background/75 z-10 flex items-center justify-center rounded-lg">
                    <div class="text-center">
                      <Spinner class="h-8 w-8 mb-2 mx-auto" />
                      <p class="text-sm">Loading calendar for {{ selectedInstructor?.first_name }}...</p>
                    </div>
                  </div>
                </div>

                <!-- Date Selection -->
                <div>
                  <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Select a date for your lessons</h3>

                    <Button
                      @click.prevent="showCalendarSheet = true"
                      type="button"
                      variant="outline"
                      size="sm"
                    >
                      <Calendar class="mr-2 h-4 w-4" />
                      Month View
                    </Button>
                  </div>

                  <div class="flex items-center">
                    <Button
                      @click.prevent="previousWeek"
                      :disabled="weekOffset === 0"
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="mr-2"
                    >
                      <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <div class="flex-1 overflow-hidden">
                      <div class="flex space-x-3">
                        <div v-for="date in visibleDates" :key="date.dateString"
                             @click="date.available && selectDate(date.dateString)"
                             class="flex-shrink-0 p-4 border-2 rounded-lg text-center min-w-[100px] transition-colors"
                             :class="getDateClasses(date)">
                          <div class="text-xs mb-1" :class="getDateTextClasses(date)">
                            {{ date.dayName }}
                          </div>
                          <div class="text-lg font-semibold" :class="getDateTextClasses(date)">{{ date.day }}</div>
                          <div class="text-xs" :class="getDateTextClasses(date)">{{ date.monthName }}</div>
                        </div>
                      </div>
                    </div>

                    <Button
                      @click.prevent="nextWeek"
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="ml-2"
                    >
                      <ChevronRight class="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                <!-- Time Slots -->
                <div v-if="form.date">
                  <h3 class="text-lg font-semibold mb-2">
                    Available time slots{{ selectedInstructor ? ' with ' + selectedInstructor.first_name : '' }}
                  </h3>
                  <p class="text-sm text-muted-foreground mb-4">{{ formatSelectedDate }} • Select your preferred lesson times</p>

                  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <Button
                      v-for="slot in timeSlots"
                      :key="slot.id"
                      @click.prevent="!slot.booked && selectTime(slot)"
                      type="button"
                      :disabled="slot.booked"
                      :variant="form.calendar_item_id === slot.id ? 'default' : 'outline'"
                      class="h-auto flex-col py-3"
                    >
                      <div class="font-medium">
                        {{ slot.displayTime }}
                      </div>
                      <div class="text-xs">
                        {{ getTimeSlotStatus(slot) }}
                      </div>
                    </Button>
                  </div>
                </div>

                <!-- Reservation Info -->
                <Alert>
                  <Info class="h-4 w-4" />
                  <AlertTitle>Lesson slots reserved</AlertTitle>
                  <AlertDescription>
                    We'll hold your lesson slots for up to 24 hours while you complete payment.{{ selectedInstructor ? ' Your instructor ' + selectedInstructor.first_name + ' will coordinate exact times with you after booking confirmation.' : '' }}
                  </AlertDescription>
                </Alert>

                <!-- Error Messages -->
                <Alert v-if="form.errors.date || form.errors.calendar_item_id || form.errors.start_time || form.errors.end_time || form.errors.instructor_id" variant="destructive">
                  <XCircle class="h-4 w-4" />
                  <AlertTitle>There were errors with your submission</AlertTitle>
                  <AlertDescription>
                    <ul class="list-disc pl-5 space-y-1">
                      <li v-if="form.errors.date">{{ form.errors.date }}</li>
                      <li v-if="form.errors.calendar_item_id">{{ form.errors.calendar_item_id }}</li>
                      <li v-if="form.errors.start_time">{{ form.errors.start_time }}</li>
                      <li v-if="form.errors.end_time">{{ form.errors.end_time }}</li>
                      <li v-if="form.errors.instructor_id">{{ form.errors.instructor_id }}</li>
                    </ul>
                  </AlertDescription>
                </Alert>

                <!-- Navigation -->
                <div class="flex justify-between items-center pt-6 border-t">
                  <Link :href="step3({ uuid: page.props.enquiry?.id || page.props.uuid }).url">
                    <Button variant="outline" class="cursor-pointer">
                      <ArrowLeft class="mr-2 h-4 w-4" />
                      Back
                    </Button>
                  </Link>

                  <Button
                    type="submit"
                    :disabled="!form.date || !form.calendar_item_id || form.processing"
                    class="cursor-pointer"
                  >
                    Next
                    <Spinner v-if="form.processing" class="ml-2 h-4 w-4 animate-spin" />
                    <ArrowRight v-if="!form.processing" class="ml-2 h-4 w-4" />
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </main>

    <OnboardingFooter />

    <!-- Calendar Sheet Modal -->
    <Sheet :open="showCalendarSheet" @update:open="showCalendarSheet = $event">
      <SheetContent side="bottom" class="max-h-[80vh] overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Select a date</SheetTitle>
          <div class="flex items-center justify-between mt-4">
            <Button @click="previousMonth" variant="ghost" size="icon">
              <ChevronLeft class="h-4 w-4" />
            </Button>
            <h4 class="text-lg font-semibold">{{ currentMonthYear }}</h4>
            <Button @click="nextMonth" variant="ghost" size="icon">
              <ChevronRight class="h-4 w-4" />
            </Button>
          </div>
        </SheetHeader>

        <div class="mt-6">
          <div class="grid grid-cols-7 gap-2 mb-2">
            <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                 class="text-center text-xs font-medium text-muted-foreground py-2">
              {{ day }}
            </div>
          </div>

          <div class="grid grid-cols-7 gap-2">
            <div v-for="n in firstDayOfMonth" :key="`empty-${n}`"></div>
            <Button
              v-for="day in daysInMonth"
              :key="day"
              @click="isDateAvailable(day) && selectDateFromCalendar(day)"
              :disabled="!isDateAvailable(day)"
              :variant="form.date === formatDateString(new Date(currentViewMonth.getFullYear(), currentViewMonth.getMonth(), day)) ? 'default' : 'ghost'"
              class="h-auto p-3"
            >
              {{ day }}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>

    <!-- Sonner Toast -->
    <Sonner position="top-right" rich-colors />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage, Link, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Spinner } from '@/components/ui/spinner'
import { Sonner, toast } from '@/components/ui/sonner'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import { step3 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step4'
import { ArrowLeft, ArrowRight, RefreshCw, Star, GraduationCap, Calendar, ChevronLeft, ChevronRight, Info, XCircle } from 'lucide-vue-next'

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
    toast.success('Date selected')
  }
})

watch(() => form.calendar_item_id, () => {
  if (form.calendar_item_id) {
    toast.success('Time slot selected')
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
    return 'opacity-50 cursor-not-allowed'
  }
  if (form.date === date.dateString) {
    return 'border-primary bg-primary/10 cursor-pointer'
  }
  return 'hover:border-primary hover:bg-primary/5 cursor-pointer'
}

function getDateTextClasses(date) {
  if (!date.available) {
    return 'text-muted-foreground'
  }
  if (form.date === date.dateString) {
    return 'text-primary'
  }
  return ''
}

function getTimeSlotStatus(slot) {
  if (slot.booked) return 'Booked'
  if (form.calendar_item_id === slot.id) return 'Selected'
  return 'Available'
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
        toast.success('Instructor changed successfully')
      }
    }
  } catch (error) {
    console.error('Failed to fetch instructor availability:', error)
    toast.error('Failed to load instructor calendar')
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
