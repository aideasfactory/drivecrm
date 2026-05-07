<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
</script>

<template>
    <AuthBase
        title="No access"
        description="This area of Drive CRM is only available to instructors and owners."
    >
        <Head title="No access" />

        <div class="flex flex-col gap-4 text-sm text-muted-foreground">
            <p>
                Your account is registered as a student. Please use the Drive CRM
                mobile app to manage your lessons, payments, and progress.
            </p>
            <p>
                If you believe this is a mistake, contact your instructor or
                support.
            </p>
            <p class="pt-2">
                <TextLink :href="login()">Back to log in</TextLink>
            </p>
        </div>
    </AuthBase>
</template>
