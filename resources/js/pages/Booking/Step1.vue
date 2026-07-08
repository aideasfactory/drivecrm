<template>
  <div class="min-h-screen">
    <header class="sticky top-0 z-50">
      <Card class="rounded-none border-b border-t-0 border-x-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between h-16 w-full">
            <div class="flex-shrink-0 min-w-[200px]">
              <span class="flex items-center w-8">
                <AppLogoIcon class="w-4 fill-current text-[var(--foreground)] dark:text-white" />
                <span class="text-xl font-bold ml-2">DRIVE</span>
              </span>
            </div>
            <div class="flex items-center gap-4 sm:gap-6 text-sm">
              <a href="tel:08003689215" class="hidden md:flex items-center hover:text-primary">
                <Phone class="mr-1.5 h-4 w-4" />
                0800 368 9215
              </a>
              <a href="mailto:lessons@just-drive.co.uk" class="flex items-center hover:text-primary">
                <Mail class="mr-1.5 h-4 w-4" />
                lessons@just-drive.co.uk
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
              <CardTitle>DRIVE Driving School</CardTitle>
              <CardDescription class="space-y-2">
                <div class="flex items-center">
                  <Phone class="mr-2 h-4 w-4" />
                  <span>0800 368 9215</span>
                </div>
                <div class="flex items-center">
                  <Mail class="mr-2 h-4 w-4" />
                  <span>lessons@just-drive.co.uk</span>
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
                  <div class="flex gap-2">
                    <select
                      id="country-code"
                      v-model="countryCode"
                      aria-label="Country code"
                      class="h-9 w-28 shrink-0 rounded-md border border-input bg-transparent dark:bg-input/30 px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
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
                      placeholder="7710 896753"
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
                    {{ countryCode === '+44' ? 'Enter your mobile number without the leading 0, e.g. 7710 896753' : 'Enter your number without the country code or leading 0' }}
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

                <div class="space-y-2">
                  <Label for="transmission">
                    Transmission preference <span class="text-destructive">*</span>
                  </Label>
                  <select
                    id="transmission"
                    v-model="form.transmission"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="{ 'border-destructive': form.errors.transmission }"
                  >
                    <option value="manual">Manual</option>
                    <option value="automatic">Automatic</option>
                  </select>
                  <p v-if="form.errors.transmission" class="text-sm text-destructive flex items-center">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.transmission }}
                  </p>
                </div>

                <div class="space-y-4 pt-4">
                  <Separator />

                  <div class="flex items-start space-x-3">
                    <input
                      type="checkbox"
                      id="privacy_consent"
                      v-model="privacyConsent"
                      required
                      class="h-4 w-4 mt-1 rounded border-input text-primary focus:ring-2 focus:ring-ring focus:ring-offset-2 cursor-pointer"
                    />
                    <Label for="privacy_consent" class="text-sm leading-relaxed cursor-pointer">
                      I have read and agree to the
                      <a href="/policy/TermsofService.pdf" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary whitespace-nowrap">Terms of Service</a>,
                      <a href="/policy/PrivacyPolicy.pdf" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary whitespace-nowrap">Privacy Policy</a>,
                      and
                      <a href="/policy/CookiePolicy.pdf" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary whitespace-nowrap">Cookie Policy</a>
                    </Label>
                  </div>
                  <p v-if="form.errors.privacy_consent" class="text-sm text-destructive flex items-center ml-7">
                    <AlertCircle class="mr-1 h-4 w-4" />
                    {{ form.errors.privacy_consent }}
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
                    :disabled="!isFormValid || form.processing"
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
import { ref, computed, watch } from 'vue'
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
  ArrowRight
} from 'lucide-vue-next'

const page = usePage()

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

const isFormValid = computed(() => {
  const hasRequiredFields = form.first_name.trim() &&
                           form.last_name.trim() &&
                           form.email.trim() &&
                           form.phone.trim() &&
                           form.postcode.trim() &&
                           form.transmission &&
                           privacyConsent.value

  if (!hasRequiredFields) return false

  return validateEmail(form.email) &&
         validatePhone(form.phone) &&
         validatePostcode(form.postcode)
})

function validateEmail(email: string) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return re.test(email)
}

// National number as bare digits, forgiving a typed leading 0 (e.g. "07710 896753" → "7710896753").
function nationalDigits(phone: string) {
  const digits = phone.replace(/\D/g, '')
  return digits.startsWith('0') ? digits.slice(1) : digits
}

function validatePhone(phone: string) {
  const digits = nationalDigits(phone)
  if (!digits) return false

  if (countryCode.value === '+44') {
    return /^7\d{9}$/.test(digits)
  }

  const totalLength = countryCode.value.replace(/\D/g, '').length + digits.length
  return totalLength >= 8 && totalLength <= 15
}

function validatePostcode(postcode: string) {
  const re = /^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i
  return re.test(postcode.trim())
}

function submit() {
  if (!isFormValid.value) return

  const enquiry = page.props.enquiry as any
  if (!enquiry?.id) {
    console.error('No enquiry ID found')
    return
  }

  form
    .transform((data) => ({
      ...data,
      phone: countryCode.value + nationalDigits(data.phone),
    }))
    .post(`/booking/${enquiry.id}/step/1`, {
      preserveScroll: 'errors',
    })
}
</script>
