<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { GraduationCap, UserCog, Users } from 'lucide-vue-next';

export type FolderVisibility = 'student' | 'instructor' | 'both';

defineProps<{
    modelValue: FolderVisibility;
    disabled?: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: FolderVisibility): void;
}>();
</script>

<template>
    <div class="space-y-2">
        <Label>Visible to *</Label>
        <div class="flex flex-col gap-2">
            <Button
                type="button"
                :variant="modelValue === 'student' ? 'default' : 'outline'"
                class="flex-1"
                :disabled="disabled"
                @click="emit('update:modelValue', 'student')"
            >
                <GraduationCap class="mr-2 h-4 w-4" />
                Pupils
            </Button>
            <Button
                type="button"
                :variant="modelValue === 'instructor' ? 'default' : 'outline'"
                class="flex-1"
                :disabled="disabled"
                @click="emit('update:modelValue', 'instructor')"
            >
                <UserCog class="mr-2 h-4 w-4" />
                Instructors
            </Button>
            <Button
                type="button"
                :variant="modelValue === 'both' ? 'default' : 'outline'"
                class="flex-1"
                :disabled="disabled"
                @click="emit('update:modelValue', 'both')"
            >
                <Users class="mr-2 h-4 w-4" />
                Both
            </Button>
        </div>
        <p class="text-muted-foreground text-xs">
            Pupils should not see instructor-only folders such as VTS or
            Standards Check Success, even when those folders are empty of pupil
            resources.
        </p>
        <p v-if="error" class="text-destructive text-sm">
            {{ error }}
        </p>
    </div>
</template>
