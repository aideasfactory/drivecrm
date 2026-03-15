<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { toast } from '@/components/ui/sonner';
import {
    FolderPlus,
    Upload,
    Trash2,
    Home,
    FolderOpen,
    FileVideo,
    Loader2,
    Download,
    FileUp,
} from 'lucide-vue-next';

import FolderCard from '@/components/Resources/FolderCard.vue';
import ResourceCard from '@/components/Resources/ResourceCard.vue';
import CreateFolderSheet from '@/components/Resources/CreateFolderSheet.vue';
import EditFolderSheet from '@/components/Resources/EditFolderSheet.vue';
import UploadResourceSheet from '@/components/Resources/UploadResourceSheet.vue';
import EditResourceSheet from '@/components/Resources/EditResourceSheet.vue';
import ResourcePreview from '@/components/Resources/ResourcePreview.vue';
import CsvImportSheet from '@/components/CsvImportSheet.vue';

// Types
interface FolderItem {
    id: number;
    name: string;
    slug: string;
}

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

interface BreadcrumbEntry {
    id: number;
    name: string;
    slug: string;
}

// State
const loading = ref(true);
const folders = ref<FolderItem[]>([]);
const resources = ref<ResourceItem[]>([]);
const breadcrumbs = ref<BreadcrumbEntry[]>([]);
const currentFolder = ref<FolderItem | null>(null);

// Sheet states
const isCreateFolderOpen = ref(false);
const isEditFolderOpen = ref(false);
const editingFolder = ref<FolderItem | null>(null);
const isUploadOpen = ref(false);
const isEditResourceOpen = ref(false);
const editingResource = ref<ResourceItem | null>(null);
const isPreviewOpen = ref(false);
const previewResource = ref<ResourceItem | null>(null);

// Delete dialog
const isDeleteFolderDialogOpen = ref(false);
const deletingFolder = ref<FolderItem | null>(null);
const isDeletingFolder = ref(false);
const isDeleteResourceDialogOpen = ref(false);
const deletingResource = ref<ResourceItem | null>(null);
const isDeletingResource = ref(false);

// CSV import
const isCsvImportOpen = ref(false);

const csvExtraFormData = computed(() => {
    return currentFolderId.value ? { resource_folder_id: currentFolderId.value } : {};
});

// Computed
const currentFolderId = computed(() => currentFolder.value?.id ?? null);
const hasContent = computed(
    () => folders.value.length > 0 || resources.value.length > 0,
);

// Data loading
const loadContents = async (folderId: number | null = null) => {
    loading.value = true;
    try {
        const url = folderId
            ? `/resources/folders/${folderId}/contents`
            : '/resources/folders/root/contents';
        const response = await axios.get(url);
        folders.value = response.data.folders || [];
        resources.value = response.data.resources || [];
        breadcrumbs.value = response.data.breadcrumbs || [];
        currentFolder.value = response.data.current_folder || null;
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Failed to load folder contents',
        );
    } finally {
        loading.value = false;
    }
};

// Navigation
const navigateToFolder = (folder: FolderItem) => {
    loadContents(folder.id);
};

const navigateToRoot = () => {
    loadContents(null);
};

const navigateToBreadcrumb = (crumb: BreadcrumbEntry) => {
    loadContents(crumb.id);
};

// Folder actions
const openEditFolder = (folder: FolderItem) => {
    editingFolder.value = folder;
    isEditFolderOpen.value = true;
};

const openDeleteFolder = (folder: FolderItem) => {
    deletingFolder.value = folder;
    isDeleteFolderDialogOpen.value = true;
};

const handleDeleteFolder = async () => {
    if (!deletingFolder.value) return;
    isDeletingFolder.value = true;

    try {
        await axios.delete(
            `/resources/folders/${deletingFolder.value.id}`,
        );
        toast.success('Folder deleted successfully');
        isDeleteFolderDialogOpen.value = false;
        loadContents(currentFolderId.value);
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Failed to delete folder',
        );
    } finally {
        isDeletingFolder.value = false;
    }
};

// Resource actions
const openEditResource = (resource: ResourceItem) => {
    editingResource.value = resource;
    isEditResourceOpen.value = true;
};

const openPreview = (resource: ResourceItem) => {
    previewResource.value = resource;
    isPreviewOpen.value = true;
};

const openDeleteResource = (resource: ResourceItem) => {
    deletingResource.value = resource;
    isDeleteResourceDialogOpen.value = true;
};

const handleDeleteResource = async () => {
    if (!deletingResource.value) return;
    isDeletingResource.value = true;

    try {
        await axios.delete(
            `/resources/files/${deletingResource.value.id}`,
        );
        toast.success('Resource deleted successfully');
        isDeleteResourceDialogOpen.value = false;
        loadContents(currentFolderId.value);
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Failed to delete resource',
        );
    } finally {
        isDeletingResource.value = false;
    }
};

onMounted(() => {
    loadContents(null);
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Resources' }]">
        <div class="flex flex-col gap-4 p-6">
            <!-- Header: Breadcrumbs + Actions -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <!-- Folder Breadcrumbs -->
                <Breadcrumb>
                    <BreadcrumbList>
                        <BreadcrumbItem>
                            <template
                                v-if="breadcrumbs.length > 0"
                            >
                                <BreadcrumbLink
                                    as="button"
                                    class="flex items-center gap-1"
                                    @click="navigateToRoot"
                                >
                                    <Home class="h-4 w-4" />
                                    Resources
                                </BreadcrumbLink>
                            </template>
                            <template v-else>
                                <BreadcrumbPage class="flex items-center gap-1">
                                    <Home class="h-4 w-4" />
                                    Resources
                                </BreadcrumbPage>
                            </template>
                        </BreadcrumbItem>

                        <template
                            v-for="(crumb, index) in breadcrumbs"
                            :key="crumb.id"
                        >
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <template
                                    v-if="
                                        index ===
                                        breadcrumbs.length - 1
                                    "
                                >
                                    <BreadcrumbPage>{{
                                        crumb.name
                                    }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink
                                        as="button"
                                        @click="
                                            navigateToBreadcrumb(
                                                crumb,
                                            )
                                        "
                                    >
                                        {{ crumb.name }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <Button variant="outline" as="a" href="/resources/csv-template">
                        <Download class="mr-2 h-4 w-4" />
                        CSV Template
                    </Button>
                    <Button
                        v-if="currentFolder"
                        variant="outline"
                        @click="isCsvImportOpen = true"
                    >
                        <FileUp class="mr-2 h-4 w-4" />
                        Upload CSV
                    </Button>
                    <Button
                        variant="outline"
                        @click="isCreateFolderOpen = true"
                    >
                        <FolderPlus class="mr-2 h-4 w-4" />
                        New Folder
                    </Button>
                    <Button
                        v-if="currentFolder"
                        @click="isUploadOpen = true"
                    >
                        <Upload class="mr-2 h-4 w-4" />
                        Upload Resource
                    </Button>
                </div>
            </div>

            <!-- Loading Skeletons -->
            <div v-if="loading" class="space-y-3">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <Skeleton
                        v-for="n in 6"
                        :key="n"
                        class="h-16 w-full"
                    />
                </div>
            </div>

            <!-- Empty State -->
            <Card v-else-if="!hasContent">
                <CardContent class="p-6">
                    <div
                        class="text-muted-foreground flex min-h-[300px] flex-col items-center justify-center gap-4"
                    >
                        <FolderOpen class="h-12 w-12" />
                        <div class="text-center">
                            <p class="text-lg font-medium">
                                {{
                                    currentFolder
                                        ? 'This folder is empty'
                                        : 'No folders yet'
                                }}
                            </p>
                            <p class="mt-2 text-sm">
                                {{
                                    currentFolder
                                        ? 'Add folders or upload files to get started.'
                                        : 'Create your first folder to start organising resources.'
                                }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                @click="isCreateFolderOpen = true"
                            >
                                <FolderPlus class="mr-2 h-4 w-4" />
                                New Folder
                            </Button>
                            <Button
                                v-if="currentFolder"
                                @click="isUploadOpen = true"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Upload Resource
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Content -->
            <template v-else>
                <!-- Folders Section -->
                <div v-if="folders.length > 0">
                    <h3
                        class="text-muted-foreground mb-2 text-sm font-medium"
                    >
                        Folders
                    </h3>
                    <div
                        class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <FolderCard
                            v-for="folder in folders"
                            :key="folder.id"
                            :folder="folder"
                            @open="navigateToFolder"
                            @edit="openEditFolder"
                            @delete="openDeleteFolder"
                        />
                    </div>
                </div>

                <!-- Resources Section -->
                <div v-if="resources.length > 0">
                    <h3
                        class="text-muted-foreground mb-2 text-sm font-medium"
                    >
                        Files
                    </h3>
                    <div class="space-y-3">
                        <ResourceCard
                            v-for="resource in resources"
                            :key="resource.id"
                            :resource="resource"
                            @preview="openPreview"
                            @edit="openEditResource"
                            @delete="openDeleteResource"
                        />
                    </div>
                </div>
            </template>
        </div>

        <!-- Create Folder Sheet -->
        <CreateFolderSheet
            v-model:open="isCreateFolderOpen"
            :parent-id="currentFolderId"
            @created="loadContents(currentFolderId)"
        />

        <!-- Edit Folder Sheet -->
        <EditFolderSheet
            v-model:open="isEditFolderOpen"
            :folder="editingFolder"
            @updated="loadContents(currentFolderId)"
        />

        <!-- Upload Resource Sheet -->
        <UploadResourceSheet
            v-model:open="isUploadOpen"
            :folder-id="currentFolderId!"
            @uploaded="loadContents(currentFolderId)"
        />

        <!-- Edit Resource Sheet -->
        <EditResourceSheet
            v-model:open="isEditResourceOpen"
            :resource="editingResource"
            @updated="loadContents(currentFolderId)"
        />

        <!-- Resource Preview -->
        <ResourcePreview
            v-model:open="isPreviewOpen"
            :resource="previewResource"
        />

        <!-- Delete Folder Confirmation -->
        <Dialog v-model:open="isDeleteFolderDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Folder?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-muted-foreground text-sm">
                        Are you sure you want to delete
                        <strong class="text-foreground font-semibold">{{
                            deletingFolder?.name
                        }}</strong>? This will permanently delete all sub-folders and files
                        within it. This action cannot be undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isDeletingFolder"
                        @click="isDeleteFolderDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="isDeletingFolder"
                        class="min-w-[100px]"
                        @click="handleDeleteFolder"
                    >
                        <Loader2
                            v-if="isDeletingFolder"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Resource Confirmation -->
        <Dialog v-model:open="isDeleteResourceDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Resource?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-muted-foreground text-sm">
                        Are you sure you want to delete
                        <strong class="text-foreground font-semibold">{{
                            deletingResource?.title
                        }}</strong>? The file will be permanently removed. This action
                        cannot be undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="isDeletingResource"
                        @click="isDeleteResourceDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="isDeletingResource"
                        class="min-w-[100px]"
                        @click="handleDeleteResource"
                    >
                        <Loader2
                            v-if="isDeletingResource"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- CSV Import Sheet -->
        <CsvImportSheet
            v-model:open="isCsvImportOpen"
            title="Import Video Resources from CSV"
            description="Upload a CSV file to bulk-create video link resources in the current folder. Download the template first to see the required format."
            import-url="/resources/import-csv"
            :extra-form-data="csvExtraFormData"
            @imported="loadContents(currentFolderId)"
        />
    </AppLayout>
</template>
