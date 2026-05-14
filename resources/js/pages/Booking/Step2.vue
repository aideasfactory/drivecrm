<template>
  <div class="min-h-screen">
    <header class="sticky top-0 z-50">
      <Card class="rounded-none border-b border-t-0 border-x-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between h-16 w-full">
            <div class="flex-shrink-0 min-w-[200px]">
              <span class="flex items-center w-8">
                <AppLogoIcon class="w-4 fill-current text-[var(--foreground)] dark:text-white" />
                <span class="text-xl font-bold ml-2">DRIVE</span>
              </span>
            </div>
            <div class="flex items-center gap-4 sm:gap-6 text-sm">
              <span class="hidden sm:flex items-center text-muted-foreground">
                <MapPin class="mr-1.5 h-4 w-4" />
                London &amp; Surrounding Areas
              </span>
              <a href="tel:02012345678" class="hidden md:flex items-center hover:text-primary">
                <Phone class="mr-1.5 h-4 w-4" />
                020 1234 5678
              </a>
              <a href="mailto:hello@DRIVE.com" class="flex items-center hover:text-primary">
                <Mail class="mr-1.5 h-4 w-4" />
                hello@DRIVE.com
              </a>
            </div>
          </div>
        </div>
      </Card>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <Card>
        <CardHeader class="text-center">
          <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full"
               :class="inArea ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'">
            <CircleCheck v-if="inArea" class="h-8 w-8" />
            <CircleX v-else class="h-8 w-8" />
          </div>
          <CardTitle class="text-3xl">
            <template v-if="inArea">We have lessons in your area</template>
            <template v-else>Sorry, we don't have any lessons in your area</template>
          </CardTitle>
          <CardDescription class="text-lg pt-2">
            <template v-if="inArea">
              Great — someone will be in touch soon to get you booked in.
            </template>
            <template v-else>
              We're not currently teaching around <strong>{{ postcode }}</strong>. We'll keep your details on file and get in touch if that changes.
            </template>
          </CardDescription>
        </CardHeader>

        <CardContent class="text-center space-y-4">
          <p class="text-sm text-muted-foreground">
            <template v-if="inArea">
              Keep an eye on your inbox — we'll be in touch at the email address you gave us.
            </template>
            <template v-else>
              Thanks for getting in touch with DRIVE Driving School.
            </template>
          </p>
        </CardContent>
      </Card>
    </main>

    <Toaster />
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Toaster } from '@/components/ui/toast'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import { CircleCheck, CircleX, MapPin, Phone, Mail } from 'lucide-vue-next'

defineProps<{
  uuid: string
  currentStep: number
  totalSteps: number
  postcode: string | null
  inArea: boolean
  maxStepReached: number
}>()
</script>
