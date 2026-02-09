<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Edit } from 'lucide-vue-next'
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <div
                class="flex min-h-[400px] flex-col items-center justify-center gap-4 text-muted-foreground"
            >
                <Edit class="h-12 w-12" />
                <div class="text-center">
                    <p class="text-lg font-medium">Edit Details & Packages</p>
                    <p class="mt-2 text-sm">
                        Form to edit instructor details and package assignments
                        will be implemented here
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
