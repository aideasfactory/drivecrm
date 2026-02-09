<template>
  <div class="lg:col-span-1">
    <!-- Instructor Card (if provided) -->
    <Card v-if="showInstructorCard" class="mb-6">
      <CardContent class="pt-6">
        <slot name="instructor-card"></slot>
      </CardContent>
    </Card>

    <!-- Main School Info Card -->
    <OnboardingLeftSidebar>
      <template #extra-content>
        <slot name="extra-content"></slot>
      </template>
    </OnboardingLeftSidebar>
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import OnboardingLeftSidebar from './OnboardingLeftSidebar.vue'

defineProps({
  showInstructorCard: {
    type: Boolean,
    default: false
  }
})
</script>
