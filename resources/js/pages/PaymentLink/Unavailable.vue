<template>
  <div class="min-h-screen flex flex-col bg-background">
    <main class="max-w-2xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <div class="text-center mb-8">
        <div
          class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4"
          :class="reason === 'paid' ? 'bg-primary/10' : 'bg-muted'"
        >
          <CheckCircle v-if="reason === 'paid'" class="h-12 w-12 text-primary" />
          <TriangleAlert v-else-if="reason === 'error'" class="h-12 w-12 text-muted-foreground" />
          <Clock v-else class="h-12 w-12 text-muted-foreground" />
        </div>
        <h1 class="text-3xl font-bold mb-2">{{ heading }}</h1>
        <p class="text-lg text-muted-foreground">{{ message }}</p>
      </div>

      <Card>
        <CardContent class="p-6 space-y-4">
          <Alert v-if="reason === 'paid'">
            <CircleCheck class="h-4 w-4" />
            <AlertTitle>Nothing more to pay right now</AlertTitle>
            <AlertDescription>
              Check your email for your booking confirmation and lesson schedule.
            </AlertDescription>
          </Alert>

          <Alert v-else-if="reason === 'error'">
            <Info class="h-4 w-4" />
            <AlertTitle>Please try again</AlertTitle>
            <AlertDescription>
              We couldn't open the payment page just now. Try the link in your email again in a few minutes.
            </AlertDescription>
          </Alert>

          <Alert v-else>
            <Info class="h-4 w-4" />
            <AlertTitle>These lesson times have been released</AlertTitle>
            <AlertDescription>
              No payment was taken. Contact your instructor<span v-if="order?.instructor"> ({{ order.instructor.name }})</span>
              to book again.
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
import { computed } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { CheckCircle, CircleCheck, Clock, Info, TriangleAlert } from 'lucide-vue-next'

interface OrderSummary {
  id: number
  package: { name: string; lessons_count: number | null } | null
  instructor: { name: string } | null
}

const props = defineProps<{
  reason: 'paid' | 'unavailable' | 'error'
  message: string
  order: OrderSummary | null
}>()

const heading = computed((): string => {
  if (props.reason === 'paid') {
    return 'Already Paid'
  }

  if (props.reason === 'error') {
    return 'Something Went Wrong'
  }

  return 'Booking No Longer Available'
})
</script>
