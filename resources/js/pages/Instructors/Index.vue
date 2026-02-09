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
import { Search, Plus, Smartphone } from 'lucide-vue-next'
import type { Instructor } from '@/types/instructor'
import AddInstructorSheet from '@/components/Instructors/AddInstructorSheet.vue'

interface Props {
    instructors: Instructor[]
}

const props = defineProps<Props>()

const searchQuery = ref('')
const isAddSheetOpen = ref(false)

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
                <Button @click="isAddSheetOpen = true" class="cursor-pointer">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Instructor
                </Button>
            </div>

            <!-- Instructors Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>App</TableHead>
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
                                        <Smartphone
                                            class="h-4 w-4"
                                            :class="
                                                instructor.connection_status ===
                                                'connected'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground'
                                            "
                                        />
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
    </AppLayout>
</template>
