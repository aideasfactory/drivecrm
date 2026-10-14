<script setup lang="ts">
defineProps<{ usualPrice: string }>()
</script>

<template>
    <span class="inline-flex items-baseline gap-1.5 whitespace-nowrap">
        <s class="font-normal text-muted-foreground" aria-label="Usual price">{{ usualPrice }}</s>
        <span>£0.00</span>
    </span>
</template>
