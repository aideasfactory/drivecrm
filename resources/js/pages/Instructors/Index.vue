<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Instructors' }]">
        <div class="flex flex-col gap-4 p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Instructors</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        Manage your driving instructors. View profiles,
                        availability, and performance metrics.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
