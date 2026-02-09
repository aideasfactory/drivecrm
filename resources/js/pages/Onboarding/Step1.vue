<template>
  <div class="min-h-screen">
    <OnboardingHeader :current-step="1" :total-steps="6" :max-step-reached="page.props.maxStepReached || 1" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1 order-2 lg:order-1">
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
            </CardContent>
          </Card>
        </div>

        <!-- Main Form -->
        <div class="lg:col-span-2 order-1 lg:order-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-3xl">Let's get to know you</CardTitle>
              <CardDescription class="text-lg">
                We'll send confirmations and your resume link to this email.
              </CardDescription>
            </CardHeader>

            <CardContent>
              <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-2">
                    <Label for="first_name">
                      First name <span class="text-destructive">*</span>
                    </Label>
                    <Input
                      id="first_name"
                      v-model="form.first_name"
                      type="text"
                      placeholder="Enter your first name"
                      required
                      :class="{ 'border-destructive': form.errors.first_name }"
                    />
                    <p v-if="form.errors.first_name" class="text-sm text-destructive flex items-center">
                      <AlertCircle class="mr-1 h-4 w-4" />
                      {{ form.errors.first_name }}
                    </p>
                  </div>

                  <div class="space-y-2">
                    <Label for="last_name">
                      Last name <span class="text-destructive">*</span>
                    </Label>
                    <Input
                      id="last_name"
                      v-model="form.last_name"
                      type="text"
                      placeholder="Enter your last name"
                      required
                      :class="{ 'border-destructive': form.errors.last_name }"
                    />
                    <p v-if="form.errors.last_name" class="text-sm text-destructive flex items-center">
                      <AlertCircle class="mr-1 h-4 w-4" />
                      {{ form.errors.last_name }}
                    </p>
                  </div>
                </div>

                <div class="space-y-2">
                  <Label for="email">
                    Email address <span class="text-destructive">*</span>
                  </Label>
                  <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="your.email@example.com"
                    required
                    :class="{ 'border-destructive': form.errors.email }"
                  />
                  <p v-if="form.errors.email" class="text-sm text-destructive flex items-center">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.email }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="phone">
                    Phone number <span class="text-destructive">*</span>
                  </Label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-sm">🇬🇧 +44</span>
                    </div>
                    <Input
                      id="phone"
                      v-model="form.phone"
                      type="tel"
                      class="pl-20"
                      placeholder="7123 456789"
                      required
                      :class="{ 'border-destructive': form.errors.phone }"
                    />
                  </div>
                  <p v-if="form.errors.phone" class="text-sm text-destructive flex items-center">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.phone }}
                  </p>
                  <p class="text-sm text-muted-foreground flex items-center">
                    <Info class="mr-1 h-4 w-4" />
                    We'll use this for lesson confirmations and updates
                  </p>
                  <p v-if="form.phone && !validatePhone(form.phone)" class="text-sm text-orange-600 flex items-center">
                    <AlertTriangle class="mr-1 h-4 w-4" />
                    Please enter in format: 07123 456 789
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="postcode">
                    Where are we picking you up? <span class="text-destructive">*</span>
                  </Label>
                  <Input
                    id="postcode"
                    v-model="form.postcode"
                    type="text"
                    class="uppercase"
                    placeholder="e.g. SW1A 1AA"
                    maxlength="8"
                    required
                    :class="{ 'border-destructive': form.errors.postcode }"
                  />
                  <p v-if="form.errors.postcode" class="text-sm text-destructive flex items-center">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.postcode }}
                  </p>
                  <p class="text-sm text-muted-foreground flex items-center">
                    <MapPin class="mr-1 h-4 w-4" />
                    Enter your postcode for lesson pickup location
                  </p>
                </div>

                <div class="space-y-4 pt-4">
                  <Separator />

                  <div class="flex items-start space-x-3">
                    <Checkbox
                      id="privacy_consent"
                      v-model:checked="form.privacy_consent"
                      required
                    />
                    <Label for="privacy_consent" class="text-sm leading-relaxed cursor-pointer">
                      I agree to the
                      <span class="font-medium hover:underline">Terms & Conditions</span>
                      and
                      <span class="font-medium hover:underline">Privacy Policy</span>,
                      and consent to my data being used to process my booking.
                      <span class="text-destructive">*</span>
                    </Label>
                  </div>
                  <p v-if="form.errors.privacy_consent" class="text-sm text-destructive flex items-center ml-7">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.privacy_consent }}
                  </p>
                </div>

                <Alert>
                  <Shield class="h-4 w-4" />
                  <AlertTitle>Your data is safe with us</AlertTitle>
                  <AlertDescription>
                    We'll only use your contact details to send booking confirmations, lesson reminders, and your personal resume link.
                    You can unsubscribe from marketing emails at any time.
                  </AlertDescription>
                </Alert>
              </form>

              <div class="flex justify-between items-center mt-8">
                <div class="flex items-center space-x-4 ml-auto">
                  <Button
                    @click="submit"
                    :disabled="!isFormValid || form.processing"
                    class="cursor-pointer"
                  >
                    Next
                    <ArrowRight v-if="!form.processing" class="ml-2 h-4 w-4" />
                    <Spinner v-if="form.processing" class="ml-2 h-4 w-4 animate-spin" />
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </main>

    <footer>
      <Card class="rounded-none border-x-0 border-b-0 mt-16">
        <CardContent class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-sm">
              © 2024 DRIVE Driving School
            </div>
            <div class="flex items-center space-x-6">
              <span class="text-sm cursor-pointer hover:underline">Terms & Conditions</span>
              <span class="text-sm cursor-pointer hover:underline">Privacy Policy</span>
              <span class="text-sm cursor-pointer hover:underline">Cookies</span>
            </div>
            <div class="flex items-center space-x-2">
              <CreditCard class="h-6 w-6" />
              <CreditCard class="h-6 w-6" />
              <CreditCard class="h-6 w-6" />
              <CreditCard class="h-6 w-6" />
              <CreditCard class="h-6 w-6" />
            </div>
          </div>
        </CardContent>
      </Card>
    </footer>

    <!-- Sonner Toast -->
    <Sonner position="top-right" rich-colors />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Spinner } from '@/components/ui/spinner'
import { Sonner } from '@/components/ui/sonner'
import OnboardingHeader from '@/components/Onboarding/OnboardingHeader.vue'
import {
  MapPin,
  Phone,
  Mail,
  Shield,
  Lock,
  CircleCheck,
  AlertCircle,
  Info,
  AlertTriangle,
  ArrowRight,
  CreditCard
} from 'lucide-vue-next'

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

function validateEmail(email: string) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return re.test(email)
}

function validatePhone(phone: string) {
  if (!phone) return false
  // Match the backend regex: /^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/
  const phoneRegex = /^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/
  return phoneRegex.test(phone.trim())
}

function validatePostcode(postcode: string) {
  const re = /^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i
  return re.test(postcode.trim())
}

function submit() {
  if (!isFormValid.value) {
    return
  }

  if (!page.props.enquiry?.id) {
    console.error('No enquiry ID found')
    return
  }

  form.post(`/onboarding/${page.props.enquiry.id}/step/1`, {
    preserveScroll: true,
    onSuccess: () => {
      // Success is handled by flash messages in layout
    },
    onError: () => {
      // Errors are displayed inline via form.errors
    }
  })
}
</script>
