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
              <Badge v-if="discount" variant="destructive" class="w-fit text-sm mt-2">
                {{ discount.percentage }}% off &mdash; {{ discount.label }}
              </Badge>
            </CardHeader>

            <CardContent class="pt-6">
              <form @submit.prevent="submit">
                <div v-if="packages && packages.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                  <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="relative"
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
                          'relative transition-all duration-200 h-full',
                          form.package_id === pkg.id
                            ? 'ring-2 ring-primary border-primary'
                            : 'hover:border-muted-foreground/30',
                          pkg.promoted
                            ? 'bg-gradient-to-b from-red-400/5 via-red-500/10 to-red-600/20'
                            : ''
                        ]"
                      >
                        <!-- Promoted ribbon -->
                        <div
                          v-if="pkg.promoted"
                          class="absolute top-0 right-0 z-10"
                        >
                          <div class="bg-gradient-to-r from-amber-500 to-orange-600 text-white text-xs font-bold px-3 py-1 rounded-bl-lg rounded-tr-xl flex items-center gap-1 shadow-md">
                            <Flame class="h-3 w-3" />
                            Popular
                          </div>
                        </div>

                        <CardHeader>
                          <div class="text-center pt-2">
                            <div class="mb-3 flex justify-center">
                              <div :class="[
                                'rounded-full p-3',
                                pkg.promoted ? 'bg-red-500/10' : 'bg-muted'
                              ]">
                                <component
                                  :is="getPackageIcon(pkg)"
                                  :class="[
                                    'h-8 w-8',
                                    pkg.promoted ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'
                                  ]"
                                />
                              </div>
                            </div>
                            <CardTitle class="text-lg">{{ pkg.name }}</CardTitle>
                            <CardDescription class="text-sm mt-1">
                              {{ pkg.lessons_count }} lessons
                            </CardDescription>
                          </div>
                        </CardHeader>

                        <CardContent class="text-center">
                          <div class="mb-4">
                            <template v-if="discount">
                              <div class="text-sm text-muted-foreground line-through">{{ pkg.formatted_total_price }}</div>
                              <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ getDiscountedPrice(pkg.formatted_total_price) }}</div>
                              <div class="text-xs text-muted-foreground mt-1">
                                {{ getDiscountedPrice(pkg.formatted_lesson_price) }} per hour
                              </div>
                            </template>
                            <template v-else>
                              <div class="text-3xl font-bold">{{ pkg.formatted_total_price }}</div>
                              <div class="text-xs text-muted-foreground mt-1">
                                {{ pkg.formatted_lesson_price }} per hour
                              </div>
                            </template>
                          </div>
                          <p class="text-xs text-muted-foreground leading-relaxed">{{ pkg.description }}</p>
                        </CardContent>

                        <CardFooter class="justify-center">
                          <Button
                            type="button"
                            :variant="form.package_id === pkg.id ? 'default' : 'outline'"
                            class="w-full"
                            size="sm"
                          >
                            <Check v-if="form.package_id === pkg.id" class="mr-1 h-4 w-4" />
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
import { ArrowLeft, ArrowRight, Car, GraduationCap, Trophy, Rocket, Check, Flame, AlertTriangle, Info } from 'lucide-vue-next'

const props = defineProps({
  uuid: String,
  currentStep: Number,
  totalSteps: Number,
  stepData: Object,
  postcode: String,
  selectedInstructor: Object,
  packages: Array,
  maxStepReached: { type: Number, default: 3 },
  discount: {
    type: Object as () => { id: string; label: string; percentage: number } | null,
    default: null
  }
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

// Calculate discounted price string from a formatted price like "£500.00"
function getDiscountedPrice(formattedPrice: string): string {
  if (!props.discount) return formattedPrice
  const price = parseFloat(formattedPrice.replace('£', '').replace(',', ''))
  const discounted = price * (1 - props.discount.percentage / 100)
  return '£' + discounted.toFixed(2)
}

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
