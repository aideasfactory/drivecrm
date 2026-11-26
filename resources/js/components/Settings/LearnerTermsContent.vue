<script setup lang="ts">
defineProps<{
    /** Server-rendered, escaped HTML from LearnerTermsMarkdown. */
    html: string
}>()
</script>

<template>
    <div
        class="text-sm leading-6 [&_a]:underline [&_h1]:mt-6 [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:mt-5 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mt-4 [&_h3]:font-semibold [&_li]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-3 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-6 [&>*:first-child]:mt-0"
        v-html="html"
    />
</template>
