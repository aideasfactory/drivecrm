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

    <!-- IN-AREA: success / confirmation -->
    <main v-if="inArea" class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
      <Card>
        <CardHeader>
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="space-y-2">
              <Badge class="gap-1.5">
                <CircleCheck class="h-3.5 w-3.5" />
                Confirmed
              </Badge>
              <CardTitle class="text-3xl">You're in. Lessons coming your way.</CardTitle>
              <CardDescription class="text-base">
                Your instructor match is being sorted now. Check your inbox — we'll be in touch today.
              </CardDescription>
            </div>
            <div class="flex-shrink-0">
              <Card class="text-center">
                <CardContent class="px-6 py-4">
                  <div class="text-3xl font-bold leading-none">24h</div>
                  <div class="text-xs text-muted-foreground mt-1">until you hear from us</div>
                </CardContent>
              </Card>
            </div>
          </div>
        </CardHeader>
      </Card>

      <!-- Video placeholder (real asset to follow) -->
      <!-- <Card class="overflow-hidden">
        <div class="relative flex aspect-video w-full flex-col items-center justify-center gap-3 bg-muted text-center">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
            <Play class="h-7 w-7" />
          </div>
          <div>
            <p class="font-semibold">What happens after you sign up?</p>
            <p class="text-sm text-muted-foreground">2 min · Watch while you wait for our call</p>
          </div>
        </div>
      </Card> -->

      <!-- Progress steps -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Card v-for="step in journeySteps" :key="step.label">
          <CardHeader>
            <div class="flex items-center justify-between">
              <Badge :variant="step.done ? 'default' : 'secondary'" class="uppercase tracking-wide">
                {{ step.label }}
              </Badge>
              <component
                :is="step.done ? CircleCheck : Circle"
                class="h-5 w-5"
                :class="step.done ? 'text-primary' : 'text-muted-foreground'"
              />
            </div>
            <CardTitle class="text-base pt-2">{{ step.title }}</CardTitle>
            <CardDescription>{{ step.description }}</CardDescription>
          </CardHeader>
        </Card>
      </div>

      <!-- Guarantee -->
      <Card>
        <CardContent class="flex items-start gap-4 px-6 py-4">
          <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
            <ShieldCheck class="h-5 w-5" />
          </div>
          <div>
            <p class="font-semibold">Pass Your Test Guarantee included</p>
            <p class="text-sm text-muted-foreground">
              Book 10 hours or more and if you don't pass first time, DRIVE pays for your second test.
              No forms, no fuss — just book and drive.
            </p>
          </div>
        </CardContent>
      </Card>

      <!-- Share -->
      <Card>
        <CardContent class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-6 py-4">
          <p class="text-sm text-muted-foreground">
            Know someone else who needs lessons? Send them to DRIVE.
          </p>
          <div class="flex gap-2">
            <Button variant="outline" @click="shareLink">
              <Share2 class="mr-2 h-4 w-4" />
              Share
            </Button>
            <Button variant="outline" @click="copyLink">
              <Link2 class="mr-2 h-4 w-4" />
              Copy link
            </Button>
          </div>
        </CardContent>
      </Card>

      <div class="flex justify-center pt-2">
        <CookiePreferencesLink />
      </div>
    </main>

    <!-- OUT-OF-AREA: no availability yet -->
    <main v-else class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
      <Card>
        <CardHeader class="text-center">
          <div class="mx-auto mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
            <MapPinOff class="h-8 w-8" />
          </div>
          <CardTitle class="text-3xl">Not in your area yet.</CardTitle>
          <CardDescription class="text-base">
            We're expanding across the UK all the time. We don't have an instructor near you right now,
            but here's how we can still help.
          </CardDescription>
          <div v-if="postcode" class="flex justify-center pt-3">
            <Badge variant="secondary" class="gap-1.5">
              <Search class="h-3.5 w-3.5" />
              Searched: {{ postcode }}
            </Badge>
          </div>
        </CardHeader>
      </Card>

      <!-- Video placeholder (real asset to follow) -->
      <!-- <Card class="overflow-hidden">
        <div class="relative flex aspect-video w-full flex-col items-center justify-center gap-3 bg-muted text-center">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
            <Play class="h-7 w-7" />
          </div>
          <div>
            <p class="font-semibold">What happens after you sign up?</p>
            <p class="text-sm text-muted-foreground">2 min · Watch while you learn more about DRIVE</p>
          </div>
        </div>
      </Card> -->

      <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-3">Your options</p>
        <div class="space-y-4">
          <Link :href="step1(uuid).url" class="block">
            <Card class="transition-colors hover:bg-accent">
              <CardContent class="flex items-center gap-4 px-6 py-4">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                  <MapPin class="h-5 w-5" />
                </div>
                <div class="flex-1">
                  <p class="font-semibold">Try a nearby postcode</p>
                  <p class="text-sm text-muted-foreground">
                    We might have an instructor a short drive away — worth a quick check.
                  </p>
                </div>
                <ChevronRight class="h-5 w-5 flex-shrink-0 text-muted-foreground" />
              </CardContent>
            </Card>
          </Link>

          <a href="tel:08003689215" class="block">
            <Card class="transition-colors hover:bg-accent">
              <CardContent class="flex items-center gap-4 px-6 py-4">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                  <PhoneCall class="h-5 w-5" />
                </div>
                <div class="flex-1">
                  <p class="font-semibold">Call us on 0800 368 9215</p>
                  <p class="text-sm text-muted-foreground">
                    Our team can often find solutions that aren't visible online.
                  </p>
                </div>
                <ChevronRight class="h-5 w-5 flex-shrink-0 text-muted-foreground" />
              </CardContent>
            </Card>
          </a>

          <Card>
            <CardContent class="flex items-center gap-4 px-6 py-4">
              <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                <BellRing class="h-5 w-5" />
              </div>
              <div class="flex-1">
                <p class="font-semibold">We'll notify you when we expand</p>
                <p class="text-sm text-muted-foreground">
                  We already have your email — we'll reach out the moment coverage opens near you.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Card>
        <CardContent class="px-6 py-4 text-sm text-muted-foreground">
          We're adding new instructors every week. <strong class="text-foreground">We'll be in touch</strong>
          as soon as someone is available in your area.
        </CardContent>
      </Card>

      <div class="flex justify-center pt-2">
        <CookiePreferencesLink />
      </div>
    </main>

    <Toaster />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Toaster, toast } from '@/components/ui/toast'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import CookiePreferencesLink from '@/components/CookiePreferencesLink.vue'
import { step1 } from '@/routes/booking'
import {
  Phone,
  Mail,
  Play,
  Circle,
  CircleCheck,
  ShieldCheck,
  Share2,
  Link2,
  MapPin,
  MapPinOff,
  PhoneCall,
  BellRing,
  Search,
  ChevronRight,
} from 'lucide-vue-next'

const props = defineProps<{
  uuid: string
  currentStep: number
  totalSteps: number
  postcode: string | null
  inArea: boolean
  maxStepReached: number
}>()

const journeySteps = [
  {
    label: 'Done',
    title: 'Sign-up complete',
    description: 'Your details are confirmed.',
    done: true,
  },
  {
    label: 'Today',
    title: 'Instructor matched',
    description: 'We find your local DVSA-approved instructor.',
    done: false,
  },
  {
    label: 'Soon',
    title: 'First lesson booked',
    description: 'Your instructor calls to arrange a time.',
    done: false,
  },
  {
    label: 'Pass',
    title: 'Test passed — guaranteed',
    description: '10+ hour package: we cover your retest if you need it.',
    done: false,
  },
]

const shareUrl = 'https://just-drive.co.uk'

const shareLink = async () => {
  if (navigator.share) {
    try {
      await navigator.share({
        title: 'DRIVE Driving School',
        text: 'Learn to drive with DRIVE.',
        url: shareUrl,
      })
    } catch {
      // User dismissed the share sheet — nothing to do.
    }
    return
  }

  copyLink()
}

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(shareUrl)
    toast({ title: 'Link copied', description: 'Share it with anyone who needs lessons.' })
  } catch {
    toast({ title: 'Could not copy link', description: 'Please copy it manually.' })
  }
}

// GTM conversion trigger: fires on the /booking/{uuid}/success page.
// Custom-event triggers are more reliable than URL triggers in an Inertia SPA.
onMounted(() => {
  ;(window as any).dataLayer?.push({
    event: 'booking_enquiry_submitted',
    in_area: props.inArea,
  })
})
</script>
