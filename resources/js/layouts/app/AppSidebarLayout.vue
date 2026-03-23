<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/toast';
import { useRole } from '@/composables/useRole';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isInstructor } = useRole();
</script>

<template>
    <!-- Instructor-only layout: no sidebar, full-width content -->
    <template v-if="isInstructor">
        <div class="flex min-h-screen w-full flex-col">
            <main class="flex-1">
                <slot />
            </main>
        </div>
        <Toaster />
    </template>

    <!-- Standard admin layout with sidebar -->
    <template v-else>
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" class="overflow-x-hidden">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <slot />
            </AppContent>
        </AppShell>
        <Toaster />
    </template>
</template>
