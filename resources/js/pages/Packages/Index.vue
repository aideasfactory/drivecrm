<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Search, Plus, PackagePlus, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next'
import CreatePackageSheet from '@/components/Packages/CreatePackageSheet.vue'
import EditPackageSheet from '@/components/Packages/EditPackageSheet.vue'
import { toast } from '@/components/ui/sonner'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import type { Package } from '@/types/instructor'

interface PackageItem {
    id: number
    name: string
    description: string | null
    total_price_pence: number
    lessons_count: number
    lesson_price_pence: number
    formatted_total_price: string
    formatted_lesson_price: string
    active: boolean
    created_at: string | null
}

interface Props {
    packages: PackageItem[]
}

const props = defineProps<Props>()

const searchQuery = ref('')
const isCreateSheetOpen = ref(false)
const isEditSheetOpen = ref(false)
const editingPackage = ref<Package | null>(null)
const deleting = ref<number | null>(null)

const filteredPackages = computed(() => {
    if (!searchQuery.value) {
        return props.packages
    }

    const query = searchQuery.value.toLowerCase()
    return props.packages.filter(
        (pkg) => pkg.name.toLowerCase().includes(query),
    )
})

const handlePackageCreated = () => {
    router.reload()
}

const handlePackageUpdated = () => {
    router.reload()
}

const handleEdit = (pkg: PackageItem) => {
    editingPackage.value = pkg as unknown as Package
    isEditSheetOpen.value = true
}

const handleDelete = async (pkg: PackageItem) => {
    if (!confirm(`Are you sure you want to delete "${pkg.name}"?`)) return

    deleting.value = pkg.id

    try {
        await axios.delete(`/packages/${pkg.id}`)
        toast.success('Package deleted successfully!')
        router.reload()
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to delete package'
        toast.error(message)
    } finally {
        deleting.value = null
    }
}

const breadcrumbs = [{ title: 'Packages' }]
</script>

<template>
    <Head title="Packages" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Packages</h2>
                <p class="text-muted-foreground">
                    Manage platform lesson packages
                </p>
            </div>

            <!-- Search and Create Button -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search packages..."
                        class="pl-9"
                    />
                </div>
                <Button
                    @click="isCreateSheetOpen = true"
                    class="cursor-pointer"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Create Package
                </Button>
            </div>

            <!-- Packages Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Lessons</TableHead>
                                <TableHead>Price</TableHead>
                                <TableHead>Per Lesson</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="w-[50px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="pkg in filteredPackages"
                                :key="pkg.id"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <PackagePlus
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <div>
                                            <div class="font-semibold">
                                                {{ pkg.name }}
                                            </div>
                                            <div
                                                v-if="pkg.description"
                                                class="text-sm text-muted-foreground line-clamp-1"
                                            >
                                                {{ pkg.description }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="font-medium">
                                        {{ pkg.lessons_count }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span class="font-medium">
                                        {{ pkg.formatted_total_price }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">
                                        {{ pkg.formatted_lesson_price }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            pkg.active
                                                ? 'default'
                                                : 'destructive'
                                        "
                                    >
                                        {{ pkg.active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">
                                        {{ pkg.created_at }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="handleEdit(pkg)">
                                                <Pencil class="mr-2 h-4 w-4" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-destructive"
                                                @click="handleDelete(pkg)"
                                                :disabled="deleting === pkg.id"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredPackages.length === 0">
                                <TableCell colspan="7" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No packages found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Create Package Sheet -->
        <CreatePackageSheet
            v-model:open="isCreateSheetOpen"
            @package-created="handlePackageCreated"
        />

        <!-- Edit Package Sheet -->
        <EditPackageSheet
            v-model:open="isEditSheetOpen"
            :package="editingPackage"
            @package-updated="handlePackageUpdated"
        />
    </AppLayout>
</template>
