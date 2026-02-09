<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports' }]">
        <div class="flex flex-col gap-4 p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Reports</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        View analytics and reports. Track performance, revenue,
                        and key metrics.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
