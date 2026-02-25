<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Folder, Pencil, Trash2 } from 'lucide-vue-next';

interface FolderItem {
    id: number;
    name: string;
    slug: string;
}

defineProps<{
    folder: FolderItem;
}>();

const emit = defineEmits<{
    (e: 'open', folder: FolderItem): void;
    (e: 'edit', folder: FolderItem): void;
    (e: 'delete', folder: FolderItem): void;
}>();
</script>

<template>
    <Card
        class="cursor-pointer transition-colors hover:bg-accent/50"
        @click="emit('open', folder)"
    >
        <CardContent class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div
                        class="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                    >
                        <Folder class="h-5 w-5" />
                    </div>
                    <span class="truncate font-medium">{{ folder.name }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-1" @click.stop>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0"
                        @click="emit('edit', folder)"
                    >
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0 text-destructive hover:text-destructive"
                        @click="emit('delete', folder)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
