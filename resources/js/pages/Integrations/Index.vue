<script setup lang="ts">
import { Bird, Search } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Integrations' }]">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Integrations
                </h1>
                <p class="text-muted-foreground">
                    Miscellaneous tools and third-party services connected to
                    Drive.
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Search class="h-5 w-5" />
                            Instructor Scraper
                            <Badge variant="secondary">Coming soon</Badge>
                        </CardTitle>
                        <CardDescription>
                            Browse the list of instructors gathered by the
                            scraper.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-3/4" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bird class="h-5 w-5" />
                            Bird
                            <Badge variant="secondary">Coming soon</Badge>
                        </CardTitle>
                        <CardDescription>
                            Chatbot conversations pulled back from Bird via
                            their API.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-3/4" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
