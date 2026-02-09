<template>
  <div class="bg-white shadow rounded-lg">
    <div v-if="title || description" class="px-6 py-4 border-b border-gray-200">
      <h3 v-if="title" class="text-lg font-medium text-gray-900">
        {{ title }}
      </h3>
      <p v-if="description" class="mt-1 text-sm text-gray-600">
        {{ description }}
      </p>
    </div>
    
    <div class="px-6 py-4">
      <slot />
    </div>
    
    <div v-if="$slots.actions" class="px-6 py-4 bg-gray-50 rounded-b-lg border-t border-gray-200">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: ''
  },
  description: {
    type: String,
    default: ''
  }
})
</script>