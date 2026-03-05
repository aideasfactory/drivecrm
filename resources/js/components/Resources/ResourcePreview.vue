<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { ExternalLink, FileText } from 'lucide-vue-next';

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
}>();

const fileUrl = ref('');
const loadingUrl = ref(false);

const isVideoLink = computed(() => props.resource?.resource_type === 'video_link');

const isVideo = computed(() =>
    props.resource?.mime_type?.startsWith('video/') ?? false,
);

const isPdf = computed(
    () => props.resource?.mime_type === 'application/pdf',
);

/**
 * Extract an embeddable URL from a YouTube or Vimeo link.
 */
const embedUrl = computed(() => {
    const url = props.resource?.video_url;
    if (!url) return '';

    // YouTube: https://www.youtube.com/watch?v=ID or https://youtu.be/ID
    const ytMatch = url.match(
        /(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/,
    );
    if (ytMatch) {
        return `https://www.youtube.com/embed/${ytMatch[1]}`;
    }

    // Vimeo: https://vimeo.com/ID
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) {
        return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    }

    return '';
});

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && props.resource) {
            if (isVideoLink.value) {
                fileUrl.value = props.resource.video_url ?? '';
                loadingUrl.value = false;
                return;
            }
            loadingUrl.value = true;
            try {
                const response = await axios.get(
                    `/resources/files/${props.resource.id}/url`,
                );
                fileUrl.value = response.data.url;
            } catch {
                fileUrl.value = '';
            } finally {
                loadingUrl.value = false;
            }
        } else {
            fileUrl.value = '';
        }
    },
);
</script>

<template>
    <Dialog
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ resource?.title }}</DialogTitle>
            </DialogHeader>

            <div v-if="resource" class="space-y-4">
                <!-- Loading URL -->
                <div v-if="loadingUrl" class="space-y-2">
                    <Skeleton class="h-48 w-full rounded-lg" />
                </div>

                <!-- Video Link Embed (YouTube/Vimeo) -->
                <div
                    v-else-if="isVideoLink && embedUrl"
                    class="overflow-hidden rounded-lg"
                >
                    <div class="relative w-full" style="padding-top: 56.25%;">
                        <iframe
                            :src="embedUrl"
                            class="absolute inset-0 h-full w-full rounded-lg"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                </div>

                <!-- Video Link fallback (couldn't parse embed URL) -->
                <div
                    v-else-if="isVideoLink"
                    class="flex flex-col items-center gap-4 rounded-lg border p-8"
                >
                    <p class="text-muted-foreground text-sm">
                        Unable to embed this video.
                    </p>
                    <Button
                        as="a"
                        :href="resource.video_url ?? ''"
                        target="_blank"
                    >
                        <ExternalLink class="mr-2 h-4 w-4" />
                        Open Video Link
                    </Button>
                </div>

                <!-- Video Player (uploaded file) -->
                <div
                    v-else-if="isVideo && fileUrl"
                    class="overflow-hidden rounded-lg bg-black"
                >
                    <video
                        controls
                        class="w-full"
                        :src="fileUrl"
                    >
                        Your browser does not support the video tag.
                    </video>
                </div>

                <!-- PDF Notice -->
                <div
                    v-else-if="isPdf"
                    class="flex flex-col items-center gap-4 rounded-lg border p-8"
                >
                    <FileText class="text-muted-foreground h-16 w-16" />
                    <p class="text-muted-foreground text-sm">
                        {{ resource.file_name }}
                    </p>
                    <Button
                        as="a"
                        :href="fileUrl"
                        target="_blank"
                        :disabled="!fileUrl"
                    >
                        <ExternalLink class="mr-2 h-4 w-4" />
                        Open PDF
                    </Button>
                </div>

                <!-- Description -->
                <div v-if="resource.description">
                    <h4 class="mb-1 text-sm font-medium">Description</h4>
                    <p class="text-muted-foreground text-sm">
                        {{ resource.description }}
                    </p>
                </div>

                <!-- Tags -->
                <div
                    v-if="resource.tags && resource.tags.length > 0"
                >
                    <h4 class="mb-1 text-sm font-medium">Tags</h4>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="tag in resource.tags"
                            :key="tag"
                            variant="secondary"
                        >
                            {{ tag }}
                        </Badge>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
