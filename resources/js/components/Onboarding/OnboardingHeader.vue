<template>
  <header class="sticky top-0 bg-white border-b border-gray-200 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <div class="flex-shrink-0 min-w-[200px]">
          <span class="flex items-center cursor-pointer">
            <i class="fa-solid fa-car text-blue-600 text-2xl mr-2"></i>
            <span class="text-xl font-bold text-gray-900">DRIVE</span>
          </span>
        </div>
        
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
                :class="[
                  'flex items-center text-sm cursor-pointer hover:opacity-80 transition-opacity',
                  getStepStatus(index + 1) === 'completed' ? 'text-green-600' : 
                  getStepStatus(index + 1) === 'current' ? 'text-gray-900' : 
                  'text-gray-400'
                ]"
              >
                <span 
                  :class="[
                    'rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-2',
                    getStepStatus(index + 1) === 'completed' ? 'bg-green-500 text-white' : 
                    getStepStatus(index + 1) === 'current' ? 'bg-blue-600 text-white' : 
                    'bg-gray-300 text-gray-500'
                  ]"
                >
                  {{ getStepStatus(index + 1) === 'completed' ? '✓' : index + 1 }}
                </span>
                <span :class="getStepStatus(index + 1) === 'current' ? 'font-medium' : ''">
                  {{ step }}
                </span>
              </Link>
              <div
                v-else
                :class="[
                  'flex items-center text-sm cursor-not-allowed opacity-60',
                  getStepStatus(index + 1) === 'completed' ? 'text-green-600' : 
                  getStepStatus(index + 1) === 'current' ? 'text-gray-900' : 
                  'text-gray-400'
                ]"
              >
                <span 
                  :class="[
                    'rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-2',
                    getStepStatus(index + 1) === 'completed' ? 'bg-green-500 text-white' : 
                    getStepStatus(index + 1) === 'current' ? 'bg-blue-600 text-white' : 
                    'bg-gray-300 text-gray-500'
                  ]"
                >
                  {{ getStepStatus(index + 1) === 'completed' ? '✓' : index + 1 }}
                </span>
                <span :class="getStepStatus(index + 1) === 'current' ? 'font-medium' : ''">
                  {{ step }}
                </span>
              </div>
              <div 
                v-if="index < steps.length - 1"
                :class="[
                  'w-12 h-px mx-3',
                  getStepStatus(index + 1) === 'completed' ? 'bg-green-500' : 'bg-gray-300'
                ]"
              ></div>
            </div>
          </div>
        </div>

        <div class="lg:hidden flex items-center justify-center flex-1">
          <div class="flex items-center space-x-2">
            <div class="w-24 bg-gray-200 rounded-full h-2">
              <div 
                class="bg-blue-600 h-2 rounded-full" 
                :style="`width: ${(currentStep / totalSteps) * 100}%`"
              ></div>
            </div>
            <span class="text-sm text-gray-600">{{ currentStep }}/{{ totalSteps }}</span>
          </div>
        </div>
        
        <div class="flex-shrink-0 min-w-[200px]"></div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { step1, step2, step3, step4, step5, step6 } from '@/routes/onboarding'

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

const getStepStatus = (stepNumber) => {
  if (stepNumber < props.currentStep) return 'completed'
  if (stepNumber === props.currentStep) return 'current'
  return 'pending'
}

const canNavigateToStep = (stepNumber) => {
  // Can only navigate to steps that have been reached before
  return stepNumber <= props.maxStepReached
}

const getStepRoute = (stepNumber) => {
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