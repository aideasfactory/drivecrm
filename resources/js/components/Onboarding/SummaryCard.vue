<template>
  <div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900">
          {{ title }}
        </h3>
        <button
          v-if="editable"
          type="button"
          @click="$emit('edit')"
          class="text-blue-600 hover:text-blue-500 text-sm font-medium"
        >
          Edit
        </button>
      </div>
    </div>
    
    <div class="px-6 py-4">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  editable: {
    type: Boolean,
    default: false
  }
})

defineEmits(['edit'])
</script>