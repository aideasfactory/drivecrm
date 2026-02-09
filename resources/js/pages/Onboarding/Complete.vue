<template>
  <div class="min-h-screen flex flex-col">
    <OnboardingHeader :current-step="7" :total-steps="6" :max-step-reached="6" />

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <!-- Success Icon -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4 bg-primary/10">
          <CheckCircle class="h-12 w-12 text-primary" />
        </div>
        <h1 class="text-3xl font-bold mb-2">Booking Confirmed!</h1>
        <p class="text-lg text-muted-foreground">Your driving lessons have been successfully booked.</p>
      </div>

      <!-- Success Message -->
      <Card class="mb-6">
        <CardContent class="p-8 space-y-6">
          <!-- Confirmation Message -->
          <Alert variant="default">
            <CircleCheck class="h-4 w-4" />
            <AlertTitle>Payment Successful</AlertTitle>
            <AlertDescription>
              A confirmation email has been sent to your email address with all the details.
            </AlertDescription>
          </Alert>

          <!-- Next Steps -->
          <div>
            <h2 class="text-xl font-semibold mb-4">What happens next?</h2>
            <div class="space-y-4">
              <div class="flex items-start gap-4">
                <Badge variant="default" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                  <Mail class="h-4 w-4" />
                </Badge>
                <div>
                  <h3 class="font-medium mb-1">Check your email</h3>
                  <p class="text-sm text-muted-foreground">
                    You'll receive a confirmation email with your booking details, instructor information, and lesson schedule.
                  </p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <Badge variant="default" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                  <Phone class="h-4 w-4" />
                </Badge>
                <div>
                  <h3 class="font-medium mb-1">Your instructor will contact you</h3>
                  <p class="text-sm text-muted-foreground">
                    Your instructor will reach out within 24 hours to introduce themselves and confirm the lesson details.
                  </p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <Badge variant="default" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0">
                  <Calendar class="h-4 w-4" />
                </Badge>
                <div>
                  <h3 class="font-medium mb-1">Prepare for your first lesson</h3>
                  <p class="text-sm text-muted-foreground">
                    Make sure to bring your provisional license and arrive 5 minutes early for your first lesson.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Important Information -->
          <Alert variant="default">
            <Info class="h-4 w-4" />
            <AlertTitle>Important Information</AlertTitle>
            <AlertDescription>
              <ul class="list-disc list-inside space-y-1 mt-2">
                <li>Keep your confirmation email safe for your records</li>
                <li>You can reschedule lessons up to 24 hours in advance</li>
                <li>Contact your instructor directly for any questions</li>
              </ul>
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    </main>

    <!-- Footer -->
    <OnboardingFooter margin-class="mt-auto" :links="['Terms & Conditions', 'Privacy Policy', 'Contact Us']" />
  </div>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import OnboardingFooter from '@/components/Onboarding/OnboardingFooter.vue'
import { CheckCircle, CircleCheck, Mail, Phone, Calendar, Info } from 'lucide-vue-next'

defineProps({
  enquiry: Object
})
</script>
