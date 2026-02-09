<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Teams' }]">
        <div class="flex flex-col gap-4 p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Teams</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        Organize instructors and pupils into teams. Manage team
                        assignments and collaboration.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
