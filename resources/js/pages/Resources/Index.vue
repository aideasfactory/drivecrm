<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Resources' }]">
        <div class="flex flex-col gap-4 p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Resources</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        Access learning materials, documents, and training
                        resources for instructors and pupils.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
