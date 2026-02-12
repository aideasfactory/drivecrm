<template>
  <div class="min-h-screen">
    <OnboardingHeader :current-step="3" :total-steps="6" :max-step-reached="maxStepReached" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="order-2 lg:order-1">
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
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 order-1 lg:order-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-3xl">Pick a package</CardTitle>
              <CardDescription class="text-lg">
                Choose the lesson package that works best for you
              </CardDescription>
            </CardHeader>

            <CardContent class="pt-6">
              <form @submit.prevent="submit">
                <div v-if="packages && packages.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                  <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="relative"
                    :class="{ 'md:scale-105 md:-mt-4': pkg.promoted }"
                  >
                    <input
                      type="radio"
                      :id="`package-${pkg.id}`"
                      v-model="form.package_id"
                      :value="pkg.id"
                      class="sr-only peer"
                    >
                    <label
                      :for="`package-${pkg.id}`"
                      class="block cursor-pointer h-full"
                    >
                      <Card
                        :class="[
                          'relative transition-all duration-200 h-full overflow-hidden',
                          pkg.promoted
                            ? 'border-destructive hover:border-destructive/80 peer-checked:ring-4 peer-checked:ring-destructive peer-checked:border-destructive'
                            : 'hover:border-primary peer-checked:ring-4 peer-checked:ring-primary peer-checked:border-primary'
                        ]"
                      >
                        <!-- Selected Indicator -->
                        <div
                          v-if="form.package_id === pkg.id"
                          :class="[
                            'absolute top-4 right-4 z-20 rounded-full p-1',
                            pkg.promoted ? 'bg-destructive' : 'bg-primary'
                          ]"
                        >
                          <CheckCircle2 class="h-5 w-5 text-black" />
                        </div>

                        <CardHeader :class="pkg.promoted ? 'bg-destructive text-destructive-foreground' : ''">
                          

                          <div class="text-center pt-2">
                            <div class="mb-4 flex justify-center">
                              <component
                                :is="getPackageIcon(pkg)"
                                :class="[
                                  'h-12 w-12',
                                  pkg.promoted ? 'text-destructive-foreground' : 'text-muted-foreground'
                                ]"
                              />
                            </div>
                            <CardTitle class="text-xl mb-2">{{ pkg.name }}</CardTitle>
                            <CardDescription
                              :class="[
                                'text-lg',
                                pkg.promoted ? 'text-destructive-foreground/80' : ''
                              ]"
                            >
                              {{ pkg.lessons_count }} lessons
                            </CardDescription>
                          </div>
                        </CardHeader>

                        <CardContent class="pt-6 text-center">
                          <div class="mb-4">
                            <div class="text-4xl font-bold mb-1">{{ pkg.formatted_total_price }}</div>
                            <div class="text-sm text-muted-foreground">
                              {{ pkg.formatted_lesson_price }} per hour
                            </div>
                          </div>
                          <p class="text-sm text-muted-foreground">{{ pkg.description }}</p>
                        </CardContent>

                        <CardFooter
                          :class="[
                            'justify-center',
                            pkg.promoted ? '' : ''
                          ]"
                        >
                          <Button
                            type="button"
                            :variant="form.package_id === pkg.id ? 'default' : 'outline'"
                            :class="pkg.promoted && form.package_id === pkg.id ? 'bg-destructive hover:bg-destructive/90' : ''"
                            class="w-full"
                          >
                            {{ form.package_id === pkg.id ? 'Selected' : 'Select Package' }}
                          </Button>
                        </CardFooter>
                      </Card>
                    </label>
                  </div>
                </div>

                <div v-else class="text-center py-12 mb-8">
                  <AlertTriangle class="h-12 w-12 mb-4 text-muted-foreground mx-auto" />
                  <p class="text-muted-foreground">No packages available for the selected instructor.</p>
                  <Link
                    :href="step2({ uuid: uuid }).url"
                    class="font-medium mt-4 inline-block hover:underline"
                  >
                    Go back and select another instructor
                  </Link>
                </div>

                <Alert v-if="packages && packages.length > 0" variant="default" class="mb-8">
                  <Info class="h-4 w-4" />
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
                      Next
                      <Spinner v-if="form.processing" class="ml-2 h-4 w-4 animate-spin" />
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

    <OnboardingFooter copyright-text="© 2024 DRIVE Driving School" />

    <!-- Sonner Toast -->
    <Sonner position="top-right" rich-colors />
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { usePage, useForm, Link } from '@inertiajs/vue3'
import { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Spinner } from '@/components/ui/spinner'
import { toast } from '@/components/ui/toast'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingLeftSidebar from '@/components/Onboarding/OnboardingLeftSidebar.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import { step2 } from '@/routes/onboarding'
import { store } from '@/routes/onboarding/step3'
import { ArrowLeft, ArrowRight, Car, GraduationCap, Trophy, Rocket, CheckCircle2, AlertTriangle, Info } from 'lucide-vue-next'

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

// Get appropriate icon component for package
function getPackageIcon(pkg: any) {
  if (pkg.hours <= 2) return Car
  if (pkg.hours <= 10) return GraduationCap
  if (pkg.hours <= 20) return Trophy
  return Rocket
}

// Show toast when package is selected
watch(() => form.package_id, (newValue) => {
  if (newValue) {
    toast({ title: 'Package selected', description: 'Your package has been saved' })
  }
})

function submit() {
  form.post(store({ uuid: props.uuid }).url)
}
</script>
