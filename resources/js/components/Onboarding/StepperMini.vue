<template>
  <div class="flex items-center space-x-2">
    <div v-for="(step, stepIdx) in steps" :key="step.number" class="flex items-center">
      <div 
        :class="[
          'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
          getStepClasses(step.number)
        ]"
      >
        <CheckIcon 
          v-if="isStepCompleted(step.number)"
          class="w-5 h-5 text-white" 
        />
        <span v-else>{{ step.number }}</span>
      </div>
      
      <ChevronRightIcon 
        v-if="stepIdx < steps.length - 1" 
        class="w-4 h-4 text-gray-400 mx-1" 
      />
    </div>
  </div>
</template>

<script setup>
import { CheckIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  currentStep: {
    type: Number,
    required: true
  },
  completedSteps: {
    type: Array,
    required: true
  },
  steps: {
    type: Array,
    required: true
  }
})

function isStepCompleted(stepNumber) {
  return props.completedSteps.includes(stepNumber)
}

function getStepClasses(stepNumber) {
  if (isStepCompleted(stepNumber)) {
    return 'bg-blue-600 text-white'
  }
  if (stepNumber === props.currentStep) {
    return 'bg-blue-100 text-blue-600 border-2 border-blue-600'
  }
  return 'bg-gray-200 text-gray-500'
}
</script>