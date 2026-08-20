<template>
  <div class="min-h-screen bg-muted/30 flex flex-col">
    <Head :title="title" />

    <!-- Header -->
    <header class="sticky top-0 z-50">
      <Card class="rounded-none border-b border-t-0 border-x-0">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center h-16 w-full">
            <a href="/" class="flex items-center cursor-pointer">
              <AppLogoIcon class="h-8 w-8" />
              <span class="text-xl font-bold ml-2">DRIVE</span>
            </a>
          </div>
        </div>
      </Card>
    </header>

    <!-- Content -->
    <main class="flex-1 w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
      <Card>
        <CardContent class="px-6 py-8 sm:px-10 sm:py-10">
          <div class="mb-8 border-b pb-6">
            <h1 class="text-3xl font-bold tracking-tight">{{ title }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">
              Watch Learn Drive.com Ltd t/a SmartDriving — Driving Instructor Platform
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
              Effective date: {{ effectiveDate }} · Last updated: {{ lastUpdated }}
            </p>
          </div>

          <div class="legal-content">
            <slot />
          </div>

          <div class="mt-10 border-t pt-6 text-sm text-muted-foreground">
            <p>
              See also:
              <template v-for="(link, index) in otherPolicies" :key="link.href">
                <a :href="link.href" class="underline underline-offset-4 hover:text-primary">{{ link.label }}</a><span v-if="index < otherPolicies.length - 1">, </span>
              </template>
            </p>
            <p class="mt-4">© {{ new Date().getFullYear() }} Watch Learn Drive.com Ltd t/a SmartDriving. All rights reserved.</p>
          </div>
        </CardContent>
      </Card>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import AppLogoIcon from '@/components/AppLogoIcon.vue'

interface Props {
  title: string
  effectiveDate: string
  lastUpdated: string
}

const props = defineProps<Props>()

const allPolicies = [
  { label: 'Terms of Service', href: '/terms-of-service' },
  { label: 'Privacy Policy', href: '/privacy-policy' },
  { label: 'Cookie Policy', href: '/cookie-policy' },
]

const otherPolicies = computed(() => allPolicies.filter((policy) => policy.label !== props.title))
</script>

<style>
.legal-content h2 {
  font-size: 1.25rem;
  font-weight: 600;
  letter-spacing: -0.01em;
  margin-top: 2rem;
  margin-bottom: 0.75rem;
}

.legal-content h3 {
  font-size: 1.05rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
}

.legal-content > h2:first-child {
  margin-top: 0;
}

.legal-content p {
  margin-bottom: 0.875rem;
  line-height: 1.7;
  color: var(--foreground);
}

.legal-content ul,
.legal-content ol {
  margin: 0.5rem 0 1rem;
  padding-left: 1.5rem;
  line-height: 1.7;
}

.legal-content ul {
  list-style-type: disc;
}

.legal-content ol {
  list-style-type: lower-alpha;
}

.legal-content li {
  margin-bottom: 0.375rem;
}

.legal-content li::marker {
  color: var(--muted-foreground);
}

.legal-content a {
  text-decoration: underline;
  text-underline-offset: 4px;
}

.legal-content a:hover {
  color: var(--primary);
}

.legal-content table {
  width: 100%;
  margin: 1rem 0 1.5rem;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.legal-content th,
.legal-content td {
  border: 1px solid var(--border);
  padding: 0.5rem 0.75rem;
  text-align: left;
  vertical-align: top;
}

.legal-content th {
  background: var(--muted);
  font-weight: 600;
}

.legal-content .table-wrap {
  overflow-x: auto;
}
</style>
