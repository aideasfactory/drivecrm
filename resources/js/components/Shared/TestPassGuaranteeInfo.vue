<script setup lang="ts">
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { Info, ShieldCheck } from 'lucide-vue-next'
import type { HTMLAttributes } from 'vue'

defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
    <Popover>
        <PopoverTrigger
            :class="cn('inline-flex cursor-pointer items-center gap-1.5 rounded-sm text-left underline decoration-dotted underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none', $props.class)"
        >
            <slot>Pass Your Test Guarantee</slot>
            <Info class="size-4 shrink-0 opacity-70" aria-hidden="true" />
        </PopoverTrigger>
        <PopoverContent class="w-80 text-sm font-normal" side="bottom" align="start">
            <div class="flex items-center gap-2 font-semibold">
                <ShieldCheck class="size-4 text-primary" aria-hidden="true" />
                Pass Your Test Guarantee
            </div>
            <div class="mt-2 space-y-2 leading-relaxed text-muted-foreground">
                <p>We're confident you'll pass. So we guarantee it.</p>
                <p>
                    Our instructors only put you in for your test when you're ready. If it still doesn't go your way,
                    we'll pay for your next attempt.
                </p>
                <p>That's how much we trust our training.</p>
            </div>
        </PopoverContent>
    </Popover>
</template>
