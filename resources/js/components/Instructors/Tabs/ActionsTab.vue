<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Zap } from 'lucide-vue-next'
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <div
                class="flex min-h-[400px] flex-col items-center justify-center gap-4 text-muted-foreground"
            >
                <Zap class="h-12 w-12" />
                <div class="text-center">
                    <p class="text-lg font-medium">Instructor Actions</p>
                    <p class="mt-2 text-sm">
                        Quick actions and bulk operations for this instructor
                    </p>
                    <p class="mt-4 text-xs">
                        Features: send notifications, export data, manage
                        availability, bulk lesson operations
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
