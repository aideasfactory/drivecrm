<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Search, GraduationCap } from 'lucide-vue-next'
import { show as studentsShow } from '@/routes/students'
import { show as instructorsShow } from '@/routes/instructors'
import type { PupilListing } from '@/types/pupil'

interface Props {
    pupils: PupilListing[]
}

const props = defineProps<Props>()

const searchQuery = ref('')

const filteredPupils = computed(() => {
    if (!searchQuery.value) {
        return props.pupils
    }

    const query = searchQuery.value.toLowerCase()
    return props.pupils.filter(
        (pupil) =>
            pupil.name.toLowerCase().includes(query) ||
            pupil.email?.toLowerCase().includes(query) ||
            pupil.instructor_name?.toLowerCase().includes(query),
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

const navigateToPupil = (pupilId: number) => {
    router.visit(studentsShow.url(pupilId))
}

const navigateToInstructor = (event: Event, instructorId: number) => {
    event.stopPropagation()
    router.visit(instructorsShow.url(instructorId))
}

const breadcrumbs = [{ title: 'Pupils' }]
</script>

<template>
    <Head title="Pupils" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold">Pupils</h2>
                <p class="text-muted-foreground">
                    Manage your learner drivers and view their assigned
                    instructors
                </p>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-4">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search pupils or instructors..."
                        class="pl-9"
                    />
                </div>
            </div>

            <!-- Pupils Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Pupil</TableHead>
                                <TableHead>Instructor</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="pupil in filteredPupils"
                                :key="pupil.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToPupil(pupil.id)"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Avatar>
                                            <AvatarFallback>
                                                {{
                                                    getInitials(pupil.name)
                                                }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div class="font-semibold">
                                                {{ pupil.name }}
                                            </div>
                                            <div
                                                v-if="pupil.email"
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ pupil.email }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="pupil.instructor_id && pupil.instructor_name"
                                        class="flex items-center gap-3"
                                    >
                                        <Avatar class="h-8 w-8">
                                            <AvatarFallback class="text-xs">
                                                {{
                                                    getInitials(
                                                        pupil.instructor_name,
                                                    )
                                                }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span
                                            class="font-medium text-primary hover:underline"
                                            @click="
                                                navigateToInstructor(
                                                    $event,
                                                    pupil.instructor_id,
                                                )
                                            "
                                        >
                                            {{ pupil.instructor_name }}
                                        </span>
                                    </div>
                                    <Badge
                                        v-else
                                        variant="secondary"
                                        class="gap-1"
                                    >
                                        <GraduationCap class="h-3 w-3" />
                                        Unassigned
                                    </Badge>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredPupils.length === 0">
                                <TableCell colspan="2" class="text-center">
                                    <div class="py-8 text-muted-foreground">
                                        No pupils found
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
