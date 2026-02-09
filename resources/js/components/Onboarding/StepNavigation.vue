<template>
  <div class="flex justify-between items-center pt-6 border-t border-gray-200">
    <button
      v-if="showPrevious"
      type="button"
      @click="goToPreviousStep"
      class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
    >
      <ChevronLeftIcon class="w-5 h-5 mr-2" />
      Previous
    </button>
    <div v-else></div>

    <StepperMini 
      :current-step="currentStep"
      :completed-steps="completedSteps"
      :steps="miniSteps"
      class="hidden md:block"
    />

    <button
      type="submit"
      :disabled="loading"
      class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <template v-if="loading">
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        {{ loadingText }}
      </template>
      <template v-else>
        {{ nextButtonText }}
        <ChevronRightIcon v-if="!isLastStep" class="w-5 h-5 ml-2" />
      </template>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'
import StepperMini from './StepperMini.vue'

const props = defineProps({
  currentStep: {
    type: Number,
    required: true
  },
  completedSteps: {
    type: Array,
    required: true
  },
  uuid: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  loadingText: {
    type: String,
    default: 'Processing...'
  }
})

const showPrevious = computed(() => props.currentStep > 1)
const isLastStep = computed(() => props.currentStep === 6)

const nextButtonText = computed(() => {
  if (isLastStep.value) return 'Complete Booking'
  return 'Continue'
})

const miniSteps = computed(() => [
  { number: 1 }, { number: 2 }, { number: 3 }, { number: 4 }, { number: 5 }, { number: 6 }
])

function goToPreviousStep() {
  if (props.currentStep > 1) {
    const previousStep = props.currentStep - 1
    router.get(`/onboarding/${props.uuid}/step/${previousStep}`)
  }
}
</script>