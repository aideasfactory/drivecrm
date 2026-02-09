<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Mail, Phone, MapPin, Edit } from 'lucide-vue-next'
import type { InstructorDetail } from '@/types/instructor'

interface Props {
    instructor: InstructorDetail
}

interface Emits {
    (e: 'edit'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}
</script>

<template>
    <div class="flex flex-col gap-4 border-b pb-6">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <!-- Large Avatar -->
                <Avatar class="h-20 w-20">
                    <AvatarFallback class="text-2xl">
                        {{ getInitials(instructor.name) }}
                    </AvatarFallback>
                </Avatar>

                <!-- Instructor Info -->
                <div class="flex flex-col gap-3">
                    <h2 class="text-3xl font-bold">{{ instructor.name }}</h2>

                    <!-- Contact Info Row -->
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div
                            v-if="instructor.phone"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <Phone class="h-4 w-4" />
                            <span>{{ instructor.phone }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Mail class="h-4 w-4" />
                            <span>{{ instructor.email }}</span>
                        </div>

                        <div
                            v-if="instructor.postcode"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <MapPin class="h-4 w-4" />
                            <span>{{ instructor.postcode }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Button -->
            <Button @click="emit('edit')">
                <Edit class="mr-2 h-4 w-4" />
                Edit Profile
            </Button>
        </div>
    </div>
</template>
