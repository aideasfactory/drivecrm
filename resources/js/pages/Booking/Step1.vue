<template>
  <div class="min-h-screen">
    <header class="sticky top-0 z-50">
      <Card class="rounded-none border-b border-t-0 border-x-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between h-16 w-full">
            <div class="flex-shrink-0 sm:min-w-[200px]">
              <span class="flex items-center">
                <AppLogoIcon class="h-8 w-8" />
                <span class="text-xl font-bold ml-2">DRIVE</span>
              </span>
            </div>
            <div class="flex items-center gap-4 sm:gap-6 text-sm">
              <a :href="phoneHref" class="hidden md:flex items-center hover:text-primary">
                <Phone class="mr-1.5 h-4 w-4" />
                {{ contact.phone }}
              </a>
              <a :href="emailHref" class="hidden md:flex items-center hover:text-primary">
                <Mail class="mr-1.5 h-4 w-4" />
                {{ contact.email }}
              </a>
            </div>
          </div>
        </div>
      </Card>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-1 order-2 lg:order-1">
          <Card class="lg:sticky lg:top-24">
            <CardHeader>
              <CardTitle>{{ contact.name }}</CardTitle>
              <CardDescription class="space-y-2">
                <div class="flex items-center">
                  <Phone class="mr-2 h-4 w-4" />
                  <a :href="phoneHref" class="hover:text-primary hover:underline">{{ contact.phone }}</a>
                </div>
                <div class="flex items-center">
                  <Mail class="mr-2 h-4 w-4" />
                  <a :href="emailHref" class="hover:text-primary hover:underline">{{ contact.email }}</a>
                </div>
              </CardDescription>
            </CardHeader>

            <CardContent class="space-y-6">
              <div class="flex items-center flex-wrap gap-3">
                <Badge variant="secondary" class="flex items-center bg-primary text-primary-foreground hover:bg-primary/90">
                  <Shield class="mr-1 h-3 w-3" />
                  DVSA-registered school
                </Badge>
                <Badge variant="secondary" class="flex items-center bg-primary text-primary-foreground hover:bg-primary/90">
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
                    <span>Flexible cancellation — 48 hours notice</span>
                  </li>
                  <li class="flex items-start">
                    <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>Flexible scheduling - reschedule anytime</span>
                  </li>
                  <li class="flex items-start">
                    <CircleCheck class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>DVSA-registered instructors</span>
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

        <div class="lg:col-span-2 order-1 lg:order-2">
          <Card>
            <CardHeader>
              <CardTitle class="text-3xl">Let's get to know you</CardTitle>
              <CardDescription class="text-lg">
                We'll check if we cover your area and get in touch.
              </CardDescription>
            </CardHeader>

            <CardContent>
              <form novalidate @submit.prevent="submit" class="space-y-6">
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
                      autocomplete="given-name"
                      autocapitalize="words"
                      maxlength="100"
                      :aria-invalid="form.errors.first_name ? 'true' : undefined"
                      :aria-describedby="form.errors.first_name ? 'first_name-error' : undefined"
                      @blur="onFieldBlur('first_name')"
                    />
                    <p v-if="form.errors.first_name" id="first_name-error" class="text-sm text-destructive flex items-start">
                      <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                      <span>{{ form.errors.first_name }}</span>
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
                      autocomplete="family-name"
                      autocapitalize="words"
                      maxlength="100"
                      :aria-invalid="form.errors.last_name ? 'true' : undefined"
                      :aria-describedby="form.errors.last_name ? 'last_name-error' : undefined"
                      @blur="onFieldBlur('last_name')"
                    />
                    <p v-if="form.errors.last_name" id="last_name-error" class="text-sm text-destructive flex items-start">
                      <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                      <span>{{ form.errors.last_name }}</span>
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
                    inputmode="email"
                    autocomplete="email"
                    autocapitalize="off"
                    spellcheck="false"
                    maxlength="255"
                        :aria-invalid="form.errors.email ? 'true' : undefined"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                        @blur="onFieldBlur('email')"
                  />
                  <p v-if="form.errors.email" id="email-error" class="text-sm text-destructive flex items-start">
                    <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ form.errors.email }}</span>
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="phone">
                    Phone number <span class="text-destructive">*</span>
                  </Label>
                  <div class="flex gap-2">
                    <select
                      id="country-code"
                      v-model="countryCode"
                      aria-label="Country code"
                      class="h-9 w-28 shrink-0 rounded-md border border-input bg-transparent dark:bg-input/30 px-3 py-1 text-base md:text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    >
                      <option v-for="country in countryCodes" :key="country.code" :value="country.code">
                        {{ country.flag }} {{ country.code }}
                      </option>
                    </select>
                    <Input
                      id="phone"
                      v-model="form.phone"
                      type="tel"
                      class="flex-1"
                      placeholder="07710 896753"
                      inputmode="tel"
                      autocomplete="tel-national"
                      :aria-invalid="form.errors.phone ? 'true' : undefined"
                      :aria-describedby="form.errors.phone ? 'phone-error' : undefined"
                      @blur="onFieldBlur('phone')"
                    />
                  </div>
                  <p v-if="form.errors.phone" id="phone-error" class="text-sm text-destructive flex items-start">
                    <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ form.errors.phone }}</span>
                  </p>
                  <p class="text-sm text-muted-foreground flex items-center">
                    <Info class="mr-1 h-4 w-4" />
                    We'll use this for lesson confirmations and updates
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
                    autocomplete="postal-code"
                    autocapitalize="characters"
                        :aria-invalid="form.errors.postcode ? 'true' : undefined"
                        :aria-describedby="form.errors.postcode ? 'postcode-error' : undefined"
                        @blur="onFieldBlur('postcode')"
                  />
                  <p v-if="form.errors.postcode" id="postcode-error" class="text-sm text-destructive flex items-start">
                    <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ form.errors.postcode }}</span>
                  </p>
                  <p class="text-sm text-muted-foreground flex items-center">
                    <MapPin class="mr-1 h-4 w-4" />
                    Enter your postcode for lesson pickup location
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="transmission">
                    Transmission preference <span class="text-destructive">*</span>
                  </Label>
                  <select
                    id="transmission"
                    v-model="form.transmission"
                    :aria-invalid="form.errors.transmission ? 'true' : undefined"
                    :aria-describedby="form.errors.transmission ? 'transmission-error' : undefined"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base md:text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="{ 'border-destructive': form.errors.transmission }"
                  >
                    <option value="manual">Manual</option>
                    <option value="automatic">Automatic</option>
                  </select>
                  <p v-if="form.errors.transmission" id="transmission-error" class="text-sm text-destructive flex items-start">
                    <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ form.errors.transmission }}</span>
                  </p>
                </div>

                <div class="space-y-4 pt-4">
                  <Separator />

                  <div class="flex items-start space-x-3">
                    <input
                      type="checkbox"
                      id="privacy_consent"
                      v-model="privacyConsent"
                      :aria-invalid="form.errors.privacy_consent ? 'true' : undefined"
                      :aria-describedby="form.errors.privacy_consent ? 'privacy_consent-error' : undefined"
                      class="h-4 w-4 mt-1 rounded border-input text-primary focus:ring-2 focus:ring-ring focus:ring-offset-2 cursor-pointer"
                    />
                    <Label for="privacy_consent" class="block text-sm leading-relaxed cursor-pointer">
                      I have read and agree to the
                      <a href="/terms-of-service" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary whitespace-nowrap">Terms of Service</a>,
                      <a href="https://just-drive.co.uk/privacy-policy/" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary whitespace-nowrap">Privacy Policy</a>,
                      and
                      <a href="https://just-drive.co.uk/cookies-policy/" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary whitespace-nowrap">Cookie Policy</a>
                    </Label>
                  </div>
                  <p v-if="form.errors.privacy_consent" id="privacy_consent-error" class="text-sm text-destructive flex items-start ml-7">
                    <AlertCircle class="mr-1 mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ form.errors.privacy_consent }}</span>
                  </p>

                </div>

                <p class="text-xs text-muted-foreground">
                  By submitting your details you agree to being contacted about driving lessons.
                </p>

                <Alert>
                  <Shield class="h-4 w-4" />
                  <AlertTitle>Your data is safe with us</AlertTitle>
                  <AlertDescription>
                    We'll only use your contact details to check coverage and get in touch about your enquiry.
                    You can unsubscribe from marketing emails at any time.
                  </AlertDescription>
                </Alert>
              </form>

              <div class="flex justify-between items-center mt-8">
                <div class="flex items-center space-x-4 ml-auto">
                  <Button
                    @click="submit"
                    :disabled="form.processing"
                    class="cursor-pointer"
                  >
                    Check my area
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

    <Toaster />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { usePage, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Spinner } from '@/components/ui/spinner'
import { Toaster } from '@/components/ui/toast'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import { useSchoolContact } from '@/composables/useSchoolContact'
import {
  MapPin,
  Phone,
  Mail,
  Shield,
  Lock,
  CircleCheck,
  AlertCircle,
  Info,
  ArrowRight
} from 'lucide-vue-next'
import {
  focusFirstError,
  toE164Phone,
  validateBookerPhone,
  validateEmail,
  validateName,
  validatePostcode,
  type FieldError,
} from '@/lib/contactValidation'

const page = usePage()
const { contact, phoneHref, emailHref } = useSchoolContact()

const existingData = (page.props.enquiry as any)?.data?.steps?.step1 || {}

const countryCodes = [
  { code: '+44', flag: '🇬🇧', name: 'United Kingdom' },
  { code: '+353', flag: '🇮🇪', name: 'Ireland' },
  { code: '+1', flag: '🇺🇸', name: 'United States / Canada' },
  { code: '+61', flag: '🇦🇺', name: 'Australia' },
  { code: '+64', flag: '🇳🇿', name: 'New Zealand' },
  { code: '+33', flag: '🇫🇷', name: 'France' },
  { code: '+49', flag: '🇩🇪', name: 'Germany' },
  { code: '+34', flag: '🇪🇸', name: 'Spain' },
  { code: '+39', flag: '🇮🇹', name: 'Italy' },
  { code: '+351', flag: '🇵🇹', name: 'Portugal' },
  { code: '+31', flag: '🇳🇱', name: 'Netherlands' },
  { code: '+32', flag: '🇧🇪', name: 'Belgium' },
  { code: '+48', flag: '🇵🇱', name: 'Poland' },
  { code: '+40', flag: '🇷🇴', name: 'Romania' },
  { code: '+359', flag: '🇧🇬', name: 'Bulgaria' },
  { code: '+370', flag: '🇱🇹', name: 'Lithuania' },
  { code: '+91', flag: '🇮🇳', name: 'India' },
  { code: '+92', flag: '🇵🇰', name: 'Pakistan' },
  { code: '+880', flag: '🇧🇩', name: 'Bangladesh' },
  { code: '+234', flag: '🇳🇬', name: 'Nigeria' },
  { code: '+27', flag: '🇿🇦', name: 'South Africa' },
  { code: '+86', flag: '🇨🇳', name: 'China' },
  { code: '+852', flag: '🇭🇰', name: 'Hong Kong' },
  { code: '+971', flag: '🇦🇪', name: 'United Arab Emirates' },
  { code: '+90', flag: '🇹🇷', name: 'Turkey' },
]

// Saved phones are E.164 (e.g. +447710896753); older drafts may be 07710-style.
// Split back into a dial code + national number for editing.
function splitPhone(phone: string): { code: string; national: string } {
  if (!phone.startsWith('+')) {
    return { code: '+44', national: phone }
  }

  const match = countryCodes
    .map((country) => country.code)
    .sort((a, b) => b.length - a.length)
    .find((code) => phone.startsWith(code))

  return match
    ? { code: match, national: phone.slice(match.length) }
    : { code: '+44', national: phone.slice(1) }
}

const existingPhone = splitPhone(existingData.phone || '')

const countryCode = ref(existingPhone.code)

const form = useForm({
  first_name: existingData.first_name || '',
  last_name: existingData.last_name || '',
  email: existingData.email || '',
  phone: existingPhone.national,
  postcode: existingData.postcode || '',
  transmission: existingData.transmission || 'manual',
  privacy_consent: existingData.privacy_consent || false,
})

const privacyConsent = ref(form.privacy_consent)

watch(privacyConsent, (newValue) => {
  form.privacy_consent = newValue
})

const fieldIds = {
  first_name: 'first_name',
  last_name: 'last_name',
  email: 'email',
  phone: 'phone',
  postcode: 'postcode',
  transmission: 'transmission',
  privacy_consent: 'privacy_consent',
}

type BookerField = keyof typeof fieldIds

const bookerFields = Object.keys(fieldIds) as BookerField[]

const touchedFields = ref<Partial<Record<BookerField, boolean>>>({})

function fieldError(field: BookerField): FieldError {
  switch (field) {
    case 'first_name':
      return validateName(form.first_name, 'Please enter your first name', 'First name')
    case 'last_name':
      return validateName(form.last_name, 'Please enter your last name', 'Last name')
    case 'email':
      return validateEmail(form.email, 'Please enter your email address')
    case 'phone':
      return validateBookerPhone(form.phone, countryCode.value)
    case 'postcode':
      return validatePostcode(form.postcode)
    case 'transmission':
      return form.transmission ? null : 'Please choose a transmission preference'
    case 'privacy_consent':
      return privacyConsent.value ? null : 'You must agree to the Terms of Service and Privacy Policy to continue'
    default:
      return null
  }
}

function validateField(field: BookerField): FieldError {
  const message = fieldError(field)

  if (message) {
    form.setError(field, message)
  } else {
    form.clearErrors(field)
  }

  return message
}

function onFieldBlur(field: BookerField) {
  touchedFields.value[field] = true
  validateField(field)
}

// Once a field has been left (or the form submitted), re-check it as the user corrects it.
for (const field of bookerFields) {
  watch(
    () => (field === 'privacy_consent' ? privacyConsent.value : form[field]),
    () => {
      if (touchedFields.value[field] || field === 'privacy_consent') {
        validateField(field)
      }
    },
  )
}

watch(countryCode, () => {
  if (touchedFields.value.phone) {
    validateField('phone')
  }
})

function submit() {
  const errors: Partial<Record<BookerField, string>> = {}

  for (const field of bookerFields) {
    touchedFields.value[field] = true
    const message = validateField(field)

    if (message) {
      errors[field] = message
    }
  }

  if (Object.keys(errors).length > 0) {
    focusFirstError(fieldIds, errors)
    return
  }

  const enquiry = page.props.enquiry as any
  if (!enquiry?.id) {
    console.error('No enquiry ID found')
    return
  }

  form
    .transform((data) => ({
      ...data,
      phone: toE164Phone(data.phone, countryCode.value),
    }))
    .post(`/booking/${enquiry.id}/step/1`, {
      preserveScroll: 'errors',
      onError: (serverErrors) => focusFirstError(fieldIds, serverErrors),
    })
}
</script>
