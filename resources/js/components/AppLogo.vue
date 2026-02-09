<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Car } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <Car class="h-6 w-6 text-red-600" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="text-xl font-bold">DRIVE</span>
    </div>
</template>
