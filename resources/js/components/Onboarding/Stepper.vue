<template>
  <nav aria-label="Progress">
    <ol role="list" class="border border-gray-300 rounded-md divide-y divide-gray-300 md:flex md:divide-y-0">
      <li v-for="(step, stepIdx) in steps" :key="step.name" class="relative md:flex-1 md:flex">
        <div 
          :class="[
            stepIdx <= steps.length - 1 ? 'group flex items-center w-full' : '',
            getStepClasses(step.number)
          ]"
        >
          <span class="px-6 py-4 flex items-center text-sm font-medium">
            <span 
              :class="[
                'flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full',
                getStepBadgeClasses(step.number)
              ]"
            >
              <CheckIcon 
                v-if="isStepCompleted(step.number)"
                class="w-6 h-6 text-white" 
                aria-hidden="true" 
              />
              <span v-else :class="getStepNumberClasses(step.number)">
                {{ step.number }}
              </span>
            </span>
            <span :class="['ml-4', getStepTextClasses(step.number)]">
              {{ step.name }}
            </span>
          </span>
        </div>

        <div v-if="stepIdx !== steps.length - 1" class="hidden md:block absolute top-0 right-0 h-full w-5" aria-hidden="true">
          <svg class="h-full w-full text-gray-300" viewBox="0 0 22 80" fill="none" preserveAspectRatio="none">
            <path d="M0 -2L20 40l-20 42" vector-effect="non-scaling-stroke" stroke="currentcolor" stroke-linejoin="round" />
          </svg>
        </div>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { CheckIcon } from '@heroicons/vue/24/solid'

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

function isStepCurrent(stepNumber) {
  return stepNumber === props.currentStep
}

function isStepUpcoming(stepNumber) {
  return stepNumber > props.currentStep
}

function getStepClasses(stepNumber) {
  if (isStepCompleted(stepNumber)) {
    return 'text-blue-600 hover:text-blue-900'
  }
  if (isStepCurrent(stepNumber)) {
    return 'text-blue-600'
  }
  return 'text-gray-500'
}

function getStepBadgeClasses(stepNumber) {
  if (isStepCompleted(stepNumber)) {
    return 'bg-blue-600 group-hover:bg-blue-800'
  }
  if (isStepCurrent(stepNumber)) {
    return 'border-2 border-blue-600 bg-white'
  }
  return 'border-2 border-gray-300 bg-white'
}

function getStepNumberClasses(stepNumber) {
  if (isStepCurrent(stepNumber)) {
    return 'text-blue-600'
  }
  return 'text-gray-500'
}

function getStepTextClasses(stepNumber) {
  if (isStepCompleted(stepNumber)) {
    return 'text-gray-900'
  }
  if (isStepCurrent(stepNumber)) {
    return 'text-blue-600'
  }
  return 'text-gray-500'
}
</script>