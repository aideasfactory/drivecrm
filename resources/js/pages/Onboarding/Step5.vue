<template>
  <div class="min-h-screen">
    <OnboardingHeader :current-step="5" :total-steps="6" :max-step-reached="maxStepReached" />

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
              <CardTitle class="text-3xl">Review your booking</CardTitle>
              <CardDescription class="text-lg">
                Please review all details before proceeding to payment. You can edit any section if needed.
              </CardDescription>
            </CardHeader>

            <CardContent>
              <form @submit.prevent="submit" class="space-y-6">
                <!-- Instructor Summary -->
                <Card>
                  <CardHeader>
                    <div class="flex items-center justify-between">
                      <CardTitle>Your Instructor</CardTitle>
                      <Link :href="step2({ uuid: uuid }).url">
                        <Button variant="link" size="sm">
                          Edit
                        </Button>
                      </Link>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div class="flex items-center space-x-4">
                      <Avatar class="h-16 w-16">
                        <AvatarImage
                          :src="instructor?.avatar || 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-5.jpg'"
                          :alt="instructor?.name"
                        />
                        <AvatarFallback>{{ instructor?.name?.charAt(0) || 'I' }}</AvatarFallback>
                      </Avatar>
                      <div class="flex-1">
                        <h4 class="font-semibold">{{ instructor?.name || 'No instructor selected' }}</h4>
                        <div class="flex items-center space-x-4 text-sm text-muted-foreground mt-1">
                          <span class="flex items-center">
                            <Car class="mr-1 h-4 w-4" />
                            {{ instructor?.transmission || 'Manual' }}
                          </span>
                          <span class="flex items-center">
                            <MapPin class="mr-1 h-4 w-4" />
                            {{ postcode || 'Area not set' }}
                          </span>
                          <span class="flex items-center">
                            <Star class="mr-1 h-4 w-4 fill-yellow-400 text-yellow-400" />
                            {{ instructor?.rating || '4.9' }} ({{ instructor?.reviews || '127' }} reviews)
                          </span>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <!-- Package Summary -->
                <Card>
                  <CardHeader>
                    <div class="flex items-center justify-between">
                      <CardTitle>Package Details</CardTitle>
                      <Link :href="step3({ uuid: uuid }).url">
                        <Button variant="link" size="sm">
                          Edit
                        </Button>
                      </Link>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div class="flex items-center justify-between">
                      <div>
                        <h4 class="font-semibold">{{ package?.name || 'No package selected' }}</h4>
                        <p class="text-sm text-muted-foreground">{{ package?.lessons_count || '0' }} lessons</p>
                      </div>
                      <div class="text-right">
                        <div class="text-xl font-bold">{{ package?.formatted_total_price || '0' }}</div>
                        <div class="text-sm text-muted-foreground">{{ package?.formatted_lesson_price || '0' }}/lesson</div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <!-- Schedule Summary -->
                <Card>
                  <CardHeader>
                    <div class="flex items-center justify-between">
                      <CardTitle>Start Date & Time</CardTitle>
                      <Link :href="step4({ uuid: uuid }).url">
                        <Button variant="link" size="sm">
                          Edit
                        </Button>
                      </Link>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div class="flex items-center space-x-4">
                      <div class="w-12 h-12 bg-primary text-primary-foreground rounded-lg flex items-center justify-center font-semibold">
                        {{ selectedDay }}
                      </div>
                      <div>
                        <h4 class="font-semibold">{{ formatDate(schedule?.date) }}</h4>
                        <p class="text-sm text-muted-foreground">{{ formatTimeSlot(schedule?.start_time, schedule?.end_time) }}</p>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <!-- Contact Details -->
                <Card>
                  <CardHeader>
                    <div class="flex items-center justify-between">
                      <CardTitle>Contact Details</CardTitle>
                      <Link :href="step1({ uuid: uuid }).url">
                        <Button variant="link" size="sm">
                          Edit
                        </Button>
                      </Link>
                    </div>
                  </CardHeader>
                  <CardContent class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <p class="text-sm text-muted-foreground mb-1">Name</p>
                        <p class="font-medium">{{ learner?.is_self ? 'You' : (learner?.first_name + ' ' + learner?.last_name) }}</p>
                      </div>
                      <div>
                        <p class="text-sm text-muted-foreground mb-1">Phone</p>
                        <p class="font-medium">{{ contact?.phone }}</p>
                      </div>
                      <div>
                        <p class="text-sm text-muted-foreground mb-1">Email</p>
                        <p class="font-medium">{{ contact?.email }}</p>
                      </div>
                    </div>

                    <Separator />

                    <div class="flex items-start space-x-3">
                      <input
                        type="checkbox"
                        v-model="isBookingForSomeoneElse"
                        id="booking-for-someone-else"
                        class="h-4 w-4 mt-1 rounded border-input text-primary focus:ring-2 focus:ring-ring focus:ring-offset-2 cursor-pointer"
                      />
                      <Label for="booking-for-someone-else" class="font-medium cursor-pointer pt-1.5">
                        I'm booking for someone else
                      </Label>
                    </div>

                    <div v-if="isBookingForSomeoneElse">
                      <Card>
                        <CardHeader>
                          <CardTitle class="text-base">Learner Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                          <div class="grid grid-cols-2 gap-4">
                            <div>
                              <Label for="learner-first-name mb-4">First Name</Label>
                              <Input
                                v-model="form.learner_first_name"
                                id="learner-first-name"
                                type="text"
                                :class="{ 'border-destructive': form.errors.learner_first_name }"
                              />
                              <p v-if="form.errors.learner_first_name" class="text-sm text-destructive mt-1">
                                {{ form.errors.learner_first_name }}
                              </p>
                            </div>
                            <div>
                              <Label for="learner-last-name">Last Name</Label>
                              <Input
                                v-model="form.learner_last_name"
                                id="learner-last-name"
                                type="text"
                                :class="{ 'border-destructive': form.errors.learner_last_name }"
                              />
                              <p v-if="form.errors.learner_last_name" class="text-sm text-destructive mt-1">
                                {{ form.errors.learner_last_name }}
                              </p>
                            </div>
                            <div>
                              <Label for="learner-phone">Phone</Label>
                              <Input
                                v-model="form.learner_phone"
                                id="learner-phone"
                                type="tel"
                                :class="{ 'border-destructive': form.errors.learner_phone }"
                              />
                              <p v-if="form.errors.learner_phone" class="text-sm text-destructive mt-1">
                                {{ form.errors.learner_phone }}
                              </p>
                            </div>
                            <div>
                              <Label for="learner-email">Email</Label>
                              <Input
                                v-model="form.learner_email"
                                id="learner-email"
                                type="email"
                                :class="{ 'border-destructive': form.errors.learner_email }"
                              />
                              <p v-if="form.errors.learner_email" class="text-sm text-destructive mt-1">
                                {{ form.errors.learner_email }}
                              </p>
                            </div>
                            <div>
                              <Label for="learner-dob">Date of Birth</Label>
                              <Input
                                v-model="form.learner_dob"
                                id="learner-dob"
                                type="date"
                                :class="{ 'border-destructive': form.errors.learner_dob }"
                              />
                              <p v-if="form.errors.learner_dob" class="text-sm text-destructive mt-1">
                                {{ form.errors.learner_dob }}
                              </p>
                            </div>
                          </div>
                        </CardContent>
                      </Card>
                    </div>
                  </CardContent>
                </Card>

                <!-- Promo Code -->
                <div class="flex items-center space-x-4 pt-6 border-t">
                  <Input
                    v-model="promoCode"
                    type="text"
                    placeholder="Enter promo code"
                    class="flex-1"
                  />
                  <Button @click="applyPromoCode" type="button" variant="secondary">
                    Apply
                  </Button>
                </div>
                <p v-if="promoDiscount" class="text-sm text-green-600">
                  Promo code applied! You saved £{{ promoDiscount }}
                </p>

                <!-- Pricing Summary -->
                <Alert>
                  <AlertTitle class="text-lg">Pricing Summary</AlertTitle>
                  <AlertDescription>
                    <div class="space-y-3 mt-4 w-full">
                      <div class="flex items-center justify-between">
                        <span>{{ package?.name }} ({{ package?.lessons_count }} lessons)</span>
                        <span class="font-medium">{{ package?.formatted_total_price || '0.00' }}</span>
                      </div>
                      <div class="flex items-center justify-between">
                        <span>Booking fee</span>
                        <span class="font-medium">£{{ pricing?.booking_fee || '19.99' }}</span>
                      </div>
                      <div class="flex items-center justify-between">
                        <span>Digital Fee</span>
                        <span class="font-medium">{{ package?.digital_fee || '3.99' }}</span>
                      </div>
                      <div v-if="promoDiscount" class="flex items-center justify-between text-green-600">
                        <span>Promo discount</span>
                        <span class="font-medium">-£{{ promoDiscount }}.00</span>
                      </div>
                      <Separator />
                      <div class="flex items-center justify-between">
                        <span class="text-lg font-semibold">Total</span>
                        <span class="text-xl font-bold">{{ package?.total_price || '0.00' }}</span>
                      </div>
                      <div class="text-sm text-muted-foreground">
                        Or pay <span class="font-semibold">{{ package?.weekly_payment || '0.00' }} weekly</span>
                      </div>
                    </div>
                  </AlertDescription>
                </Alert>

                <!-- Form Actions -->
                <div class="flex items-center justify-between pt-6 border-t">
                  <Link :href="step4({ uuid: uuid }).url">
                    <Button variant="outline" class="cursor-pointer">
                      <ArrowLeft class="mr-2 h-4 w-4" />
                      Back
                    </Button>
                  </Link>

                  <Button type="submit" :disabled="form.processing" class="cursor-pointer">
                    <Spinner v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Confirm & Continue to Payment
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

    <!-- Sonner Toast -->
    <Sonner position="top-right" rich-colors />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, Link, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Separator } from '@/components/ui/separator'
import { Spinner } from '@/components/ui/spinner'
import { toast } from '@/components/ui/toast'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import { step1, step2, step3, step4 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step5'
import { ArrowLeft, ArrowRight, Car, MapPin, Star } from 'lucide-vue-next'

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
const promoCode = ref('')
const promoDiscount = ref(0)
const bookingFee = 5

// Local ref for checkbox to handle reactivity
const isBookingForSomeoneElse = ref(form.booking_for_someone_else)

// Watch and sync the local ref with the form
watch(isBookingForSomeoneElse, (newValue) => {
  form.booking_for_someone_else = newValue
})

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
    toast({ title: 'Promo code applied!' })
  } else if (promoCode.value.toLowerCase() === 'save20') {
    promoDiscount.value = 20
    toast({ title: 'Promo code applied!' })
  } else {
    promoDiscount.value = 0
    toast({ title: 'Invalid promo code', variant: 'destructive' })
  }
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
        toast({ title: 'Saved' })
      },
      onError: () => {
        // Silently handle errors for auto-save
      }
    })
  }, 1500) // 1.5 second debounce
}

// Watch for changes and auto-save
// Note: We don't auto-save learner fields to avoid premature validation
// Learner fields are only validated on explicit form submission
watch(isBookingForSomeoneElse, (newValue) => {
  // Sync the checkbox state but don't auto-save to avoid validation
  // Validation will happen on explicit submit
})

// Removed auto-save watchers for learner fields
// These fields will only be validated when user clicks "Confirm & Continue to Payment"
// This prevents showing validation errors while user is still filling out the form

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
