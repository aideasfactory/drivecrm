<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
// @ts-expect-error vuedraggable has no bundled types
import draggable from 'vuedraggable'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import {
    ListChecks,
    Plus,
    Pencil,
    Trash2,
    GripVertical,
    Loader2,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import type { InstructorDetail } from '@/types/instructor'

interface Subcategory {
    id: number
    name: string
    sort_order: number
}

interface Category {
    id: number
    name: string
    sort_order: number
    subcategories: Subcategory[]
}

const props = defineProps<{
    instructor: InstructorDetail
}>()

const categories = ref<Category[]>([])
const isLoading = ref(true)

type SheetMode =
    | { kind: 'create-category' }
    | { kind: 'edit-category'; category: Category }
    | { kind: 'create-subcategory'; category: Category }
    | { kind: 'edit-subcategory'; category: Category; subcategory: Subcategory }
    | null

const sheetMode = ref<SheetMode>(null)
const sheetName = ref('')
const sheetError = ref('')
const isSubmitting = ref(false)

type DeleteTarget =
    | { kind: 'category'; category: Category }
    | { kind: 'subcategory'; category: Category; subcategory: Subcategory }
    | null

const deleteTarget = ref<DeleteTarget>(null)
const isDeleting = ref(false)

const sheetTitle = computed(() => {
    switch (sheetMode.value?.kind) {
        case 'create-category':
            return 'Add Category'
        case 'edit-category':
            return 'Rename Category'
        case 'create-subcategory':
            return `Add Subcategory to "${sheetMode.value.category.name}"`
        case 'edit-subcategory':
            return 'Rename Subcategory'
        default:
            return ''
    }
})

const deleteMessage = computed(() => {
    if (!deleteTarget.value) return ''
    if (deleteTarget.value.kind === 'category') {
        return `Delete the "${deleteTarget.value.category.name}" category and all of its subcategories? Historical student scores under these items will be preserved but hidden from new scoring.`
    }
    return `Delete the "${deleteTarget.value.subcategory.name}" subcategory? Historical student scores for this item will be preserved but hidden from new scoring.`
})

const loadFramework = async () => {
    isLoading.value = true
    try {
        const { data } = await axios.get(
            `/instructors/${props.instructor.id}/progress-tracker/framework`,
        )
        categories.value = data.categories ?? []
    } catch (error) {
        console.error('Error loading progress tracker framework:', error)
        toast({
            title: 'Failed to load progress tracker',
            variant: 'destructive',
        })
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadFramework()
})

const openCreateCategory = () => {
    sheetMode.value = { kind: 'create-category' }
    sheetName.value = ''
    sheetError.value = ''
}

const openEditCategory = (category: Category) => {
    sheetMode.value = { kind: 'edit-category', category }
    sheetName.value = category.name
    sheetError.value = ''
}

const openCreateSubcategory = (category: Category) => {
    sheetMode.value = { kind: 'create-subcategory', category }
    sheetName.value = ''
    sheetError.value = ''
}

const openEditSubcategory = (category: Category, subcategory: Subcategory) => {
    sheetMode.value = { kind: 'edit-subcategory', category, subcategory }
    sheetName.value = subcategory.name
    sheetError.value = ''
}

const closeSheet = () => {
    sheetMode.value = null
    sheetName.value = ''
    sheetError.value = ''
}

const handleSubmitSheet = async () => {
    const name = sheetName.value.trim()
    if (!name) {
        sheetError.value = 'Name is required'
        return
    }
    if (name.length > 100) {
        sheetError.value = 'Name must be 100 characters or fewer'
        return
    }

    isSubmitting.value = true
    sheetError.value = ''
    const base = `/instructors/${props.instructor.id}/progress-tracker`

    try {
        const mode = sheetMode.value
        if (!mode) return

        if (mode.kind === 'create-category') {
            const { data } = await axios.post(`${base}/categories`, { name })
            categories.value.push({ ...data.category, subcategories: [] })
            toast({ title: 'Category added' })
        } else if (mode.kind === 'edit-category') {
            const { data } = await axios.put(`${base}/categories/${mode.category.id}`, { name })
            const target = categories.value.find(c => c.id === mode.category.id)
            if (target) target.name = data.category.name
            toast({ title: 'Category renamed' })
        } else if (mode.kind === 'create-subcategory') {
            const { data } = await axios.post(
                `${base}/categories/${mode.category.id}/subcategories`,
                { name },
            )
            const target = categories.value.find(c => c.id === mode.category.id)
            if (target) target.subcategories.push(data.subcategory)
            toast({ title: 'Subcategory added' })
        } else if (mode.kind === 'edit-subcategory') {
            const { data } = await axios.put(
                `${base}/subcategories/${mode.subcategory.id}`,
                { name },
            )
            const cat = categories.value.find(c => c.id === mode.category.id)
            const sub = cat?.subcategories.find(s => s.id === mode.subcategory.id)
            if (sub) sub.name = data.subcategory.name
            toast({ title: 'Subcategory renamed' })
        }

        closeSheet()
    } catch (error: any) {
        const laravelErrors = error.response?.data?.errors?.name
        sheetError.value = laravelErrors?.[0] ?? error.response?.data?.message ?? 'Save failed'
    } finally {
        isSubmitting.value = false
    }
}

const openDelete = (target: Exclude<DeleteTarget, null>) => {
    deleteTarget.value = target
}

const closeDelete = () => {
    deleteTarget.value = null
}

const handleDelete = async () => {
    const target = deleteTarget.value
    if (!target) return

    isDeleting.value = true
    const base = `/instructors/${props.instructor.id}/progress-tracker`

    try {
        if (target.kind === 'category') {
            await axios.delete(`${base}/categories/${target.category.id}`)
            categories.value = categories.value.filter(c => c.id !== target.category.id)
            toast({ title: 'Category deleted' })
        } else {
            await axios.delete(`${base}/subcategories/${target.subcategory.id}`)
            const cat = categories.value.find(c => c.id === target.category.id)
            if (cat) {
                cat.subcategories = cat.subcategories.filter(s => s.id !== target.subcategory.id)
            }
            toast({ title: 'Subcategory deleted' })
        }
        closeDelete()
    } catch (error: any) {
        toast({
            title: error.response?.data?.message ?? 'Delete failed',
            variant: 'destructive',
        })
    } finally {
        isDeleting.value = false
    }
}

const persistCategoryOrder = async () => {
    try {
        await axios.post(
            `/instructors/${props.instructor.id}/progress-tracker/categories/reorder`,
            { category_ids: categories.value.map(c => c.id) },
        )
    } catch (error) {
        toast({ title: 'Reorder failed', variant: 'destructive' })
        loadFramework()
    }
}

const persistSubcategoryOrder = async (category: Category) => {
    try {
        await axios.post(
            `/instructors/${props.instructor.id}/progress-tracker/categories/${category.id}/subcategories/reorder`,
            { subcategory_ids: category.subcategories.map(s => s.id) },
        )
    } catch (error) {
        toast({ title: 'Reorder failed', variant: 'destructive' })
        loadFramework()
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="flex items-center gap-2 font-bold">
                <ListChecks class="h-5 w-5" />
                Progress Tracker Framework
            </h1>
            <Button v-if="!isLoading" size="sm" @click="openCreateCategory">
                <Plus class="mr-2 h-4 w-4" />
                Add Category
            </Button>
        </div>

        <p class="text-sm text-muted-foreground">
            This framework is used to score your students' progress (1–5) on each
            subcategory. Changes here apply to every student you teach.
        </p>

        <div v-if="isLoading" class="space-y-3">
            <Skeleton class="h-24 w-full" />
            <Skeleton class="h-24 w-full" />
            <Skeleton class="h-24 w-full" />
        </div>

        <div v-else-if="categories.length === 0" class="rounded-lg border-2 border-dashed p-10 text-center">
            <ListChecks class="mx-auto h-10 w-10 text-muted-foreground" />
            <p class="mt-3 text-sm text-muted-foreground">
                No categories yet. Add one to get started.
            </p>
        </div>

        <draggable
            v-else
            v-model="categories"
            item-key="id"
            handle=".category-drag-handle"
            animation="150"
            ghost-class="opacity-40"
            class="space-y-4"
            @end="persistCategoryOrder"
        >
            <template #item="{ element: category }">
                <div class="rounded-lg border bg-background">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <div class="flex items-center gap-2">
                            <GripVertical class="category-drag-handle h-4 w-4 cursor-grab text-muted-foreground active:cursor-grabbing" />
                            <span class="font-semibold">{{ category.name }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button variant="ghost" size="sm" @click="openCreateSubcategory(category)">
                                <Plus class="mr-1 h-4 w-4" />
                                Subcategory
                            </Button>
                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openEditCategory(category)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                @click="openDelete({ kind: 'category', category })"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <draggable
                        v-model="category.subcategories"
                        item-key="id"
                        :handle="`.sub-drag-handle-${category.id}`"
                        animation="150"
                        ghost-class="opacity-40"
                        class="divide-y"
                        @end="persistSubcategoryOrder(category)"
                    >
                        <template #item="{ element: subcategory }">
                            <div class="flex items-center justify-between px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <GripVertical
                                        :class="[
                                            `sub-drag-handle-${category.id}`,
                                            'h-4 w-4 cursor-grab text-muted-foreground active:cursor-grabbing',
                                        ]"
                                    />
                                    <span class="text-sm">{{ subcategory.name }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                        @click="openEditSubcategory(category, subcategory)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        @click="openDelete({ kind: 'subcategory', category, subcategory })"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <div
                        v-if="category.subcategories.length === 0"
                        class="px-4 py-3 text-center text-sm text-muted-foreground"
                    >
                        No subcategories yet.
                    </div>
                </div>
            </template>
        </draggable>
    </div>

    <Sheet :open="sheetMode !== null" @update:open="(o: boolean) => !o && closeSheet()">
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ sheetTitle }}</SheetTitle>
            </SheetHeader>

            <form @submit.prevent="handleSubmitSheet" class="mt-6 space-y-6 px-6 py-4">
                <div class="space-y-2">
                    <Label for="tracker_name">Name</Label>
                    <Input
                        id="tracker_name"
                        v-model="sheetName"
                        maxlength="100"
                        autofocus
                        :class="{ 'border-red-500': sheetError }"
                    />
                    <p v-if="sheetError" class="text-sm text-red-600">
                        {{ sheetError }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="closeSheet" :disabled="isSubmitting">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-[100px]">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Save
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <Dialog :open="deleteTarget !== null" @update:open="(o: boolean) => !o && closeDelete()">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Confirm Delete</DialogTitle>
            </DialogHeader>
            <div class="py-4">
                <p class="text-sm text-muted-foreground">{{ deleteMessage }}</p>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="closeDelete" :disabled="isDeleting">
                    Cancel
                </Button>
                <Button
                    @click="handleDelete"
                    :disabled="isDeleting"
                    class="min-w-[100px] bg-red-600 hover:bg-red-700"
                >
                    <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                    <Trash2 v-else class="mr-2 h-4 w-4" />
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
