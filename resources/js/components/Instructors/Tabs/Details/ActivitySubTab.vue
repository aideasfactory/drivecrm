<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Activity } from 'lucide-vue-next'
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <div
                class="flex min-h-[400px] flex-col items-center justify-center gap-4 text-muted-foreground"
            >
                <Activity class="h-12 w-12" />
                <div class="text-center">
                    <p class="text-lg font-medium">Activity Log</p>
                    <p class="mt-2 text-sm">
                        Timeline of instructor activities, changes, and events will
                        be displayed here
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
