<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Search, Plus, Download, FileUp } from 'lucide-vue-next'
import type { Instructor } from '@/types/instructor'
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'
import CsvImportSheet from '@/components/CsvImportSheet.vue'

interface Props {
    instructors: Instructor[]
}

const props = defineProps<Props>()

const searchQuery = ref('')
const isAddSheetOpen = ref(false)
const isCsvImportOpen = ref(false)

const handleCsvImported = () => {
    router.reload()
}

const filteredInstructors = computed(() => {
    if (!searchQuery.value) {
        return props.instructors
    }

    const query = searchQuery.value.toLowerCase()
    return props.instructors.filter(
        (instructor) =>
            instructor.name.toLowerCase().includes(query) ||
            instructor.email.toLowerCase().includes(query)
    )
})

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const navigateToInstructor = (instructorId: number) => {
    router.visit(`/instructors/${instructorId}`)
}

const breadcrumbs = [{ title: 'Instructors' }]
</script>

<template>
    <Head title="Instructors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Instructors</h2>
                <p class="text-muted-foreground">
                    Manage your driving instructors and their performance
                </p>
            </div>

            <!-- Search and Add Button -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search instructors..."
                        class="pl-9"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" as="a" href="/instructors/csv-template">
                        <Download class="mr-2 h-4 w-4" />
                        CSV Template
                    </Button>
                    <Button variant="outline" @click="isCsvImportOpen = true">
                        <FileUp class="mr-2 h-4 w-4" />
                        Upload CSV
                    </Button>
                    <Button @click="isAddSheetOpen = true" class="cursor-pointer">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Instructor
                    </Button>
                </div>
            </div>

            <!-- Instructors Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Stripe</TableHead>
                                <TableHead>Pupils</TableHead>
                                <TableHead>Last Sync</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="instructor in filteredInstructors"
                                :key="instructor.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToInstructor(instructor.id)"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Avatar>
                                            <AvatarFallback>
                                                {{
                                                    getInitials(instructor.name)
                                                }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div class="font-semibold">
                                                {{ instructor.name }}
                                            </div>
                                            <div
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ instructor.email }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <svg
                                            class="h-4 w-4"
                                            :class="
                                                instructor.connection_status ===
                                                'connected'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground'
                                            "
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.918 3.757 7.164c0 4.469 2.978 6.2 6.334 7.476 2.172.831 2.978 1.488 2.978 2.409 0 .921-.831 1.488-2.287 1.488-1.937 0-4.88-.92-6.692-2.172L3.2 21.858C5.37 23.183 8.25 24 11.013 24c2.594 0 4.77-.623 6.334-1.808 1.66-1.275 2.532-3.142 2.532-5.537 0-4.607-3.035-6.334-5.903-7.505z" />
                                        </svg>
                                        <span
                                            :class="
                                                instructor.connection_status ===
                                                'connected'
                                                    ? 'text-foreground'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{
                                                instructor.connection_status ===
                                                'connected'
                                                    ? 'Connected'
                                                    : 'Not Connected'
                                            }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="font-medium">
                                        {{ instructor.pupils_count }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span class="text-muted-foreground">
                                        {{ instructor.last_sync }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredInstructors.length === 0">
                                <TableCell colspan="4" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No instructors found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Add Instructor Sheet -->
        <AddInstructorSheet
            v-model:open="isAddSheetOpen"
            @instructor-created="isAddSheetOpen = false"
        />

        <!-- CSV Import Sheet -->
        <CsvImportSheet
            v-model:open="isCsvImportOpen"
            title="Import Instructors from CSV"
            description="Upload a CSV file to bulk-create instructor records. Download the template first to see the required format."
            import-url="/instructors/import-csv"
            @imported="handleCsvImported"
        />
    </AppLayout>
</template>
