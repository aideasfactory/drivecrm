<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Apps' }]">
        <div class="flex flex-col gap-4 p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Apps</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        Manage integrations and connected applications. Connect
                        third-party services.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
