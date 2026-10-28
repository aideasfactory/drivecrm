<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import WorkflowMessageItem from '@/components/Workflows/WorkflowMessageItem.vue'
import type { Workflow } from '@/types/workflow'

interface Props {
    workflow: Workflow
}

defineProps<Props>()

const emit = defineEmits<{
    (e: 'open-template', key: string): void
    (e: 'preview-template', key: string): void
}>()
</script>

<template>
    <Card>
        <CardHeader class="gap-2">
            <Badge variant="outline" class="w-fit">{{ workflow.group }}</Badge>
            <CardTitle class="text-xl">{{ workflow.title }}</CardTitle>
            <CardDescription>{{ workflow.summary }}</CardDescription>
        </CardHeader>
        <CardContent>
            <ol class="flex flex-col">
                <li
                    v-for="(step, index) in workflow.steps"
                    :key="index"
                    class="relative flex gap-4 pb-6 last:pb-0"
                >
                    <div class="flex flex-col items-center">
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border bg-muted text-xs font-semibold"
                        >
                            {{ index + 1 }}
                        </span>
                        <span
                            v-if="index < workflow.steps.length - 1"
                            class="mt-1 w-px flex-1 bg-border"
                        />
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2 pt-0.5">
                        <h4 class="font-semibold leading-tight">
                            {{ step.when }}
                        </h4>
                        <p class="text-sm text-muted-foreground">
                            {{ step.what }}
                        </p>
                        <div
                            v-if="step.messages.length"
                            class="flex flex-col gap-2"
                        >
                            <WorkflowMessageItem
                                v-for="(message, messageIndex) in step.messages"
                                :key="messageIndex"
                                :message="message"
                                @open-template="emit('open-template', $event)"
                                @preview-template="
                                    emit('preview-template', $event)
                                "
                            />
                        </div>
                    </div>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>
