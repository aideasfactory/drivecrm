<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import ImportCard from '@/components/Imports/ImportCard.vue'
import WelcomeEmailsCard from '@/components/Imports/WelcomeEmailsCard.vue'
import { Button } from '@/components/ui/button'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { template } from '@/routes/imports'
import type { ImportRun } from '@/types/imports'
import { DatabaseZap, Download } from 'lucide-vue-next'

defineProps<{
    latestRun: ImportRun | null
}>()

const importRunning = ref(false)

const breadcrumbs = [{ title: 'Data Import' }]
</script>

<template>
    <Head title="Data Import" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="flex items-center gap-3 text-3xl font-bold">
                    <DatabaseZap class="h-8 w-8" />
                    Data Import
                </h2>
                <p class="max-w-2xl text-muted-foreground">
                    Bring instructors, pupils, coverage, diary, tests, progress, reflective logs, finances and
                    mileage across from another system. The import only adds what isn't in Drive yet. Imported
                    lessons are never charged or paid out through Stripe. No emails are sent during an import.
                </p>
            </div>

            <!-- Step 1: template -->
            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>1. Get the template</CardTitle>
                    <CardDescription>
                        A zip with the export spec (README) and an example of each CSV.
                        Send it to whoever is exporting the data — the example files
                        link together, so the zip is itself a valid import.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button variant="outline" as="a" :href="template.url()">
                        <Download class="mr-2 h-4 w-4" />
                        Download template
                    </Button>
                </CardContent>
            </Card>

            <!-- Step 2: upload, check (background), import (background, in stages) -->
            <ImportCard :latest-run="latestRun" @running="(value) => (importRunning = value)" />

            <!-- Welcome emails for selected instructors -->
            <WelcomeEmailsCard :import-running="importRunning" />
        </div>
    </AppLayout>
</template>
