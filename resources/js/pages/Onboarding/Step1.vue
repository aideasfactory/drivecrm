<template>
  <div class="bg-gray-50 min-h-screen">
    <OnboardingHeader :current-step="1" :total-steps="6" :max-step-reached="page.props.maxStepReached || 1" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-24">
            <div class="mb-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-2">DRIVE Driving School</h3>
              <div class="space-y-2 text-sm text-gray-600">
                <div class="flex items-center">
                  <i class="fa-solid fa-map-marker-alt mr-2 text-gray-400"></i>
                  <span>London & Surrounding Areas</span>
                </div>
                <div class="flex items-center">
                  <i class="fa-solid fa-phone mr-2 text-gray-400"></i>
                  <span>020 1234 5678</span>
                </div>
                <div class="flex items-center">
                  <i class="fa-solid fa-envelope mr-2 text-gray-400"></i>
                  <span>hello@DRIVE.com</span>
                </div>
              </div>
            </div>

            <div class="mb-6">
              <div class="flex items-center space-x-3 mb-4">
                <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium flex items-center">
                  <i class="fa-solid fa-shield-alt mr-1"></i>
                  DVSA Approved
                </div>
                <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium flex items-center">
                  <i class="fa-solid fa-lock mr-1"></i>
                  Secure Checkout
                </div>
              </div>
            </div>

            <div>
              <h4 class="font-semibold text-gray-900 mb-3">Why book with us?</h4>
              <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Full refund policy - cancel up to 24 hours before</span>
                </li>
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Flexible scheduling - reschedule anytime</span>
                </li>
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Qualified DVSA approved instructors</span>
                </li>
                <li class="flex items-start">
                  <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
                  <span>Modern dual-control vehicles</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Main Form -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
              <h1 class="text-3xl font-bold text-gray-900 mb-2">Let's get to know you</h1>
              <p class="text-lg text-gray-600">We'll send confirmations and your resume link to this email.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="first_name" class="block text-sm font-semibold text-gray-900 mb-2">
                    First name <span class="text-red-500">*</span>
                  </label>
                  <input 
                    type="text" 
                    id="first_name" 
                    v-model="form.first_name"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                    :class="{ 'border-red-500 focus:ring-red-500': form.errors.first_name }"
                    placeholder="Enter your first name">
                  <div v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">
                    <i class="fa-solid fa-exclamation-circle mr-1"></i>
                    {{ form.errors.first_name }}
                  </div>
                </div>

                <div>
                  <label for="last_name" class="block text-sm font-semibold text-gray-900 mb-2">
                    Last name <span class="text-red-500">*</span>
                  </label>
                  <input 
                    type="text" 
                    id="last_name" 
                    v-model="form.last_name"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                    :class="{ 'border-red-500 focus:ring-red-500': form.errors.last_name }"
                    placeholder="Enter your last name">
                  <div v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">
                    <i class="fa-solid fa-exclamation-circle mr-1"></i>
                    {{ form.errors.last_name }}
                  </div>
                </div>
              </div>

              <div>
                <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">
                  Email address <span class="text-red-500">*</span>
                </label>
                <input 
                  type="email" 
                  id="email" 
                  v-model="form.email"
                  required
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                  :class="{ 'border-red-500 focus:ring-red-500': form.errors.email }"
                  placeholder="your.email@example.com">
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                  <i class="fa-solid fa-exclamation-circle mr-1"></i>
                  {{ form.errors.email }}
                </div>
              </div>

              <div>
                <label for="phone" class="block text-sm font-semibold text-gray-900 mb-2">
                  Phone number <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 text-sm">🇬🇧 +44</span>
                  </div>
                  <input 
                    type="tel" 
                    id="phone" 
                    v-model="form.phone"
                    required
                    class="w-full pl-20 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                    :class="{ 'border-red-500 focus:ring-red-500': form.errors.phone }"
                    placeholder="7123 456789">
                </div>
                <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                  <i class="fa-solid fa-exclamation-circle mr-1"></i>
                  {{ form.errors.phone }}
                </div>
                <p class="mt-1 text-sm text-gray-500">
                  <i class="fa-solid fa-info-circle mr-1"></i>
                  We'll use this for lesson confirmations and updates
                </p>
                <p v-if="form.phone && !validatePhone(form.phone)" class="mt-1 text-sm text-orange-600">
                  <i class="fa-solid fa-exclamation-triangle mr-1"></i>
                  Please enter in format: 07123 456 789
                </p>
              </div>

              <div>
                <label for="postcode" class="block text-sm font-semibold text-gray-900 mb-2">
                  Where are we picking you up? <span class="text-red-500">*</span>
                </label>
                <input 
                  type="text" 
                  id="postcode" 
                  v-model="form.postcode"
                  required
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500 uppercase"
                  :class="{ 'border-red-500 focus:ring-red-500': form.errors.postcode }"
                  placeholder="e.g. SW1A 1AA"
                  maxlength="8">
                <div v-if="form.errors.postcode" class="mt-1 text-sm text-red-600">
                  <i class="fa-solid fa-exclamation-circle mr-1"></i>
                  {{ form.errors.postcode }}
                </div>
                <p class="mt-1 text-sm text-gray-500">
                  <i class="fa-solid fa-map-marker-alt mr-1"></i>
                  Enter your postcode for lesson pickup location
                </p>
              </div>

              <div class="space-y-4 pt-4 border-t border-gray-200">
                <div class="flex items-start">
                  <input 
                    type="checkbox" 
                    id="privacy_consent" 
                    v-model="form.privacy_consent"
                    required
                    class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-600 cursor-pointer">
                  <label for="privacy_consent" class="ml-3 text-sm text-gray-700 cursor-pointer">
                    I agree to the <span class="text-blue-600 font-medium hover:underline">Terms & Conditions</span> and <span class="text-blue-600 font-medium hover:underline">Privacy Policy</span>, and consent to my data being used to process my booking. <span class="text-red-500">*</span>
                  </label>
                </div>
                <div v-if="form.errors.privacy_consent" class="ml-7 text-sm text-red-600">
                  <i class="fa-solid fa-exclamation-circle mr-1"></i>
                  {{ form.errors.privacy_consent }}
                </div>
              </div>

              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                  <i class="fa-solid fa-shield-alt text-blue-600 mt-0.5 mr-3 flex-shrink-0"></i>
                  <div class="text-sm text-blue-800">
                    <p class="font-medium mb-1">Your data is safe with us</p>
                    <p>We'll only use your contact details to send booking confirmations, lesson reminders, and your personal resume link. You can unsubscribe from marketing emails at any time.</p>
                  </div>
                </div>
              </div>
            </form>

            <div class="flex justify-between items-center mt-8">
              <!-- <button 
                type="button"
                class="text-gray-600 hover:text-gray-800 font-semibold px-6 py-3 rounded-lg transition-colors duration-200 flex items-center">
                <i class="fa-solid fa-arrow-left mr-2"></i>
                Back
              </button> -->
              <div class="flex items-center space-x-4">
                <!-- <div class="text-sm text-gray-500">
                  <i class="fa-solid fa-save mr-1"></i>
                  Progress automatically saved
                </div> -->
                <!-- <div v-if="!isFormValid" class="text-xs text-orange-600">
                  Form validation: {{ !form.first_name.trim() ? 'First name required ' : '' }}{{ !form.last_name.trim() ? 'Last name required ' : '' }}{{ !form.email.trim() ? 'Email required ' : '' }}{{ !validateEmail(form.email) && form.email ? 'Valid email required ' : '' }}{{ !form.phone.trim() ? 'Phone required ' : '' }}{{ !validatePhone(form.phone) && form.phone ? 'Valid phone required ' : '' }}{{ !form.postcode.trim() ? 'Postcode required ' : '' }}{{ !validatePostcode(form.postcode) && form.postcode ? 'Valid postcode required ' : '' }}{{ !form.privacy_consent ? 'Privacy consent required ' : '' }}
                </div> -->
                <button 
                  type="button"
                  @click="submit"
                  :disabled="!isFormValid || form.processing"
                  class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                  <template v-if="form.processing">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                    Processing...
                  </template>
                  <template v-else>
                    Next
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                  </template>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="bg-white border-t border-gray-200 mt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <div class="text-sm text-gray-600 mb-4 md:mb-0">
            © 2024 DRIVE Driving School
          </div>
          <div class="flex items-center space-x-6 mb-4 md:mb-0">
            <span class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer">Terms & Conditions</span>
            <span class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer">Privacy Policy</span>
            <span class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer">Cookies</span>
          </div>
          <div class="flex items-center space-x-2">
            <i class="fa-brands fa-cc-visa text-2xl text-gray-400"></i>
            <i class="fa-brands fa-cc-mastercard text-2xl text-gray-400"></i>
            <i class="fa-brands fa-cc-amex text-2xl text-gray-400"></i>
            <i class="fa-brands fa-apple-pay text-2xl text-gray-400"></i>
            <i class="fa-brands fa-google-pay text-2xl text-gray-400"></i>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage, useForm } from '@inertiajs/vue3'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'

const page = usePage()

const existingData = page.props.enquiry?.data?.steps?.step1 || {}

const form = useForm({
  first_name: existingData.first_name || '',
  last_name: existingData.last_name || '',
  email: existingData.email || '',
  phone: existingData.phone || '',
  postcode: existingData.postcode || '',
  privacy_consent: existingData.privacy_consent || false,
  booking_for_other: existingData.booking_for_other || false
})

const isFormValid = computed(() => {
  const hasRequiredFields = form.first_name.trim() &&
                           form.last_name.trim() &&
                           form.email.trim() &&
                           form.phone.trim() &&
                           form.postcode.trim() &&
                           form.privacy_consent;
                           
  if (!hasRequiredFields) return false;
  
  const hasValidFields = validateEmail(form.email) &&
                        validatePhone(form.phone) &&
                        validatePostcode(form.postcode);
                        
  return hasValidFields;
})

function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return re.test(email)
}

function validatePhone(phone) {
  if (!phone) return false
  // Match the backend regex: /^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/
  const phoneRegex = /^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/
  return phoneRegex.test(phone.trim())
}

function validatePostcode(postcode) {
  const re = /^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i
  return re.test(postcode.trim())
}

function submit() {
  console.log('Form submission attempted')
  console.log('Form valid:', isFormValid.value)
  console.log('Form data:', {
    first_name: form.first_name,
    last_name: form.last_name,
    email: form.email,
    phone: form.phone,
    postcode: form.postcode,
    privacy_consent: form.privacy_consent,
    booking_for_other: form.booking_for_other
  })
  console.log('Enquiry ID:', page.props.enquiry?.id)
  
  if (!isFormValid.value) {
    console.log('Form validation failed')
    return
  }
  
  if (!page.props.enquiry?.id) {
    console.error('No enquiry ID found')
    return
  }
  
  console.log('Submitting to:', `/onboarding/${page.props.enquiry.id}/step/1`)
  
  form.post(`/onboarding/${page.props.enquiry.id}/step/1`, {
    preserveScroll: true,
    onSuccess: (page) => {
      console.log('Form submitted successfully:', page)
    },
    onError: (errors) => {
      console.error('Form submission errors:', errors)
      console.error('Full error object:', form.errors)
    },
    onFinish: () => {
      console.log('Form submission finished')
    },
    onBefore: () => {
      console.log('Starting form submission...')
    }
  })
}
</script>