<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { LayoutDashboard } from 'lucide-vue-next'

interface Props {
    studentId: number
}

defineProps<Props>()
</script>

<template>
    <Card>
        <CardContent class="flex flex-col items-center gap-3 py-12 text-muted-foreground">
            <LayoutDashboard class="h-10 w-10" />
            <div class="text-center">
                <p class="font-medium">Overview</p>
                <p class="mt-1 text-sm">Student overview coming soon</p>
            </div>
        </CardContent>
    </Card>
</template>
