<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Sheet,
    SheetContent,
    SheetDescription,
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
    MapPin,
    Plus,
    Pencil,
    Trash2,
    Loader2,
    Save,
    Star,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'

interface PickupPoint {
    id: number
    student_id: number
    label: string
    address: string
    postcode: string
    latitude: number | null
    longitude: number | null
    is_default: boolean
    created_at: string
    updated_at: string
}

interface PickupPointFormData {
    label: string
    address: string
    postcode: string
    is_default: boolean
}

const props = defineProps<{
    studentId: number
}>()

const pickupPoints = ref<PickupPoint[]>([])
const isLoading = ref(true)
const isSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const isSubmitting = ref(false)
const isDeleting = ref(false)
const isSettingDefault = ref<number | null>(null)
const editingPoint = ref<PickupPoint | null>(null)
const deletePointId = ref<number | null>(null)
const errors = ref<Record<string, string>>({})

const form = ref<PickupPointFormData>({
    label: '',
    address: '',
    postcode: '',
    is_default: false,
})

const basePath = `/students/${props.studentId}/pickup-points`

const deletePoint = computed(() =>
    pickupPoints.value.find((p) => p.id === deletePointId.value),
)
const isEditMode = computed(() => editingPoint.value !== null)
const hasPickupPoints = computed(() => pickupPoints.value.length > 0)

const loadPickupPoints = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(basePath)
        pickupPoints.value = response.data.pickup_points || []
    } catch {
        toast({ title: 'Failed to load pickup points', variant: 'destructive' })
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadPickupPoints()
})

const openAddSheet = () => {
    editingPoint.value = null
    form.value = { label: '', address: '', postcode: '', is_default: false }
    errors.value = {}
    isSheetOpen.value = true
}

const openEditSheet = (point: PickupPoint) => {
    editingPoint.value = point
    form.value = {
        label: point.label,
        address: point.address,
        postcode: point.postcode,
        is_default: point.is_default,
    }
    errors.value = {}
    isSheetOpen.value = true
}

const handleSubmit = async () => {
    errors.value = {}
    isSubmitting.value = true

    try {
        if (isEditMode.value && editingPoint.value) {
            const response = await axios.put(
                `${basePath}/${editingPoint.value.id}`,
                form.value,
            )
            const index = pickupPoints.value.findIndex(
                (p) => p.id === editingPoint.value!.id,
            )
            if (index !== -1) {
                pickupPoints.value[index] = response.data.pickup_point
            }
            if (response.data.pickup_point.is_default) {
                pickupPoints.value.forEach((p) => {
                    if (p.id !== response.data.pickup_point.id) {
                        p.is_default = false
                    }
                })
            }
            toast({ title: 'Pickup point updated successfully' })
        } else {
            const response = await axios.post(basePath, form.value)
            if (response.data.pickup_point.is_default) {
                pickupPoints.value.forEach((p) => {
                    p.is_default = false
                })
            }
            pickupPoints.value.push(response.data.pickup_point)
            toast({ title: 'Pickup point added successfully' })
        }

        pickupPoints.value.sort((a, b) => {
            if (a.is_default !== b.is_default) return a.is_default ? -1 : 1
            return a.label.localeCompare(b.label)
        })

        isSheetOpen.value = false
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            )
        } else {
            const message =
                error.response?.data?.message || 'Failed to save pickup point'
            toast({ title: message, variant: 'destructive' })
        }
    } finally {
        isSubmitting.value = false
    }
}

const openDeleteDialog = (pointId: number) => {
    deletePointId.value = pointId
    isDeleteDialogOpen.value = true
}

const handleDelete = async () => {
    if (!deletePointId.value) return
    isDeleting.value = true

    try {
        await axios.delete(`${basePath}/${deletePointId.value}`)
        pickupPoints.value = pickupPoints.value.filter(
            (p) => p.id !== deletePointId.value,
        )
        toast({ title: 'Pickup point deleted successfully' })
        isDeleteDialogOpen.value = false
        deletePointId.value = null
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to delete pickup point'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isDeleting.value = false
    }
}

const handleSetDefault = async (point: PickupPoint) => {
    isSettingDefault.value = point.id
    try {
        await axios.patch(`${basePath}/${point.id}/default`)
        pickupPoints.value.forEach((p) => {
            p.is_default = p.id === point.id
        })
        pickupPoints.value.sort((a, b) => {
            if (a.is_default !== b.is_default) return a.is_default ? -1 : 1
            return a.label.localeCompare(b.label)
        })
        toast({ title: `${point.label} set as default pickup point` })
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to set default'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isSettingDefault.value = null
    }
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <MapPin class="h-5 w-5" />
                Pickup Points
            </h3>
            <Button v-if="!isLoading" @click="openAddSheet">
                <Plus class="mr-2 h-4 w-4" />
                Add Point
            </Button>
        </div>

        <!-- Loading Skeletons -->
        <div v-if="isLoading" class="space-y-4">
            <Skeleton v-for="n in 2" :key="n" class="h-24 w-full" />
        </div>

        <!-- Loaded Content -->
        <div v-else>
            <!-- Empty State -->
            <Card v-if="!hasPickupPoints">
                <CardContent class="p-6">
                    <div
                        class="flex min-h-[200px] flex-col items-center justify-center gap-4 text-muted-foreground"
                    >
                        <MapPin class="h-12 w-12" />
                        <div class="text-center">
                            <p class="text-lg font-medium">
                                No pickup points
                            </p>
                            <p class="mt-2 text-sm">
                                Add pickup/drop-off locations using the button
                                above
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Pickup Point Cards -->
            <div v-else class="max-h-[600px] space-y-4 overflow-y-auto">
                <Card
                    v-for="point in pickupPoints"
                    :key="point.id"
                >
                    <CardContent class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <!-- Label + Default Badge -->
                                <div
                                    class="mb-2 flex items-center gap-3"
                                >
                                    <h4 class="text-lg font-semibold">
                                        {{ point.label }}
                                    </h4>
                                    <Badge
                                        v-if="point.is_default"
                                        variant="default"
                                    >
                                        Default
                                    </Badge>
                                    <Button
                                        v-else
                                        variant="secondary"
                                        size="sm"
                                        :disabled="
                                            isSettingDefault === point.id
                                        "
                                        @click="handleSetDefault(point)"
                                        class="h-7 text-xs"
                                    >
                                        <Loader2
                                            v-if="
                                                isSettingDefault === point.id
                                            "
                                            class="mr-1 h-3 w-3 animate-spin"
                                        />
                                        <Star
                                            v-else
                                            class="mr-1 h-3 w-3"
                                        />
                                        Set Default
                                    </Button>
                                </div>

                                <!-- Address Details -->
                                <div class="space-y-1">
                                    <p
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ point.address }}
                                    </p>
                                    <p
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        {{ point.postcode }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditSheet(point)"
                                    class="h-8 w-8 p-0"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openDeleteDialog(point.id)"
                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Add/Edit Sheet -->
        <Sheet v-model:open="isSheetOpen">
            <SheetContent side="right" class="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <MapPin
                            v-if="!isEditMode"
                            class="h-5 w-5"
                        />
                        <Pencil v-else class="h-5 w-5" />
                        {{
                            isEditMode
                                ? 'Edit Pickup Point'
                                : 'Add Pickup Point'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            isEditMode
                                ? 'Update the pickup point details.'
                                : 'Add a new pickup/drop-off location.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <form
                    @submit.prevent="handleSubmit"
                    class="mt-6 space-y-6 px-6 py-4"
                >
                    <div class="space-y-2">
                        <Label for="pp_label">Label *</Label>
                        <Input
                            id="pp_label"
                            v-model="form.label"
                            placeholder="e.g., Home, School, Work"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.label"
                            class="text-sm text-destructive"
                        >
                            {{ errors.label }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pp_address">Address *</Label>
                        <Input
                            id="pp_address"
                            v-model="form.address"
                            placeholder="e.g., 123 Main Street, Manchester"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.address"
                            class="text-sm text-destructive"
                        >
                            {{ errors.address }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pp_postcode">Postcode *</Label>
                        <Input
                            id="pp_postcode"
                            v-model="form.postcode"
                            placeholder="e.g., M1 4BT"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.postcode"
                            class="text-sm text-destructive"
                        >
                            {{ errors.postcode }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="pp_default"
                            type="checkbox"
                            v-model="form.is_default"
                            :disabled="isSubmitting"
                            class="h-4 w-4 rounded border-input accent-primary"
                        />
                        <Label
                            for="pp_default"
                            class="cursor-pointer text-sm"
                        >
                            Set as default pickup point
                        </Label>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSheetOpen = false"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="isSubmitting"
                            class="min-w-[120px]"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Save
                                v-else
                                class="mr-2 h-4 w-4"
                            />
                            {{
                                isEditMode
                                    ? 'Save Changes'
                                    : 'Add Point'
                            }}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Pickup Point?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to delete
                        <strong class="font-semibold text-foreground">{{
                            deletePoint?.label
                        }}</strong
                        >? This action cannot be undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isDeleteDialogOpen = false"
                        :disabled="isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleDelete"
                        :disabled="isDeleting"
                        class="min-w-[100px]"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2
                            v-else
                            class="mr-2 h-4 w-4"
                        />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
