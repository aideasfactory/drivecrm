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

interface FolderItem {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    open: boolean;
    folder: FolderItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'updated'): void;
}>();

const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ name: '' });

watch(
    () => props.open,
    (val) => {
        if (val && props.folder) {
            form.value = { name: props.folder.name };
            errors.value = {};
        }
    },
);

const handleSubmit = async () => {
    if (!props.folder) return;

    errors.value = {};
    isSubmitting.value = true;

    try {
        await axios.put(`/resources/folders/${props.folder.id}`, {
            name: form.value.name,
        });
        toast.success('Folder renamed successfully');
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
                error.response?.data?.message || 'Failed to rename folder',
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
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Pencil class="h-5 w-5" />
                    Rename Folder
                </SheetTitle>
                <SheetDescription>
                    Update the name of this folder.
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-6 space-y-6 px-6 py-4"
                @submit.prevent="handleSubmit"
            >
                <div class="space-y-2">
                    <Label for="edit_folder_name">Folder Name *</Label>
                    <Input
                        id="edit_folder_name"
                        v-model="form.name"
                        placeholder="e.g. Roundabouts"
                        :disabled="isSubmitting"
                    />
                    <p
                        v-if="errors.name"
                        class="text-destructive text-sm"
                    >
                        {{ errors.name }}
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
