<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent } from '@/components/ui/card'
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
    Item,
    ItemActions,
    ItemContent,
    ItemMedia,
    ItemTitle,
} from '@/components/ui/item'
import { MapPin, Plus, Trash2, Loader2 } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import type { InstructorDetail, Location } from '@/types/instructor'

const props = defineProps<{
    instructor: InstructorDetail
}>()

// State
const locations = ref<Location[]>([])
const isLoading = ref(true)
const isAddSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const deleteLocationId = ref<number | null>(null)
const newPostcodeSector = ref('')
const isSubmitting = ref(false)
const isDeleting = ref(false)
const formError = ref('')

// Computed
const hasLocations = computed(() => locations.value.length > 0)

// Load locations data on mount
const loadLocations = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructor.id}/locations`)
        locations.value = response.data.locations || []
    } catch (error) {
        console.error('Error loading locations:', error)
        toast({ title: 'Failed to load coverage areas', variant: 'destructive' })
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadLocations()
})

// Validate postcode sector format (2-4 chars: 1-2 letters + 1-2 digits)
const validatePostcode = (value: string): string | null => {
    const trimmed = value.trim().toUpperCase()

    if (!trimmed) {
        return 'Postcode sector is required'
    }

    if (!/^[A-Z]{1,2}[0-9]{1,2}$/.test(trimmed)) {
        return 'Invalid format. Use format like TS7, WR14, or M1'
    }

    if (trimmed.length > 4) {
        return 'Postcode sector must not exceed 4 characters'
    }

    // Check for duplicates
    if (locations.value.some(loc => loc.postcode_sector === trimmed)) {
        return 'This postcode sector is already added'
    }

    return null
}

// Add location
const handleAddLocation = async () => {
    formError.value = ''

    // Validate
    const error = validatePostcode(newPostcodeSector.value)
    if (error) {
        formError.value = error
        return
    }

    isSubmitting.value = true

    try {
        const response = await axios.post(
            `/instructors/${props.instructor.id}/locations`,
            { postcode_sector: newPostcodeSector.value.trim().toUpperCase() }
        )

        // Add to local state
        locations.value.push(response.data.location)
        locations.value.sort((a, b) => a.postcode_sector.localeCompare(b.postcode_sector))

        // Show success toast
        toast({ title: `Location ${response.data.location.postcode_sector} added successfully` })

        // Reset form
        newPostcodeSector.value = ''
        formError.value = ''
        isAddSheetOpen.value = false

    } catch (error: any) {
        console.error('Error adding location:', error)
        // Handle validation errors from Laravel
        if (error.response?.data?.errors?.postcode_sector) {
            formError.value = error.response.data.errors.postcode_sector[0]
        } else {
            const message = error.response?.data?.message || 'Failed to add location'
            formError.value = message
            toast({ title: message, variant: 'destructive' })
        }
    } finally {
        isSubmitting.value = false
    }
}

// Delete location
const handleDeleteLocation = async () => {
    if (!deleteLocationId.value) return

    const locationToDelete = locations.value.find(loc => loc.id === deleteLocationId.value)
    if (!locationToDelete) return

    isDeleting.value = true

    try {
        await axios.delete(
            `/instructors/${props.instructor.id}/locations/${deleteLocationId.value}`
        )

        // Remove from local state
        locations.value = locations.value.filter(loc => loc.id !== deleteLocationId.value)

        // Show success toast
        toast({ title: `Location ${locationToDelete.postcode_sector} removed` })

        // Close dialog
        closeDeleteDialog()

    } catch (error: any) {
        console.error('Error deleting location:', error)
        const message = error.response?.data?.message || 'Failed to remove location'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isDeleting.value = false
    }
}

// Open delete dialog
const openDeleteDialog = (locationId: number) => {
    deleteLocationId.value = locationId
    isDeleteDialogOpen.value = true
}

// Close delete dialog
const closeDeleteDialog = () => {
    deleteLocationId.value = null
    isDeleteDialogOpen.value = false
}

// Open add sheet
const openAddSheet = () => {
    newPostcodeSector.value = ''
    formError.value = ''
    isAddSheetOpen.value = true
}
</script>

<template>
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <!-- Column 1: Locations List -->
        <div class="lg:col-span-1">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="flex items-center gap-2 font-bold">
                    <MapPin class="h-5 w-5" />
                    Coverage Areas
                </h1>
                <Button
                    v-if="!isLoading"
                    size="sm"
                    @click="openAddSheet"
                    class="h-8"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Add Area
                </Button>
            </div>

            <!-- Loading Skeletons -->
            <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-14 w-full" />
                <Skeleton class="h-14 w-full" />
                <Skeleton class="h-14 w-full" />
                <Skeleton class="h-14 w-full" />
            </div>

            <!-- Loaded Content -->
            <div v-else class="max-h-[600px] space-y-3 overflow-y-auto pr-2">
                <!-- Empty State -->
                <div v-if="!hasLocations" class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center">
                    <MapPin class="mx-auto h-8 w-8 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500">No coverage areas yet</p>
                    <p class="text-xs text-gray-400">Click "Add Area" button above</p>
                </div>

                <!-- Location Items -->
                <Item
                    v-for="location in locations"
                    :key="location.id"
                    variant="outline"
                >
                    <ItemMedia>
                        <span class="h-4 w-4 rounded-full bg-primary"></span>
                    </ItemMedia>
                    <ItemContent>
                        <ItemTitle>{{ location.postcode_sector }}</ItemTitle>
                    </ItemContent>
                    <ItemActions>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="openDeleteDialog(location.id)"
                            class="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </ItemActions>
                </Item>
            </div>
        </div>

        <!-- Column 2: Google Map Placeholder -->
        <div class="lg:col-span-2">
            <!-- Loading Skeleton -->
            <Skeleton v-if="isLoading" class="h-[650px] w-full" />

            <!-- Loaded Content -->
            <Card v-else class="h-[650px] overflow-hidden">
                <CardContent class="relative h-full p-0">
                    <!-- Placeholder Map -->
                    <div class="flex h-full items-center justify-center bg-gray-100">
                        <div class="text-center">
                            <MapPin class="mx-auto h-16 w-16 text-gray-400" />
                            <p class="mt-4 text-lg font-medium text-gray-600">
                                Interactive Coverage Map
                            </p>
                            <p class="mt-2 text-sm text-gray-500">
                                Map with coverage boundaries coming soon
                            </p>
                            <div v-if="hasLocations" class="mt-4">
                                <p class="text-sm font-medium text-gray-700">Current Coverage Areas:</p>
                                <div class="mt-2 flex flex-wrap justify-center gap-2">
                                    <span
                                        v-for="location in locations"
                                        :key="location.id"
                                        class="rounded-full bg-primary px-3 py-1 text-xs font-medium text-white"
                                    >
                                        {{ location.postcode_sector }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map Controls Placeholder -->
                    <div class="absolute left-4 top-4 flex flex-col rounded-md border border-gray-200 bg-white shadow-lg">
                        <button class="flex h-10 w-10 items-center justify-center border-b border-gray-200 text-gray-700 hover:bg-gray-100">
                            <Plus class="h-4 w-4" />
                        </button>
                        <button class="flex h-10 w-10 items-center justify-center text-gray-700 hover:bg-gray-100">
                            <span class="text-lg font-bold">−</span>
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Add Location Sheet (Slideout from right) -->
    <Sheet v-model:open="isAddSheetOpen">
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <MapPin class="h-5 w-5" />
                    Add Coverage Area
                </SheetTitle>
            </SheetHeader>

            <form @submit.prevent="handleAddLocation" class="mt-6 space-y-6 px-6 py-4">
                <div class="space-y-2">
                    <Label for="postcode_sector">Postcode Sector</Label>
                    <Input
                        id="postcode_sector"
                        v-model="newPostcodeSector"
                        placeholder="e.g., TS7, WR14, M1"
                        maxlength="4"
                        class="uppercase"
                        :class="{ 'border-red-500': formError }"
                        @input="newPostcodeSector = newPostcodeSector.toUpperCase()"
                    />
                    <p v-if="formError" class="text-sm text-red-600">
                        {{ formError }}
                    </p>
                    <p class="text-xs text-gray-500">
                        Format: 1-2 letters + 1-2 digits (e.g., TS7, WR14)
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isAddSheetOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-[120px]">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        Add Coverage Area
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="isDeleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Remove Coverage Area?</DialogTitle>
            </DialogHeader>
            <div class="py-4">
                <p class="text-sm dark:text-white text-gray-600">
                    Are you sure you want to remove
                    <strong class="font-semibold dark:text-white text-gray-900">{{ locations.find(loc => loc.id === deleteLocationId)?.postcode_sector }}</strong>
                    from the coverage areas? This action cannot be undone.
                </p>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    @click="closeDeleteDialog"
                    :disabled="isDeleting"
                >
                    Cancel
                </Button>
                <Button
                    @click="handleDeleteLocation"
                    :disabled="isDeleting"
                    class="min-w-[100px] bg-red-600 hover:bg-red-700"
                >
                    <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                    <Trash2 v-else class="mr-2 h-4 w-4" />
                    Remove
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
