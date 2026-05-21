<template>
  <button
    type="button"
    @click="open"
    class="text-sm cursor-pointer hover:underline bg-transparent border-0 p-0"
  >
    Cookie preferences
  </button>
</template>

<script setup lang="ts">
import { openCookiePreferences } from '@/lib/cookieConsent'

const open = () => openCookiePreferences()
</script>
