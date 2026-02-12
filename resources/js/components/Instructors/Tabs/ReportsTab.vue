<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { BarChart3 } from 'lucide-vue-next'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
}

defineProps<Props>()
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <div
                class="flex min-h-[400px] flex-col items-center justify-center gap-4 text-muted-foreground"
            >
                <BarChart3 class="h-12 w-12" />
                <div class="text-center">
                    <p class="text-lg font-medium">Instructor Reports</p>
                    <p class="mt-2 text-sm">
                        View analytics and performance reports for this instructor
                    </p>
                    <p class="mt-4 text-xs">
                        Features: lesson stats, revenue tracking, pupil progress, attendance reports
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
