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
import { FolderPlus, Loader2, Save } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
    parentId: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'created'): void;
}>();

const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const form = ref({ name: '' });

watch(
    () => props.open,
    (val) => {
        if (val) {
            form.value = { name: '' };
            errors.value = {};
        }
    },
);

const handleSubmit = async () => {
    errors.value = {};
    isSubmitting.value = true;

    try {
        await axios.post('/resources/folders', {
            name: form.value.name,
            parent_id: props.parentId,
        });
        toast.success('Folder created successfully');
        emit('update:open', false);
        emit('created');
    } catch (error: any) {
        if (error.response?.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            );
        } else {
            toast.error(
                error.response?.data?.message || 'Failed to create folder',
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
                    <FolderPlus class="h-5 w-5" />
                    Create Folder
                </SheetTitle>
                <SheetDescription>
                    Create a new folder to organise your resources.
                </SheetDescription>
            </SheetHeader>

            <form
                class="mt-6 space-y-6 px-6 py-4"
                @submit.prevent="handleSubmit"
            >
                <div class="space-y-2">
                    <Label for="folder_name">Folder Name *</Label>
                    <Input
                        id="folder_name"
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
                        Create Folder
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
