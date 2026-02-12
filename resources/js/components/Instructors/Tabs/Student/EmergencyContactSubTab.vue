<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { ShieldAlert } from 'lucide-vue-next'

interface Props {
    studentId: number
}

defineProps<Props>()
</script>

<template>
    <Card>
        <CardContent class="flex flex-col items-center gap-3 py-12 text-muted-foreground">
            <ShieldAlert class="h-10 w-10" />
            <div class="text-center">
                <p class="font-medium">Emergency Contact</p>
                <p class="mt-1 text-sm">Emergency contacts coming soon</p>
            </div>
        </CardContent>
    </Card>
</template>
