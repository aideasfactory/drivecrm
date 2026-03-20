<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useTeamTheme } from '@/composables/useTeamTheme';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

useTeamTheme();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
