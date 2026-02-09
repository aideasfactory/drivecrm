<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <OnboardingHeader :current-step="7" :total-steps="6" :max-step-reached="6" />

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-1">
      <!-- Success Icon -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
          <i class="fa-solid fa-check text-green-600 text-4xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Booking Confirmed!</h1>
        <p class="text-lg text-gray-600">Your driving lessons have been successfully booked.</p>
      </div>

      <!-- Success Message -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-6">
        <div class="space-y-6">
          <!-- Confirmation Message -->
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-start">
              <i class="fa-solid fa-circle-check text-green-600 mt-0.5 mr-3"></i>
              <div class="text-sm text-green-800">
                <p class="font-medium mb-1">Payment Successful</p>
                <p>A confirmation email has been sent to your email address with all the details.</p>
              </div>
            </div>
          </div>

          <!-- Next Steps -->
          <div>
            <h2 class="text-xl font-semibold text-gray-900 mb-4">What happens next?</h2>
            <div class="space-y-4">
              <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                  <span class="text-blue-600 font-semibold">1</span>
                </div>
                <div>
                  <h3 class="font-medium text-gray-900 mb-1">Check your email</h3>
                  <p class="text-sm text-gray-600">
                    You'll receive a confirmation email with your booking details, instructor information, and lesson schedule.
                  </p>
                </div>
              </div>

              <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                  <span class="text-blue-600 font-semibold">2</span>
                </div>
                <div>
                  <h3 class="font-medium text-gray-900 mb-1">Your instructor will contact you</h3>
                  <p class="text-sm text-gray-600">
                    Your instructor will reach out within 24 hours to introduce themselves and confirm the lesson details.
                  </p>
                </div>
              </div>

              <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                  <span class="text-blue-600 font-semibold">3</span>
                </div>
                <div>
                  <h3 class="font-medium text-gray-900 mb-1">Prepare for your first lesson</h3>
                  <p class="text-sm text-gray-600">
                    Make sure to bring your provisional license and arrive 5 minutes early for your first lesson.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Important Information -->
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex items-start">
              <i class="fa-solid fa-info-circle text-amber-600 mt-0.5 mr-3"></i>
              <div class="text-sm text-amber-800">
                <p class="font-medium mb-1">Important Information</p>
                <ul class="list-disc list-inside space-y-1">
                  <li>Keep your confirmation email safe for your records</li>
                  <li>You can reschedule lessons up to 24 hours in advance</li>
                  <li>Contact your instructor directly for any questions</li>
                </ul>
              </div>
            </div>
          </div>

          
        </div>
      </div>
 
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
          <div class="text-sm text-gray-600">
            © 2024 DRIVE Academy
          </div>

          <div class="flex items-center space-x-6">
            <a href="#" class="text-sm text-gray-600 hover:text-gray-900">Terms & Conditions</a>
            <a href="#" class="text-sm text-gray-600 hover:text-gray-900">Privacy Policy</a>
            <a href="#" class="text-sm text-gray-600 hover:text-gray-900">Contact Us</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'

defineProps({
  enquiry: Object
})
</script>
