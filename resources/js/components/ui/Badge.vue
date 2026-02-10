<script setup lang="ts">
import { computed } from 'vue'
import type { HTMLAttributes } from 'vue'
import { badgeVariants, type BadgeVariants } from './badge'
import { cn } from '@/lib/utils'

interface Props {
    variant?: BadgeVariants['variant']
    class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
})

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props

    return delegated
})
</script>

<template>
    <div :class="cn(badgeVariants({ variant }), props.class)" v-bind="delegatedProps">
        <slot />
    </div>
</template>
