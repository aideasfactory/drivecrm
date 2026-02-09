<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Driver Training Onboarding</h1>
          </div>
          <div v-if="enquiry" class="text-sm text-gray-500">
            Session ID: {{ enquiry.id.slice(0, 8) }}
          </div>
        </div>
      </div>
    </header>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <Stepper 
        :current-step="currentStep" 
        :completed-steps="completedSteps"
        :steps="steps"
      />

      <div v-if="flash.error || flash.success" class="mt-6">
        <div v-if="flash.error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
          {{ flash.error }}
        </div>
        <div v-if="flash.success" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
          {{ flash.success }}
        </div>
      </div>

      <main class="mt-8">
        <slot />
      </main>
    </div>

    <footer class="mt-auto bg-white border-t border-gray-200">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <p class="text-center text-sm text-gray-500">
          © {{ new Date().getFullYear() }} Your Driving School. All rights reserved.
        </p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Stepper from '@/Components/Onboarding/Stepper.vue'

const page = usePage()

const props = defineProps({
  currentStep: {
    type: Number,
    required: true
  }
})

const enquiry = computed(() => page.props.enquiry)
const flash = computed(() => page.props.flash || {})

const completedSteps = computed(() => {
  const completed = []
  for (let i = 1; i < props.currentStep; i++) {
    completed.push(i)
  }
  return completed
})

const steps = [
  { number: 1, name: 'Postcode' },
  { number: 2, name: 'Instructor' },
  { number: 3, name: 'Package' },
  { number: 4, name: 'Schedule' },
  { number: 5, name: 'Your Details' },
  { number: 6, name: 'Review & Book' }
]
</script>