<template>
  <div class="lg:col-span-1">
    <!-- Instructor Card (if provided) -->
    <div v-if="showInstructorCard" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
      <slot name="instructor-card"></slot>
    </div>

    <!-- Main School Info Card -->
    <OnboardingLeftSidebar>
      <template #extra-content>
        <slot name="extra-content"></slot>
      </template>
    </OnboardingLeftSidebar>
  </div>
</template>

<script setup>
import OnboardingLeftSidebar from './OnboardingLeftSidebar.vue'

defineProps({
  showInstructorCard: {
    type: Boolean,
    default: false
  }
})
</script>