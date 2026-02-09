<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Pupils' }]">
        <div class="flex flex-col gap-4 p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Pupils</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground">
                        Manage your learner drivers. View progress, bookings,
                        and lesson history.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
