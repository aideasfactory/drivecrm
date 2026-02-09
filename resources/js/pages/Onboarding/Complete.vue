<template>
  <div class="min-h-screen flex flex-col">
    <OnboardingHeader :current-step="7" :total-steps="6" :max-step-reached="6" />

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <!-- Success Icon -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
          <i class="fa-solid fa-check text-green-600 text-4xl"></i>
        </div>
        <h1 class="text-3xl font-bold mb-2">Booking Confirmed!</h1>
        <p class="text-lg text-muted-foreground">Your driving lessons have been successfully booked.</p>
      </div>

      <!-- Success Message -->
      <Card class="mb-6">
        <CardContent class="p-8 space-y-6">
          <!-- Confirmation Message -->
          <Alert variant="default" class="bg-green-50 border-green-200">
            <i class="fa-solid fa-circle-check text-green-600"></i>
            <AlertTitle>Payment Successful</AlertTitle>
            <AlertDescription>
              A confirmation email has been sent to your email address with all the details.
            </AlertDescription>
          </Alert>

          <!-- Next Steps -->
          <div>
            <h2 class="text-xl font-semibold mb-4">What happens next?</h2>
            <div class="space-y-4">
              <div class="flex items-start">
                <Badge variant="default" class="w-8 h-8 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                  1
                </Badge>
                <div>
                  <h3 class="font-medium mb-1">Check your email</h3>
                  <p class="text-sm text-muted-foreground">
                    You'll receive a confirmation email with your booking details, instructor information, and lesson schedule.
                  </p>
                </div>
              </div>

              <div class="flex items-start">
                <Badge variant="default" class="w-8 h-8 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                  2
                </Badge>
                <div>
                  <h3 class="font-medium mb-1">Your instructor will contact you</h3>
                  <p class="text-sm text-muted-foreground">
                    Your instructor will reach out within 24 hours to introduce themselves and confirm the lesson details.
                  </p>
                </div>
              </div>

              <div class="flex items-start">
                <Badge variant="default" class="w-8 h-8 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                  3
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
          <Alert variant="default" class="bg-amber-50 border-amber-200">
            <i class="fa-solid fa-info-circle text-amber-600"></i>
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
    <footer class="mt-auto">
      <Card class="rounded-none border-x-0 border-b-0">
        <CardContent class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm">
              © 2024 DRIVE Academy
            </div>

            <div class="flex items-center space-x-6">
              <a href="#" class="text-sm hover:underline">Terms & Conditions</a>
              <a href="#" class="text-sm hover:underline">Privacy Policy</a>
              <a href="#" class="text-sm hover:underline">Contact Us</a>
            </div>
          </div>
        </CardContent>
      </Card>
    </footer>
  </div>
</template>

<script setup>
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'

defineProps({
  enquiry: Object
})
</script>
