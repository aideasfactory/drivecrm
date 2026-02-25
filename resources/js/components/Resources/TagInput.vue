<script setup lang="ts">
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { X } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string[];
    disabled?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const inputValue = ref('');

const addTag = () => {
    const tag = inputValue.value.trim().toLowerCase();
    if (tag && !props.modelValue.includes(tag)) {
        emit('update:modelValue', [...props.modelValue, tag]);
    }
    inputValue.value = '';
};

const removeTag = (tag: string) => {
    emit(
        'update:modelValue',
        props.modelValue.filter((t) => t !== tag),
    );
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addTag();
    }
    if (
        e.key === 'Backspace' &&
        inputValue.value === '' &&
        props.modelValue.length > 0
    ) {
        removeTag(props.modelValue[props.modelValue.length - 1]);
    }
};
</script>

<template>
    <div class="space-y-2">
        <div
            v-if="modelValue.length > 0"
            class="flex flex-wrap gap-1.5"
        >
            <Badge
                v-for="tag in modelValue"
                :key="tag"
                variant="secondary"
                class="gap-1 pr-1"
            >
                {{ tag }}
                <button
                    type="button"
                    class="hover:bg-muted ml-0.5 rounded-full p-0.5"
                    :disabled="disabled"
                    @click="removeTag(tag)"
                >
                    <X class="h-3 w-3" />
                </button>
            </Badge>
        </div>
        <Input
            v-model="inputValue"
            :placeholder="placeholder || 'Type a tag and press Enter'"
            :disabled="disabled"
            @keydown="handleKeydown"
        />
    </div>
</template>
