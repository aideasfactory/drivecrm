<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Pencil } from 'lucide-vue-next'
import { channels } from '@/components/Workflows/channels'
import type { WorkflowMessage } from '@/types/workflow'

interface Props {
    message: WorkflowMessage
}

const props = defineProps<Props>()

const emit = defineEmits<{
    (e: 'open-template', key: string): void
}>()

const channel = computed(() => channels[props.message.channel])
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-md border px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex min-w-0 items-start gap-3">
            <component
                :is="channel.icon"
                class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
            />
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        :variant="
                            message.channel === 'none' ? 'outline' : 'secondary'
                        "
                    >
                        {{ channel.label }}
                    </Badge>
                    <span
                        class="text-sm"
                        :class="
                            message.channel === 'none'
                                ? 'text-muted-foreground'
                                : 'font-medium'
                        "
                    >
                        {{ message.label }}
                    </span>
                </div>
                <p
                    v-if="message.recipient && message.channel !== 'bird'"
                    class="text-xs text-muted-foreground"
                >
                    To: {{ message.recipient }}
                </p>
                <p v-if="message.note" class="text-xs text-muted-foreground">
                    {{ message.note }}
                </p>
            </div>
        </div>
        <Button
            v-if="message.channel === 'email' && message.template"
            type="button"
            variant="outline"
            size="sm"
            class="shrink-0 cursor-pointer self-start sm:self-center"
            @click="emit('open-template', message.template)"
        >
            <Pencil class="mr-2 h-3 w-3" />
            View email
        </Button>
    </div>
</template>
