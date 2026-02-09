<template>
  <footer :class="marginClass">
    <Card class="rounded-none border-x-0 border-b-0">
      <CardContent class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
          <!-- Copyright -->
          <div class="text-sm">
            {{ copyrightText }}
          </div>

          <!-- Links -->
          <div class="flex items-center space-x-6">
            <span
              v-for="(link, index) in links"
              :key="index"
              class="text-sm cursor-pointer hover:underline"
            >
              {{ link }}
            </span>
          </div>
 
        </div>
      </CardContent>
    </Card>
  </footer>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { CreditCard } from 'lucide-vue-next'

interface Props {
  copyrightText?: string
  links?: string[]
  marginClass?: string
}

withDefaults(defineProps<Props>(), {
  copyrightText: '© 2024 DRIVE Academy',
  links: () => ['Terms & Conditions', 'Privacy Policy', 'Cookies'],
  marginClass: 'mt-16'
})
</script>
