<template>
  <div class="min-h-screen flex flex-col bg-background">
    <main class="max-w-2xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <!-- Success state -->
      <template v-if="verified">
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4 bg-primary/10">
            <CheckCircle class="h-12 w-12 text-primary" />
          </div>
          <h1 class="text-3xl font-bold mb-2">Payment Received</h1>
          <p class="text-lg text-muted-foreground">
            Your lessons are booked and confirmed.
          </p>
        </div>

        <Card class="mb-6">
          <CardContent class="p-6 space-y-6">
            <Alert>
              <CircleCheck class="h-4 w-4" />
              <AlertTitle>Booking confirmed</AlertTitle>
              <AlertDescription>
                A confirmation email has been sent to you with the full details.
              </AlertDescription>
            </Alert>

            <div v-if="order" class="space-y-3">
              <div v-if="order.package" class="flex items-start justify-between gap-4">
                <span class="text-sm text-muted-foreground">Package</span>
                <span class="text-sm font-medium text-right">
                  {{ order.package.name }}
                  <span v-if="order.package.lessons_count" class="text-muted-foreground">
                    · {{ order.package.lessons_count }} lessons
                  </span>
                </span>
              </div>
              <div v-if="order.instructor" class="flex items-start justify-between gap-4">
                <span class="text-sm text-muted-foreground">Instructor</span>
                <span class="text-sm font-medium text-right">{{ order.instructor.name }}</span>
              </div>
              <div v-if="order.total_price_pence" class="flex items-start justify-between gap-4">
                <span class="text-sm text-muted-foreground">Amount paid</span>
                <span class="text-sm font-medium text-right">{{ formatPrice(order.total_price_pence) }}</span>
              </div>
            </div>

            <Alert>
              <Info class="h-4 w-4" />
              <AlertTitle>What happens next?</AlertTitle>
              <AlertDescription>
                <ul class="list-disc list-inside space-y-1 mt-2">
                  <li>Check your email for the confirmation and lesson schedule.</li>
                  <li>Your instructor will be in touch to confirm the first lesson details.</li>
                  <li>You can reschedule lessons up to 24 hours in advance from the Drive app.</li>
                </ul>
              </AlertDescription>
            </Alert>
          </CardContent>
        </Card>
      </template>

      <!-- Pending / unverified state -->
      <template v-else>
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4 bg-muted">
            <Clock class="h-12 w-12 text-muted-foreground" />
          </div>
          <h1 class="text-3xl font-bold mb-2">Payment Processing</h1>
          <p class="text-lg text-muted-foreground">
            {{ message || "We're still confirming your payment with Stripe." }}
          </p>
        </div>

        <Card>
          <CardContent class="p-6">
            <Alert>
              <Info class="h-4 w-4" />
              <AlertTitle>Hang tight</AlertTitle>
              <AlertDescription>
                This page can be safely closed. You'll receive a confirmation email as soon as the payment is confirmed.
              </AlertDescription>
            </Alert>
          </CardContent>
        </Card>
      </template>
    </main>
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { CheckCircle, CircleCheck, Info, Clock } from 'lucide-vue-next'

interface OrderSummary {
  id: number
  status: string
  total_price_pence: number | null
  package: { name: string; lessons_count: number | null } | null
  instructor: { name: string } | null
}

defineProps<{
  verified: boolean
  message?: string
  order: OrderSummary | null
}>()

const formatPrice = (pence: number): string => {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
  }).format(pence / 100)
}
</script>
