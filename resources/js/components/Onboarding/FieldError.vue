<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'

defineProps<{
  id: string
  message?: string
}>()
</script>

<template>
  <p :id="id" aria-live="polite" class="mt-1 flex items-start text-sm text-destructive">
    <template v-if="message">
      <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
      <span>{{ message }}</span>
    </template>
  </p>
</template>
