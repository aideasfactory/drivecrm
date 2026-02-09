<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Users } from 'lucide-vue-next'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <div
                class="flex min-h-[400px] flex-col items-center justify-center gap-4 text-muted-foreground"
            >
                <Users class="h-12 w-12" />
                <div class="text-center">
                    <p class="text-lg font-medium">Active Pupils</p>
                    <p class="mt-2 text-sm">
                        List of {{ instructor.stats.current_pupils }} active pupils
                        will be displayed here
                    </p>
                    <p class="mt-4 text-xs">
                        Features: pupil cards, progress tracking, lesson history,
                        and quick actions
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
