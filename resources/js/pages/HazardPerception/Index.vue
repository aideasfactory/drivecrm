<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from '@/components/ui/sonner';
import {
    Loader2,
    Pencil,
    Trash2,
    TrafficCone,
    Upload,
} from 'lucide-vue-next';
import VideoFormSheet, {
    type HazardPerceptionVideoItem,
} from '@/components/HazardPerception/VideoFormSheet.vue';

const loading = ref(true);
const videos = ref<HazardPerceptionVideoItem[]>([]);

const isFormOpen = ref(false);
const editingVideo = ref<HazardPerceptionVideoItem | null>(null);

const isDeleteDialogOpen = ref(false);
const deletingVideo = ref<HazardPerceptionVideoItem | null>(null);
const isDeleting = ref(false);

const hasVideos = computed(() => videos.value.length > 0);

const loadVideos = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/hazard-perception/videos');
        videos.value = response.data.videos || [];
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to load videos');
    } finally {
        loading.value = false;
    }
};

const openUpload = () => {
    editingVideo.value = null;
    isFormOpen.value = true;
};

const openEdit = (video: HazardPerceptionVideoItem) => {
    editingVideo.value = video;
    isFormOpen.value = true;
};

const openDelete = (video: HazardPerceptionVideoItem) => {
    deletingVideo.value = video;
    isDeleteDialogOpen.value = true;
};

const handleDelete = async () => {
    if (!deletingVideo.value) return;
    isDeleting.value = true;

    try {
        await axios.delete(`/hazard-perception/videos/${deletingVideo.value.id}`);
        toast.success('Video deleted successfully');
        isDeleteDialogOpen.value = false;
        loadVideos();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to delete video');
    } finally {
        isDeleting.value = false;
    }
};

const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
};

onMounted(() => {
    loadVideos();
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Hazard Perception' }]">
        <div class="flex flex-col gap-4 p-6">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-semibold">
                        <TrafficCone class="h-5 w-5" />
                        Hazard Perception Clips
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        Manage the clips, recap videos, and scoring zones used by the
                        student app.
                    </p>
                </div>
                <Button @click="openUpload">
                    <Upload class="mr-2 h-4 w-4" />
                    Upload Clip
                </Button>
            </div>

            <!-- Loading skeletons -->
            <div v-if="loading" class="space-y-3">
                <Skeleton v-for="n in 6" :key="n" class="h-14 w-full" />
            </div>

            <!-- Empty state -->
            <Card v-else-if="!hasVideos">
                <CardContent class="p-6">
                    <div
                        class="text-muted-foreground flex min-h-[300px] flex-col items-center justify-center gap-4"
                    >
                        <TrafficCone class="h-12 w-12" />
                        <div class="text-center">
                            <p class="text-lg font-medium">No hazard clips yet</p>
                            <p class="mt-2 text-sm">
                                Upload your first clip to get started.
                            </p>
                        </div>
                        <Button @click="openUpload">
                            <Upload class="mr-2 h-4 w-4" />
                            Upload Clip
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Videos table -->
            <Card v-else>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Topic</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>Hazards</TableHead>
                                <TableHead>Recap</TableHead>
                                <TableHead class="text-right">Attempts</TableHead>
                                <TableHead class="w-[100px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="video in videos" :key="video.id">
                                <TableCell class="max-w-[280px]">
                                    <p class="truncate font-medium">
                                        {{ video.title }}
                                    </p>
                                    <p
                                        v-if="video.description"
                                        class="text-muted-foreground truncate text-xs"
                                    >
                                        {{ video.description }}
                                    </p>
                                </TableCell>
                                <TableCell>{{ video.category }}</TableCell>
                                <TableCell>{{ video.topic }}</TableCell>
                                <TableCell>
                                    {{ formatDuration(video.duration_seconds) }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            video.is_double_hazard
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ video.is_double_hazard ? 'Double' : 'Single' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            video.has_recap ? 'default' : 'outline'
                                        "
                                    >
                                        {{ video.has_recap ? 'Yes' : 'No' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    {{ video.attempts_count ?? 0 }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openEdit(video)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="openDelete(video)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Upload / Edit Sheet -->
        <VideoFormSheet
            v-model:open="isFormOpen"
            :video="editingVideo"
            @saved="loadVideos"
        />

        <!-- Delete Confirmation -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Hazard Clip?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-muted-foreground text-sm">
                        Are you sure you want to delete
                        <strong class="text-foreground font-semibold">{{
                            deletingVideo?.title
                        }}</strong>? The video files, scoring zones, and all student
                        attempts for this clip will be permanently removed. This action
                        cannot be undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isDeleting"
                        @click="isDeleteDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="isDeleting"
                        class="min-w-[100px]"
                        @click="handleDelete"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
