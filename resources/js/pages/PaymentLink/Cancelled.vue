<template>
  <div class="min-h-screen flex flex-col bg-background">
    <main class="max-w-2xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4 bg-muted">
          <XCircle class="h-12 w-12 text-muted-foreground" />
        </div>
        <h1 class="text-3xl font-bold mb-2">Payment Cancelled</h1>
        <p class="text-lg text-muted-foreground">
          No payment was taken. Your booking hasn't been confirmed.
        </p>
      </div>

      <Card>
        <CardContent class="p-6 space-y-4">
          <Alert>
            <Info class="h-4 w-4" />
            <AlertTitle>Want to try again?</AlertTitle>
            <AlertDescription>
              Ask your instructor<span v-if="order?.instructor"> ({{ order.instructor.name }})</span>
              to resend the payment link when you're ready to pay.
            </AlertDescription>
          </Alert>

          <div v-if="order?.package" class="text-sm text-muted-foreground">
            Booking: <span class="font-medium text-foreground">{{ order.package.name }}</span>
            <span v-if="order.package.lessons_count"> · {{ order.package.lessons_count }} lessons</span>
          </div>
        </CardContent>
      </Card>
    </main>
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { XCircle, Info } from 'lucide-vue-next'

interface OrderSummary {
  id: number
  package: { name: string; lessons_count: number | null } | null
  instructor: { name: string } | null
}

defineProps<{
  order: OrderSummary | null
}>()
</script>
