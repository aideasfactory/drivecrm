<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { CalendarCheck, ReceiptText, ChevronRight, BarChart3 } from 'lucide-vue-next';
import type { Component } from 'vue';

interface ReportLink {
    key: string;
    title: string;
    description: string;
    icon: string;
    route: string;
}

interface Props {
    reports: ReportLink[];
}

defineProps<Props>();

const iconMap: Record<string, Component> = {
    CalendarCheck,
    ReceiptText,
};

const resolveIcon = (icon: string): Component => iconMap[icon] ?? BarChart3;
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports' }]">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Reports</h1>
                <p class="text-sm text-muted-foreground">Select a report to view its details.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="report in reports" :key="report.key" :href="report.route" class="group">
                    <Card class="h-full transition-colors hover:border-primary/50 hover:bg-accent/40">
                        <CardHeader>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <component :is="resolveIcon(report.icon)" class="h-5 w-5 text-primary" />
                                    </div>
                                    <CardTitle class="text-base">{{ report.title }}</CardTitle>
                                </div>
                                <ChevronRight class="h-5 w-5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <CardDescription>{{ report.description }}</CardDescription>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
