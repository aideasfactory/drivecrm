<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FileVideo, FileText, Pencil, Trash2, Eye, Link } from 'lucide-vue-next';

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
    thumbnail_url: string | null;
}

const props = defineProps<{
    resource: ResourceItem;
}>();

const emit = defineEmits<{
    (e: 'preview', resource: ResourceItem): void;
    (e: 'edit', resource: ResourceItem): void;
    (e: 'delete', resource: ResourceItem): void;
}>();

const isVideoLink = computed(() => props.resource.resource_type === 'video_link');

const isVideo = computed(() =>
    props.resource.mime_type?.startsWith('video/') ?? false,
);

const isPdf = computed(
    () => props.resource.mime_type === 'application/pdf',
);

const formattedSize = computed(() => {
    const bytes = props.resource.file_size;
    if (bytes === null || bytes === undefined) return '';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' bytes';
});
</script>

<template>
    <Card>
        <CardContent class="p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3 overflow-hidden">
                    <div
                        v-if="resource.thumbnail_url"
                        class="h-10 w-10 shrink-0 overflow-hidden rounded-lg"
                    >
                        <img
                            :src="resource.thumbnail_url"
                            :alt="resource.title"
                            class="h-full w-full object-cover"
                        />
                    </div>
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            isVideoLink
                                ? 'bg-purple-500/10 text-purple-600'
                                : isVideo
                                    ? 'bg-blue-500/10 text-blue-600'
                                    : 'bg-red-500/10 text-red-600'
                        "
                    >
                        <Link v-if="isVideoLink" class="h-5 w-5" />
                        <FileVideo v-else-if="isVideo" class="h-5 w-5" />
                        <FileText v-else class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ resource.title }}
                        </p>
                        <p
                            v-if="resource.description"
                            class="text-muted-foreground mt-0.5 line-clamp-2 text-sm"
                        >
                            {{ resource.description }}
                        </p>
                        <p v-if="isVideoLink" class="text-muted-foreground mt-1 text-xs truncate">
                            Video Link
                        </p>
                        <p v-else class="text-muted-foreground mt-1 text-xs">
                            {{ resource.file_name }} &middot;
                            {{ formattedSize }}
                        </p>
                        <div
                            v-if="resource.tags && resource.tags.length > 0"
                            class="mt-2 flex flex-wrap gap-1"
                        >
                            <Badge
                                v-for="tag in resource.tags"
                                :key="tag"
                                variant="outline"
                                class="text-xs"
                            >
                                {{ tag }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0"
                        @click="emit('preview', resource)"
                    >
                        <Eye class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0"
                        @click="emit('edit', resource)"
                    >
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0 text-destructive hover:text-destructive"
                        @click="emit('delete', resource)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
