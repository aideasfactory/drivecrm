<template>
  <header class="sticky top-0 z-50">
    <Card class="rounded-none border-b border-t-0 border-x-0">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 w-full">
          <div class="flex-shrink-0 min-w-[200px]">
            <span class="flex items-center cursor-pointer">
              <Car class="h-6 w-6 mr-2" />
              <span class="text-xl font-bold">DRIVE</span>
            </span>
          </div>

          <!-- Desktop Stepper -->
          <div class="hidden lg:flex items-center justify-center flex-1 px-8">
            <div class="flex items-center">
              <div
                v-for="(step, index) in steps"
                :key="index"
                class="flex items-center"
              >
                <Link
                  v-if="canNavigateToStep(index + 1)"
                  :href="getStepRoute(index + 1)"
                  class="flex items-center text-sm cursor-pointer hover:opacity-80 transition-opacity"
                >
                  <Badge
                    :variant="getStepStatus(index + 1) === 'completed' ? 'default' :
                             getStepStatus(index + 1) === 'current' ? 'default' :
                             'secondary'"
                    :class="[
                      'rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-2',
                      getStepStatus(index + 1) === 'completed' ? 'bg-red-600 text-white hover:bg-red-700' : ''
                    ]"
                  >
                    {{ getStepStatus(index + 1) === 'completed' ? '✓' : index + 1 }}
                  </Badge>
                  <span :class="getStepStatus(index + 1) === 'current' ? 'font-medium' : ''">
                    {{ step }}
                  </span>
                </Link>
                <div
                  v-else
                  class="flex items-center text-sm cursor-not-allowed opacity-60"
                >
                  <Badge
                    :variant="getStepStatus(index + 1) === 'completed' ? 'default' :
                             getStepStatus(index + 1) === 'current' ? 'default' :
                             'secondary'"
                    :class="[
                      'rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-2',
                      getStepStatus(index + 1) === 'completed' ? 'bg-red-600 text-white hover:bg-red-700' : ''
                    ]"
                  >
                    {{ getStepStatus(index + 1) === 'completed' ? '✓' : index + 1 }}
                  </Badge>
                  <span :class="getStepStatus(index + 1) === 'current' ? 'font-medium' : ''">
                    {{ step }}
                  </span>
                </div>
                <Separator
                  v-if="index < steps.length - 1"
                  class="w-12 mx-3"
                  orientation="horizontal"
                />
              </div>
            </div>
          </div>

          <!-- Mobile Progress -->
          <div class="lg:hidden flex items-center justify-center flex-1">
            <div class="flex items-center space-x-2">
              <div class="w-24 h-2 bg-secondary rounded-full overflow-hidden">
                <div
                  class="h-2 bg-primary rounded-full transition-all duration-300"
                  :style="`width: ${(currentStep / totalSteps) * 100}%`"
                ></div>
              </div>
              <span class="text-sm font-medium">{{ currentStep }}/{{ totalSteps }}</span>
            </div>
          </div>

          <div class="flex-shrink-0 min-w-[200px]"></div>
        </div>
      </div>
    </Card>
  </header>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { step1, step2, step3, step4, step5, step6 } from '@/routes/onboarding'
import { Car } from 'lucide-vue-next'

const props = defineProps({
  currentStep: {
    type: Number,
    required: true
  },
  totalSteps: {
    type: Number,
    default: 6
  },
  maxStepReached: {
    type: Number,
    default: 1
  }
})

const page = usePage()

const steps = ['Details', 'Instructor', 'Package', 'Schedule', 'Review', 'Payment']

const getStepStatus = (stepNumber: number) => {
  if (stepNumber < props.currentStep) return 'completed'
  if (stepNumber === props.currentStep) return 'current'
  return 'pending'
}

const canNavigateToStep = (stepNumber: number) => {
  // Can only navigate to steps that have been reached before
  return stepNumber <= props.maxStepReached
}

const getStepRoute = (stepNumber: number) => {
  const uuid = page.props.enquiry?.id || page.props.uuid
  if (!uuid) return '#'

  // Use Wayfinder route functions
  switch(stepNumber) {
    case 1:
      return step1({ uuid }).url
    case 2:
      return step2({ uuid }).url
    case 3:
      return step3({ uuid }).url
    case 4:
      return step4({ uuid }).url
    case 5:
      return step5({ uuid }).url
    case 6:
      return step6({ uuid }).url
    default:
      return '#'
  }
}
</script>
