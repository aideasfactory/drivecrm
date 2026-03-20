<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { type BreadcrumbItem } from '@/types';
import { Camera, Loader2, Trash2, Upload } from 'lucide-vue-next';
import { toast } from '@/components/ui/sonner';

type InstructorData = {
    id: number;
    avatar: string | null;
    profile_picture_url: string | null;
    has_profile_picture: boolean;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    instructor?: InstructorData | null;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const removing = ref(false);
const previewUrl = ref<string | null>(null);

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        return;
    }

    // Client-side validation
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        toast.error('Please select a JPG, PNG, or WebP image.');
        target.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        toast.error('Image must be smaller than 5MB.');
        target.value = '';
        return;
    }

    // Show preview
    previewUrl.value = URL.createObjectURL(file);

    // Upload
    uploading.value = true;
    router.post('/settings/profile/picture', { profile_picture: file } as any, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Profile picture updated.');
            previewUrl.value = null;
        },
        onError: (errors: Record<string, string>) => {
            toast.error(errors.profile_picture || 'Failed to upload profile picture.');
            previewUrl.value = null;
        },
        onFinish: () => {
            uploading.value = false;
            if (target) {
                target.value = '';
            }
        },
    });
};

const removeProfilePicture = () => {
    removing.value = true;
    router.delete('/settings/profile/picture', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Profile picture removed.');
        },
        onError: () => {
            toast.error('Failed to remove profile picture.');
        },
        onFinish: () => {
            removing.value = false;
        },
    });
};

const displayAvatar = () => {
    if (previewUrl.value) {
        return previewUrl.value;
    }
    return props.instructor?.profile_picture_url || props.instructor?.avatar || null;
};

const getInitials = () => {
    if (!user.name) return '?';
    const parts = user.name.split(' ');
    return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <!-- Profile Picture Section (Instructors only) -->
                <div v-if="instructor" class="space-y-4">
                    <Heading
                        variant="small"
                        title="Profile picture"
                        description="Upload a profile picture visible to your students"
                    />

                    <div class="flex items-center gap-6">
                        <div class="relative">
                            <Avatar class="h-20 w-20">
                                <AvatarImage :src="displayAvatar() ?? undefined" :alt="user.name" />
                                <AvatarFallback class="text-lg">{{ getInitials() }}</AvatarFallback>
                            </Avatar>
                            <button
                                type="button"
                                class="absolute bottom-0 right-0 flex h-7 w-7 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm hover:bg-primary/90"
                                @click="triggerFileInput"
                                :disabled="uploading"
                            >
                                <Camera class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="uploading"
                                    @click="triggerFileInput"
                                >
                                    <Loader2 v-if="uploading" class="mr-2 h-4 w-4 animate-spin" />
                                    <Upload v-else class="mr-2 h-4 w-4" />
                                    {{ instructor.has_profile_picture ? 'Change picture' : 'Upload picture' }}
                                </Button>

                                <Button
                                    v-if="instructor.has_profile_picture"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="removing"
                                    @click="removeProfilePicture"
                                >
                                    <Loader2 v-if="removing" class="mr-2 h-4 w-4 animate-spin" />
                                    <Trash2 v-else class="mr-2 h-4 w-4" />
                                    Remove
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                JPG, PNG, or WebP. Max 5MB.
                            </p>
                        </div>
                    </div>

                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        class="hidden"
                        @change="handleFileChange"
                    />
                </div>

                <!-- Separator between sections -->
                <div v-if="instructor" class="border-t" />

                <!-- Existing profile info form -->
                <Heading
                    variant="small"
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
