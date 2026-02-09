<template>
  <div class="min-h-screen">
    <OnboardingHeader :current-step="3" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <OnboardingLeftSidebar>
          <template #extra-content>
            <Separator class="my-6" />
            <div>
              <h4 class="font-semibold mb-3">Your selection</h4>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-muted-foreground">Area:</span>
                  <span class="font-medium">{{ postcode }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-muted-foreground">Instructor:</span>
                  <span class="font-medium">{{ selectedInstructor?.name || 'None selected' }}</span>
                </div>
              </div>
            </div>
          </template>
        </OnboardingLeftSidebar>

        <!-- Main Content -->
        <div class="lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-3xl">Pick a package</CardTitle>
              <CardDescription class="text-lg">
                Choose the lesson package that works best for you
              </CardDescription>
            </CardHeader>

            <CardContent>
              <form @submit.prevent="submit">
                <div v-if="packages && packages.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                  <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="relative"
                    :class="{ 'transform md:scale-105 md:-mt-4': pkg.promoted }"
                  >
                    <!-- Promoted Badge -->
                    <Badge
                      v-if="pkg.promoted"
                      variant="destructive"
                      class="absolute -top-3 left-1/2 transform -translate-x-1/2 z-10"
                    >
                      Offer
                    </Badge>

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
                        'block cursor-pointer transition-all duration-200 h-full border-2 rounded-lg',
                        pkg.promoted
                          ? 'p-8 border-destructive hover:border-destructive/80 peer-checked:border-destructive peer-checked:bg-destructive peer-checked:text-destructive-foreground'
                          : 'p-6 hover:border-primary peer-checked:border-primary peer-checked:bg-primary/5'
                      ]"
                    >
                      <div class="text-center">
                        <div class="mb-4">
                          <i
                            :class="[
                              'fa-solid text-3xl',
                              getPackageIcon(pkg),
                              pkg.promoted ? 'text-white peer-checked:text-white' : 'text-muted-foreground'
                            ]"
                          ></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">{{ pkg.name }}</h3>
                        <div class="flex-row items-baseline justify-center mb-3">
                          <span class="text-3xl font-bold">{{ pkg.formatted_total_price }}</span>
                          <span class="text-lg ml-2 text-muted-foreground">{{ pkg.lessons_count }} lessons</span>
                        </div>
                        <div class="text-sm mb-2">
                          <span class="font-medium">{{ pkg.formatted_lesson_price }}</span> per hour
                        </div>
                        <p class="text-sm text-muted-foreground">{{ pkg.description }}</p>
                      </div>
                    </label>
                  </div>
                </div>

                <div v-else class="text-center py-12 mb-8">
                  <i class="fa-solid fa-exclamation-triangle text-4xl mb-4 text-muted-foreground"></i>
                  <p class="text-muted-foreground">No packages available for the selected instructor.</p>
                  <Link
                    :href="step2({ uuid: uuid }).url"
                    class="font-medium mt-4 inline-block hover:underline"
                  >
                    Go back and select another instructor
                  </Link>
                </div>

                <Alert v-if="packages && packages.length > 0" variant="default" class="mb-8">
                  <i class="fa-solid fa-info-circle mr-2"></i>
                  <AlertTitle>Pricing Note</AlertTitle>
                  <AlertDescription>
                    Prices may vary by area and instructor. Your final price will be confirmed before payment.
                  </AlertDescription>
                </Alert>

                <div class="flex justify-between items-center">
                  <Link :href="step2({ uuid: uuid }).url">
                    <Button variant="outline" class="cursor-pointer">
                      <ArrowLeft class="mr-2 h-4 w-4" />
                      Back
                    </Button>
                  </Link>
                  <div class="flex items-center space-x-4">
                    <Button
                      v-if="packages && packages.length > 0"
                      type="submit"
                      :disabled="!form.package_id || form.processing"
                      class="cursor-pointer"
                    >
                      <Spinner v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                      Next
                      <ArrowRight v-if="!form.processing" class="ml-2 h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </main>

    <footer>
      <Card class="rounded-none border-x-0 border-b-0 mt-16">
        <CardContent class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-sm">
              © 2024 DRIVE Driving School
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

    <!-- Sonner Toast -->
    <Sonner position="top-right" rich-colors />
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { usePage, useForm, Link } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Spinner } from '@/components/ui/spinner'
import { Sonner, toast } from '@/components/ui/sonner'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import { step2 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step3'
import { ArrowLeft, ArrowRight } from 'lucide-vue-next'

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

const form = useForm({
  package_id: props.stepData?.package_id || null
})

const postcode = computed(() => {
  return props.postcode || page.props.postcode || 'Not provided'
})

const selectedInstructor = computed(() => {
  return props.selectedInstructor || null
})

// Get appropriate icon for package
function getPackageIcon(pkg: any) {
  if (pkg.hours <= 2) return 'fa-car'
  if (pkg.hours <= 10) return 'fa-graduation-cap'
  if (pkg.hours <= 20) return 'fa-trophy'
  return 'fa-rocket'
}

// Show toast when package is selected
watch(() => form.package_id, (newValue) => {
  if (newValue) {
    toast.success('Package selected', {
      description: 'Your package has been saved'
    })
  }
})

function submit() {
  form.post(store({ uuid: props.uuid }).url)
}
</script>
