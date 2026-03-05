<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { toast } from '@/components/ui/sonner';
import { Pencil, Loader2, Save } from 'lucide-vue-next';
import TagInput from '@/components/Resources/TagInput.vue';

interface ResourceItem {
    id: number;
    title: string;
    description: string | null;
    tags: string[] | null;
    resource_type: 'file' | 'video_link';
    video_url: string | null;
    file_name: string | null;
    file_size: number | null;
    mime_type: string | null;
    file_path: string | null;
    thumbnail_path: string | null;
}

const props = defineProps<{
    open: boolean;
    resource: ResourceItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({
    title: '',
    description: '',
    tags: [] as string[],
});

watch(
    () => props.open,
    (val) => {
        if (val && props.resource) {
            form.value = {
                title: props.resource.title,
                description: props.resource.description || '',
                tags: props.resource.tags || [],
            };
            errors.value = {};
        }
    },
);

const handleSubmit = async () => {
    if (!props.resource) return;

    errors.value = {};
    isSubmitting.value = true;

    try {
        await axios.put(`/resources/files/${props.resource.id}`, {
            title: form.value.title,
            description: form.value.description || null,
            tags: form.value.tags.length > 0 ? form.value.tags : null,
        });
        toast.success('Resource updated successfully');
        emit('update:open', false);
        emit('updated');
    } catch (error: any) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            );
        } else {
            toast.error(
                error.response?.data?.message || 'Failed to update resource',
            );
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Sheet
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <SheetContent side="right" class="sm:max-w-lg">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Pencil class="h-5 w-5" />
                    Edit Resource
                </SheetTitle>
                <SheetDescription>
                    Update the title, description, and tags for this resource.
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-6 space-y-6 px-6 py-4"
                @submit.prevent="handleSubmit"
            >
                <!-- Resource Info (read-only) -->
                <div
                    v-if="resource"
                    class="bg-muted/50 rounded-md p-3"
                >
                    <template v-if="resource.resource_type === 'video_link'">
                        <p class="text-sm font-medium">Video Link</p>
                        <p class="text-muted-foreground truncate text-xs">
                            {{ resource.video_url }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-sm font-medium">
                            {{ resource.file_name }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ resource.mime_type }}
                        </p>
                    </template>
                </div>

                <!-- Title -->
                <div class="space-y-2">
                    <Label for="edit_resource_title">Title *</Label>
                    <Input
                        id="edit_resource_title"
                        v-model="form.title"
                        placeholder="e.g. Turning right at roundabout"
                        :disabled="isSubmitting"
                    />
                    <p
                        v-if="errors.title"
                        class="text-destructive text-sm"
                    >
                        {{ errors.title }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="edit_resource_description"
                        >Description</Label
                    >
                    <textarea
                        id="edit_resource_description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Describe the content of this resource..."
                        :disabled="isSubmitting"
                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <p
                        v-if="errors.description"
                        class="text-destructive text-sm"
                    >
                        {{ errors.description }}
                    </p>
                </div>

                <!-- Tags -->
                <div class="space-y-2">
                    <Label>Tags</Label>
                    <TagInput
                        v-model="form.tags"
                        :disabled="isSubmitting"
                        placeholder="Type a tag and press Enter"
                    />
                    <p class="text-muted-foreground text-xs">
                        Tags help with searching and AI recommendations.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isSubmitting"
                        class="min-w-[140px]"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save v-else class="mr-2 h-4 w-4" />
                        Save Changes
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
