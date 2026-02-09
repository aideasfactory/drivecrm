<script setup lang="ts">
import { Toaster } from 'vue-sonner'

const props = withDefaults(
  defineProps<{
    theme?: 'light' | 'dark' | 'system'
    position?: 'top-left' | 'top-center' | 'top-right' | 'bottom-left' | 'bottom-center' | 'bottom-right'
    expand?: boolean
    richColors?: boolean
    closeButton?: boolean
  }>(),
  {
    theme: 'light',
    position: 'top-right',
    expand: false,
    richColors: false,
    closeButton: false,
  }
)
</script>

<template>
  <Toaster
    :theme="theme"
    :position="position"
    :expand="expand"
    :rich-colors="richColors"
    :close-button="closeButton"
  />
</template>
