<template>
  <Card class="sticky top-24">
    <CardHeader>
      <CardTitle>DRIVE Driving School</CardTitle>
      <CardDescription class="space-y-2">
        <div class="flex items-center">
          <i class="fa-solid fa-map-marker-alt mr-2"></i>
          <span>London & Surrounding Areas</span>
        </div>
        <div class="flex items-center">
          <i class="fa-solid fa-phone mr-2"></i>
          <span>020 1234 5678</span>
        </div>
        <div class="flex items-center">
          <i class="fa-solid fa-envelope mr-2"></i>
          <span>hello@DRIVE.com</span>
        </div>
      </CardDescription>
    </CardHeader>

    <CardContent class="space-y-6">
      <div class="flex items-center space-x-3">
        <Badge variant="secondary" class="flex items-center">
          <i class="fa-solid fa-shield-alt mr-1"></i>
          DVSA Approved
        </Badge>
        <Badge variant="secondary" class="flex items-center">
          <i class="fa-solid fa-lock mr-1"></i>
          Secure Checkout
        </Badge>
      </div>

      <Separator />

      <div>
        <h4 class="font-semibold mb-3">Why book with us?</h4>
        <ul class="space-y-2 text-sm">
          <li class="flex items-start">
            <i class="fa-solid fa-check-circle mr-2 mt-0.5 flex-shrink-0"></i>
            <span>Full refund policy - cancel up to 24 hours before</span>
          </li>
          <li class="flex items-start">
            <i class="fa-solid fa-check-circle mr-2 mt-0.5 flex-shrink-0"></i>
            <span>Flexible scheduling - reschedule anytime</span>
          </li>
          <li class="flex items-start">
            <i class="fa-solid fa-check-circle mr-2 mt-0.5 flex-shrink-0"></i>
            <span>Qualified DVSA approved instructors</span>
          </li>
          <li class="flex items-start">
            <i class="fa-solid fa-check-circle mr-2 mt-0.5 flex-shrink-0"></i>
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
</script>
