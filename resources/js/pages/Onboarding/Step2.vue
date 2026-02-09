<template>
  <div class="min-h-screen">
    <OnboardingHeader :current-step="currentStep" :total-steps="totalSteps" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1 order-2 lg:order-1">
          <OnboardingLeftSidebar />
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 order-1 lg:order-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-3xl">Select your instructor</CardTitle>
              <CardDescription class="text-lg">
                Choose an instructor that suits your preferences and location
              </CardDescription>
            </CardHeader>

            <CardContent>
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Map Section -->
                <div class="order-2 lg:order-1">
                  <div class="rounded-lg h-[500px] relative overflow-hidden">
                    <InstructorMap
                      v-if="googleMapsApiKey"
                      :api-key="googleMapsApiKey"
                      :user-postcode="postcode"
                      :instructors="filteredInstructors"
                      :selected-instructor-id="selectedInstructor"
                      @instructor-selected="selectInstructor"
                      @map-loaded="onMapLoaded"
                    />
                    <div v-else class="absolute inset-0 flex items-center justify-center">
                      <div class="text-center">
                        <MapPin class="h-16 w-16 mx-auto mb-4" />
                        <p>Map unavailable</p>
                        <p class="text-sm text-muted-foreground">Google Maps API key not configured</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Instructor List Section -->
                <div class="order-1 lg:order-2">
                  <!-- Transmission Filter -->
                  <div class="mb-6 space-y-3">
                    <h3 class="text-sm font-semibold">Transmission Type</h3>
                    <div class="flex space-x-2">
                      <Button
                        v-for="filter in transmissionFilters"
                        :key="filter.value"
                        @click="setTransmissionFilter(filter.value)"
                        :variant="selectedTransmission === filter.value ? 'default' : 'outline'"
                        size="sm"
                      >
                        {{ filter.label }}
                      </Button>
                    </div>
                  </div>

                  <!-- Available Instructors -->
                  <div class="mb-4">
                    <h3 class="text-sm font-semibold">Available Instructors</h3>
                  </div>

                  <!-- Instructor Cards -->
                  <div class="space-y-3">
                    <Card
                      v-for="instructor in filteredInstructors"
                      :key="instructor.id"
                      :class="[
                        'cursor-pointer transition-all',
                        selectedInstructor === instructor.id
                          ? 'border-primary ring-2 ring-primary bg-red-500/30'
                          : instructor.priority
                            ? 'border-2 border-orange-300 bg-orange-900/40'
                            : ''
                      ]"
                      @click="selectInstructor(instructor.id)"
                    >
                      <CardContent class="pt-6">
                        <div class="flex items-start space-x-3">
                          <Avatar class="h-16 w-16">
                            <img
                              :src="instructor.avatar"
                              :alt="instructor.name"
                            />
                          </Avatar>

                          <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-2">
                              <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                  <h4 class="font-semibold text-base">{{ instructor.name }}</h4>
                                  <Button
                                    @click.stop="showInstructorInfo(instructor)"
                                    variant="ghost"
                                    size="sm"
                                    class="h-auto p-1"
                                  >
                                    <Info class="h-4 w-4" />
                                  </Button>
                                </div>
                                <div class="flex items-center space-x-2 mt-1">
                                  <div class="flex items-center">
                                    <div class="flex">
                                      <Star
                                        v-for="i in 5"
                                        :key="i"
                                        class="h-3 w-3"
                                        :class="i <= Math.floor(instructor.rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'"
                                      />
                                    </div>
                                    <span class="text-xs ml-1">{{ instructor.rating }}</span>
                                  </div>
                                </div>
                                <div class="flex items-center space-x-1 mt-1">
                                  <Badge
                                    v-for="transmission in instructor.transmissions"
                                    :key="transmission"
                                    variant="secondary"
                                    class="text-xs"
                                  >
                                    {{ transmission === 'manual' ? 'Manual' : 'Auto' }}
                                  </Badge>
                                </div>
                              </div>
                              <Badge
                                v-if="instructor.priority"
                                variant="default"
                                class="ml-2 bg-red-600 text-white hover:bg-red-700"
                              >
                                Top Pick
                              </Badge>
                            </div>

                            <div class="space-y-1 text-sm">
                              <div class="flex items-center">
                                <MapPin class="mr-2 h-3 w-3" />
                                <span>{{ instructor.address }} • {{ instructor.postcode }}</span>
                              </div>
                              <div class="flex items-center font-medium">
                                <Calendar class="mr-2 h-3 w-3" />
                                <span>Next: {{ instructor.next_available }}</span>
                              </div>
                            </div>

                            <Button
                              @click.stop="selectInstructor(instructor.id)"
                              :variant="selectedInstructor === instructor.id ? 'default' : 'secondary'"
                              class="w-full mt-3"
                              size="sm"
                            >
                              <Check v-if="selectedInstructor === instructor.id" class="mr-1 h-4 w-4" />
                              {{ selectedInstructor === instructor.id ? 'Selected' : 'Select Instructor' }}
                            </Button>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  </div>

                  <!-- Load More -->
                  <div class="mt-4 text-center">
                    <Button
                      @click="loadMoreInstructors"
                      variant="link"
                      :disabled="loadingMore"
                    >
                      {{ loadingMore ? 'Loading...' : 'Load more instructors' }}
                    </Button>
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <Separator class="mt-8" />
              <div class="flex justify-between items-center pt-6">
                <Button
                  @click="goBack"
                  variant="outline"
                  class="cursor-pointer"
                >
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Back
                </Button>
                <Button
                  @click="submit"
                  :disabled="!selectedInstructor || form.processing"
                  class="cursor-pointer"
                >
                  Next
                  <Spinner v-if="form.processing" class="ml-2 h-4 w-4 animate-spin" />
                  <ArrowRight v-if="!form.processing" class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </main>

    <OnboardingFooter copyright-text="© 2024 DRIVE Driving School" />

    <!-- Instructor Info Dialog -->
    <Dialog v-model:open="showModal">
      <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Instructor Details</DialogTitle>
        </DialogHeader>
        <div v-if="modalInstructor" class="space-y-6">
          <div class="flex items-start space-x-4">
            <Avatar class="h-24 w-24">
              <img
                :src="modalInstructor.image"
                :alt="modalInstructor.name"
              />
            </Avatar>
            <div class="flex-1">
              <h4 class="text-2xl font-bold mb-1">{{ modalInstructor.name }}</h4>
              <div class="flex items-center mb-3">
                <div class="flex">
                  <Star
                    v-for="i in 5"
                    :key="i"
                    class="h-4 w-4"
                    :class="i <= Math.floor(modalInstructor.rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'"
                  />
                </div>
                <span class="text-sm ml-2">{{ modalInstructor.rating }}</span>
              </div>
              <div class="flex flex-wrap gap-2">
                <Badge variant="secondary">
                  <Clock class="mr-1 h-3 w-3" />
                  {{ modalInstructor.experience }} experience
                </Badge>
                <Badge variant="secondary">
                  <TrendingUp class="mr-1 h-3 w-3" />
                  {{ modalInstructor.passRate }} pass rate
                </Badge>
                <Badge variant="secondary">
                  <Users class="mr-1 h-3 w-3" />
                  {{ modalInstructor.totalStudents }} students
                </Badge>
              </div>
            </div>
          </div>

          <div>
            <h5 class="font-semibold mb-3 flex items-center">
              <User class="mr-2 h-4 w-4" />
              About
            </h5>
            <p class="leading-relaxed">{{ modalInstructor.bio }}</p>
          </div>

          <div>
            <h5 class="font-semibold mb-3 flex items-center">
              <Star class="mr-2 h-4 w-4" />
              Specialties
            </h5>
            <div class="flex flex-wrap gap-2">
              <Badge
                v-for="specialty in modalInstructor.specialties"
                :key="specialty"
                variant="outline"
              >
                {{ specialty }}
              </Badge>
            </div>
          </div>

          <div>
            <h5 class="font-semibold mb-3 flex items-center">
              <Award class="mr-2 h-4 w-4" />
              Qualifications
            </h5>
            <ul class="space-y-2">
              <li
                v-for="qualification in modalInstructor.qualifications"
                :key="qualification"
                class="flex items-start"
              >
                <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
                <span>{{ qualification }}</span>
              </li>
            </ul>
          </div>

          <div>
            <h5 class="font-semibold mb-3 flex items-center">
              <Languages class="mr-2 h-4 w-4" />
              Languages
            </h5>
            <div class="flex flex-wrap gap-2">
              <Badge
                v-for="language in modalInstructor.languages"
                :key="language"
                variant="secondary"
              >
                {{ language }}
              </Badge>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Sonner Toast -->
    <Sonner position="top-right" rich-colors />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Avatar } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Sonner, toast } from '@/components/ui/sonner'
import InstructorMap from '@/components/Onboarding/InstructorMap.vue'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import {
  ArrowLeft,
  ArrowRight,
  MapPin,
  Info,
  Star,
  Calendar,
  Check,
  Clock,
  TrendingUp,
  Users,
  User,
  Award,
  CircleCheck,
  Languages
} from 'lucide-vue-next'

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

  // Show success toast using Sonner
  toast.success('Instructor selected', {
    description: 'You can continue to the next step'
  })
}

function showInstructorInfo(instructor) {
  modalInstructor.value = instructor
  showModal.value = true
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
    toast.error('Please select an instructor', {
      description: 'You must select an instructor before continuing'
    })
    return
  }

  form.post(`/onboarding/${props.uuid}/step/2`, {
    onSuccess: () => {
      toast.success('Instructor saved!', {
        description: 'Moving to the next step...'
      })
    },
    onError: (errors) => {
      toast.error('Failed to save instructor', {
        description: JSON.stringify(errors)
      })
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
