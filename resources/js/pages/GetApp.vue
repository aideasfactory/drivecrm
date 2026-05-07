<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Apple, Smartphone, Calendar, CreditCard, BookOpen, MessageSquare } from 'lucide-vue-next';

defineProps<{
    appName: string;
    iosUrl: string | null;
    androidUrl: string | null;
}>();
</script>

<template>
    <Head :title="`Get the ${appName} app`" />

    <div class="min-h-screen bg-gradient-to-b from-background to-muted">
        <div class="mx-auto flex max-w-3xl flex-col items-center px-6 py-16 lg:py-24">

            <header class="text-center">
                <p class="text-sm font-medium uppercase tracking-wider text-muted-foreground">{{ appName }}</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">
                    Get the {{ appName }} app
                </h1>
                <p class="mt-4 max-w-xl text-lg text-muted-foreground">
                    Manage your driving lessons, track progress, and stay in touch with your instructor — all from your phone.
                </p>
            </header>

            <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                <Button
                    v-if="iosUrl"
                    as="a"
                    :href="iosUrl"
                    size="lg"
                    class="min-w-[220px]"
                >
                    <Apple class="mr-2 h-5 w-5" />
                    Download for iPhone
                </Button>
                <Button v-else size="lg" class="min-w-[220px]" disabled>
                    <Apple class="mr-2 h-5 w-5" />
                    iPhone — coming soon
                </Button>

                <Button
                    v-if="androidUrl"
                    as="a"
                    :href="androidUrl"
                    size="lg"
                    variant="secondary"
                    class="min-w-[220px]"
                >
                    <Smartphone class="mr-2 h-5 w-5" />
                    Download for Android
                </Button>
                <Button v-else size="lg" variant="secondary" class="min-w-[220px]" disabled>
                    <Smartphone class="mr-2 h-5 w-5" />
                    Android — coming soon
                </Button>
            </div>

            <section class="mt-16 grid w-full gap-6 sm:grid-cols-2">
                <div class="flex gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Calendar class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">See your lessons</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Every lesson, time, and meeting point in one place.
                        </p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <CreditCard class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Pay in seconds</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Settle weekly invoices straight from the app.
                        </p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <BookOpen class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Track progress</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Watch your skills tick off, lesson by lesson.
                        </p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <MessageSquare class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">Message your instructor</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Reschedule, ask a question, or send a quick note.
                        </p>
                    </div>
                </div>
            </section>

            <footer class="mt-16 text-center text-xs text-muted-foreground">
                <p>Already received a temporary password by email? You'll use it the first time you sign in to the app.</p>
            </footer>

        </div>
    </div>
</template>
