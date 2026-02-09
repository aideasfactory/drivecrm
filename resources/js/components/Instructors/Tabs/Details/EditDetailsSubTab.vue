<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { Edit, Package as PackageIcon, Plus } from 'lucide-vue-next'
import PackageForm, { type PackageFormData } from '@/components/Instructors/PackageForm.vue'
import type { Package } from '@/types/instructor'
import type { InstructorDetail } from '@/types/instructor'
import { toast } from '@/components/ui/sonner'

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

// Component state
const packages = ref<Package[]>([])
const loading = ref(true)
const selectedPackage = ref<Package | null>(null)
const isSheetOpen = ref(false)
const saving = ref(false)
const isCreating = ref(false)

// Load packages on mount
const loadPackages = async () => {
    loading.value = true
    try {
        const response = await axios.get(
            `/instructors/${props.instructor.id}/packages`
        )
        packages.value = response.data.packages
    } catch (error) {
        console.error('Failed to load packages:', error)
        toast.error('Failed to load packages')
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadPackages()
})

// Separate packages into platform and bespoke
const bespokePackages = computed(() =>
    packages.value.filter((pkg) => pkg.is_bespoke_package)
)

// Open sheet to create new package
const createPackage = () => {
    selectedPackage.value = null
    isCreating.value = true
    isSheetOpen.value = true
}

// Open sheet to edit package
const editPackage = (pkg: Package) => {
    selectedPackage.value = pkg
    isCreating.value = false
    isSheetOpen.value = true
}

// Close sheet
const closeSheet = () => {
    isSheetOpen.value = false
    selectedPackage.value = null
    isCreating.value = false
}

// Save package (create or update)
const savePackage = async (formData: PackageFormData) => {
    saving.value = true
    try {
        if (isCreating.value) {
            // Create new package
            await axios.post(
                `/instructors/${props.instructor.id}/packages`,
                formData
            )
            toast.success('Package created successfully!')
        } else if (selectedPackage.value) {
            // Update existing package
            await axios.put(
                `/packages/${selectedPackage.value.id}`,
                formData
            )
            toast.success('Package updated successfully!')
        }

        // Reload packages after save
        await loadPackages()
        closeSheet()
    } catch (error: any) {
        console.error('Failed to save package:', error)
        const message = error.response?.data?.message || 'Failed to save package'
        toast.error(message)
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Bespoke Packages -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <Edit class="h-5 w-5" />
                    Bespoke Packages
                </CardTitle>
                <Button @click="createPackage" size="sm">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Package
                </Button>
            </CardHeader>
            <CardContent>
                <!-- Loading Skeleton -->
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>

                <!-- Bespoke Packages Table -->
                <div v-else-if="bespokePackages.length > 0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Lessons</TableHead>
                                <TableHead>Total Price</TableHead>
                                <TableHead>Price per Lesson</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="pkg in bespokePackages"
                                :key="pkg.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="editPackage(pkg)"
                            >
                                <TableCell class="font-medium">
                                    {{ pkg.name }}
                                </TableCell>
                                <TableCell>{{ pkg.lessons_count }}</TableCell>
                                <TableCell>{{
                                    pkg.formatted_total_price
                                }}</TableCell>
                                <TableCell>{{
                                    pkg.formatted_lesson_price
                                }}</TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                        :class="
                                            pkg.active
                                                ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                                : 'bg-gray-50 text-gray-700 dark:bg-gray-900/20 dark:text-gray-400'
                                        "
                                    >
                                        {{ pkg.active ? 'Active' : 'Inactive' }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click.stop="editPackage(pkg)"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex min-h-[200px] flex-col items-center justify-center gap-4 text-muted-foreground"
                >
                    <PackageIcon class="h-12 w-12" />
                    <div class="text-center">
                        <p class="text-lg font-medium">No bespoke packages yet</p>
                        <p class="mt-2 text-sm">
                            Create a custom package for this instructor
                        </p>
                        <Button @click="createPackage" class="mt-4" size="sm">
                            <Plus class="mr-2 h-4 w-4" />
                            Create First Package
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Package Form Sheet -->
        <Sheet :open="isSheetOpen" @update:open="closeSheet">
            <SheetContent class="w-full sm:max-w-xl overflow-y-auto">
                <SheetHeader>
                    <SheetTitle>
                        {{ isCreating ? 'Create Package' : 'Edit Package' }}
                    </SheetTitle>
                </SheetHeader>

                <div class="mt-6 px-6 py-4">
                    <!-- Package Form -->
                    <PackageForm
                        :package="selectedPackage"
                        :saving="saving"
                        @save="savePackage"
                        @cancel="closeSheet"
                    />
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
