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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Edit, Package as PackageIcon, Plus, PackagePlus, PackageOpen, TrendingUp, Loader2 } from 'lucide-vue-next'
import { useRole } from '@/composables/useRole'
import PackageForm, { type PackageFormData } from '@/components/Instructors/PackageForm.vue'
import type { Package } from '@/types/instructor'
import type { InstructorDetail } from '@/types/instructor'
import { toast } from '@/components/ui/toast'

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

const { isOwner } = useRole()

// Component state
const packages = ref<Package[]>([])
const loading = ref(true)
const selectedPackage = ref<Package | null>(null)
const isSheetOpen = ref(false)
const saving = ref(false)
const isCreating = ref(false)

// Drive package uplift state (owner only)
const priceUpliftPence = ref(0)
const isUpliftSheetOpen = ref(false)
const upliftInput = ref('0')
const savingUplift = ref(false)

// Load packages on mount
const loadPackages = async () => {
    loading.value = true
    try {
        const response = await axios.get(
            `/instructors/${props.instructor.id}/packages`
        )
        packages.value = response.data.packages
        priceUpliftPence.value = response.data.price_uplift_pence ?? 0
    } catch (error) {
        console.error('Failed to load packages:', error)
        toast({ title: 'Failed to load packages', variant: 'destructive' })
    } finally {
        loading.value = false
    }
}

const formattedUplift = computed(() => {
    const pounds = priceUpliftPence.value / 100
    const formatted = `£${Math.abs(pounds).toFixed(2)}`
    return pounds < 0 ? `-${formatted}` : formatted
})

const openUpliftSheet = () => {
    upliftInput.value = (priceUpliftPence.value / 100).toFixed(2)
    isUpliftSheetOpen.value = true
}

const saveUplift = async () => {
    savingUplift.value = true
    try {
        const response = await axios.put(
            `/instructors/${props.instructor.id}/price-uplift`,
            { price_uplift: upliftInput.value }
        )
        priceUpliftPence.value = response.data.price_uplift_pence ?? 0
        toast({ title: 'Price uplift updated' })
        isUpliftSheetOpen.value = false
    } catch (error: any) {
        const message =
            error.response?.data?.errors?.price_uplift?.[0] ||
            error.response?.data?.message ||
            'Failed to update price uplift'
        toast({ title: message, variant: 'destructive' })
    } finally {
        savingUplift.value = false
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
            toast({ title: 'Package created successfully!' })
        } else if (selectedPackage.value) {
            // Update existing package
            await axios.put(
                `/packages/${selectedPackage.value.id}`,
                formData
            )
            toast({ title: 'Package updated successfully!' })
        }

        // Reload packages after save
        await loadPackages()
        closeSheet()
    } catch (error: any) {
        console.error('Failed to save package:', error)
        const message = error.response?.data?.message || 'Failed to save package'
        toast({ title: message, variant: 'destructive' })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Drive Package Uplift (owner only) -->
        <Card v-if="isOwner">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5" />
                    Drive Package Uplift
                </CardTitle>
                <Button @click="openUpliftSheet" size="sm" variant="outline" :disabled="loading">
                    <Edit class="mr-2 h-4 w-4" />
                    Edit Uplift
                </Button>
            </CardHeader>
            <CardContent>
                <Skeleton v-if="loading" class="h-10 w-full" />
                <div v-else class="flex flex-col gap-1">
                    <p class="text-2xl font-bold">
                        {{ formattedUplift }}
                        <span class="text-sm font-normal text-muted-foreground">per lesson</span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Added to Drive package prices when a learner selects this
                        instructor during website signup. Bespoke packages below are
                        never affected. £0.00 = base pricing.
                    </p>
                </div>
            </CardContent>
        </Card>

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

        <!-- Price Uplift Sheet -->
        <Sheet v-model:open="isUpliftSheetOpen">
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5" />
                        Edit Drive Package Uplift
                    </SheetTitle>
                </SheetHeader>
                <form @submit.prevent="saveUplift" class="mt-6 space-y-6 px-6 py-4">
                    <div class="flex flex-col gap-2">
                        <Label for="price_uplift">Uplift per lesson (£)</Label>
                        <Input
                            id="price_uplift"
                            v-model="upliftInput"
                            type="number"
                            step="0.01"
                        />
                        <p class="text-sm text-muted-foreground">
                            Example: a £5.00 uplift adds £50.00 to a 10-lesson Drive
                            package for learners choosing this instructor. Use 0 for
                            base pricing; negative values discount.
                        </p>
                    </div>
                    <Button
                        type="submit"
                        :disabled="savingUplift"
                        class="cursor-pointer min-w-[140px]"
                    >
                        <Loader2 v-if="savingUplift" class="mr-2 h-4 w-4 animate-spin" />
                        <TrendingUp v-else class="mr-2 h-4 w-4" />
                        Save uplift
                    </Button>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Package Form Sheet -->
        <Sheet :open="isSheetOpen" @update:open="closeSheet">
            <SheetContent class="w-full sm:max-w-xl overflow-y-auto">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <PackagePlus v-if="isCreating" class="h-5 w-5" />
                        <PackageOpen v-else class="h-5 w-5" />
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
