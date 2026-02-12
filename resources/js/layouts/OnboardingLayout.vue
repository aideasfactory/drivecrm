<template>
  <div class="min-h-screen">
    <Card class="rounded-none border-x-0 border-t-0">
      <CardHeader>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center">
            <CardTitle class="text-2xl">Driver Training Onboarding</CardTitle>
            <CardDescription v-if="enquiry" class="text-sm">
              Session ID: {{ enquiry.id.slice(0, 8) }}
            </CardDescription>
          </div>
        </div>
      </CardHeader>
    </Card>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <Stepper
        :current-step="currentStep"
        :completed-steps="completedSteps"
        :steps="steps"
      />

      <!-- Flash Messages -->
      <div v-if="flash.error || flash.success" class="mt-6">
        <Alert v-if="flash.error" variant="destructive">
          <AlertDescription>{{ flash.error }}</AlertDescription>
        </Alert>
        <Alert v-if="flash.success">
          <AlertDescription>{{ flash.success }}</AlertDescription>
        </Alert>
      </div>

      <main class="mt-8">
        <slot />
      </main>
    </div>

    <footer class="mt-auto">
      <Card class="rounded-none border-x-0 border-b-0">
        <CardContent class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <p class="text-center text-sm">
            © {{ new Date().getFullYear() }} Your Driving School. All rights reserved.
          </p>
        </CardContent>
      </Card>
    </footer>

    <Toaster />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Toaster } from '@/components/ui/toast'
import Stepper from '@/components/Onboarding/Stepper.vue'

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
