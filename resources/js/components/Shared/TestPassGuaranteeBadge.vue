<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { ShieldCheck } from 'lucide-vue-next'

withDefaults(defineProps<{ short?: boolean }>(), {
    short: false,
})
</script>

<template>
    <Badge title="Pass Your Test Guarantee">
        <ShieldCheck />
        {{ short ? 'Pass Guarantee' : 'Pass Your Test Guarantee' }}
    </Badge>
</template>
