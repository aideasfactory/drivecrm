<template>
  <Card class="lg:sticky lg:top-24">
    <CardHeader>
      <CardTitle>DRIVE Driving School</CardTitle>
      <CardDescription class="space-y-2">
        <div class="flex items-center">
          <MapPin class="mr-2 h-4 w-4" />
          <span>London & Surrounding Areas</span>
        </div>
        <div class="flex items-center">
          <Phone class="mr-2 h-4 w-4" />
          <span>020 1234 5678</span>
        </div>
        <div class="flex items-center">
          <Mail class="mr-2 h-4 w-4" />
          <span>hello@DRIVE.com</span>
        </div>
      </CardDescription>
    </CardHeader>

    <CardContent class="space-y-6">
      <div class="flex items-center flex-wrap gap-3">
        <Badge variant="secondary" class="flex items-center">
          <Shield class="mr-1 h-3 w-3" />
          DVSA Approved
        </Badge>
        <Badge variant="secondary" class="flex items-center">
          <Lock class="mr-1 h-3 w-3" />
          Secure Checkout
        </Badge>
      </div>

      <Separator />

      <div>
        <h4 class="font-semibold mb-3">Why book with us?</h4>
        <ul class="space-y-2 text-sm">
          <li class="flex items-start">
            <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
            <span>Full refund policy - cancel up to 24 hours before</span>
          </li>
          <li class="flex items-start">
            <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
            <span>Flexible scheduling - reschedule anytime</span>
          </li>
          <li class="flex items-start">
            <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
            <span>Qualified DVSA approved instructors</span>
          </li>
          <li class="flex items-start">
            <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
            <span>Modern dual-control vehicles</span>
          </li>
        </ul>
      </div>

      <!-- Extra slot for step-specific content like booking summary -->
      <slot name="extra-content"></slot>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { MapPin, Phone, Mail, Shield, Lock, CircleCheck } from 'lucide-vue-next'
</script>
