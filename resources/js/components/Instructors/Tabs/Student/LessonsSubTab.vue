<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { BookOpen } from 'lucide-vue-next'

interface Props {
    studentId: number
}

defineProps<Props>()
</script>

<template>
    <Card>
        <CardContent class="flex flex-col items-center gap-3 py-12 text-muted-foreground">
            <BookOpen class="h-10 w-10" />
            <div class="text-center">
                <p class="font-medium">Lessons</p>
                <p class="mt-1 text-sm">Lesson history coming soon</p>
            </div>
        </CardContent>
    </Card>
</template>
