<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2, UserPlus, UserPen, Save, Trash2, AlertTriangle } from 'lucide-vue-next'
import type { CreateInstructorData, InstructorDetail } from '@/types/instructor'

interface Props {
    open: boolean
    instructor?: InstructorDetail | null
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'instructor-created'): void
    (e: 'instructor-updated'): void
}

const props = withDefaults(defineProps<Props>(), {
    instructor: null,
})
const emit = defineEmits<Emits>()

const isEditMode = computed(() => props.instructor !== null)

const isSubmitting = ref(false)
const isRequestingDeletion = ref(false)
const showDeleteDialog = ref(false)
const form = ref<CreateInstructorData>({
    name: '',
    email: '',
    password: '',
    phone: '',
    bio: '',
    transmission_type: 'manual',
    status: 'active',
    pdi_status: '',
    address: '',
    postcode: '',
})

const errors = ref<Record<string, string>>({})

// Watch for instructor prop changes and populate form
watch(
    () => props.instructor,
    (instructor) => {
        if (instructor) {
            form.value = {
                name: instructor.name,
                email: instructor.email,
                password: '',
                phone: instructor.phone || '',
                bio: instructor.bio || '',
                transmission_type: instructor.transmission_type,
                status: instructor.status,
                pdi_status: '',
                address: '',
                postcode: instructor.postcode || '',
            }
        } else {
            // Reset form for add mode
            form.value = {
                name: '',
                email: '',
                password: '',
                phone: '',
                bio: '',
                transmission_type: 'manual',
                status: 'active',
                pdi_status: '',
                address: '',
                postcode: '',
            }
        }
    },
    { immediate: true }
)

const handleSubmit = () => {
    isSubmitting.value = true
    errors.value = {}

    if (isEditMode.value && props.instructor) {
        // Update existing instructor
        router.put(`/instructors/${props.instructor.id}`, form.value, {
            preserveScroll: true,
            onSuccess: () => {
                emit('instructor-updated')
                emit('update:open', false)
            },
            onError: (formErrors) => {
                errors.value = formErrors as Record<string, string>
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        })
    } else {
        // Create new instructor
        router.post('/instructors', form.value, {
            preserveScroll: true,
            onSuccess: () => {
                // Reset form
                form.value = {
                    name: '',
                    email: '',
                    password: '',
                    phone: '',
                    bio: '',
                    transmission_type: 'manual',
                    status: 'active',
                    pdi_status: '',
                    address: '',
                    postcode: '',
                }
                emit('instructor-created')
                emit('update:open', false)
            },
            onError: (formErrors) => {
                errors.value = formErrors as Record<string, string>
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        })
    }
}

const handleOpenChange = (value: boolean) => {
    if (!isSubmitting.value) {
        emit('update:open', value)
    }
}

const handleRequestDeletion = () => {
    if (!props.instructor) return

    isRequestingDeletion.value = true

    router.post(`/instructors/${props.instructor.id}/request-deletion`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false
        },
        onError: () => {
            // Error handling - the error will be displayed via toast/notification
        },
        onFinish: () => {
            isRequestingDeletion.value = false
        },
    })
}
</script>

<template>
    <Sheet :open="props.open" @update:open="handleOpenChange">
        <SheetContent class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <UserPen v-if="isEditMode" class="h-5 w-5" />
                    <UserPlus v-else class="h-5 w-5" />
                    {{ isEditMode ? 'Edit Instructor' : 'Add New Instructor' }}
                </SheetTitle>
                <SheetDescription>
                    {{
                        isEditMode
                            ? 'Update instructor profile information.'
                            : 'Create a new instructor profile. All fields marked with * are required.'
                    }}
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold">Basic Information</h3>

                    <div class="space-y-2">
                        <Label for="name">Full Name *</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="John Doe"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.name" class="text-sm text-destructive">
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Email *</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="john@example.com"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.email" class="text-sm text-destructive">
                            {{ errors.email }}
                        </p>
                    </div>

                    <div v-if="!isEditMode" class="space-y-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            placeholder="Leave blank for default (password123)"
                            :disabled="isSubmitting"
                        />
                        <p class="text-xs text-muted-foreground">
                            Default password: password123
                        </p>
                        <p v-if="errors.password" class="text-sm text-destructive">
                            {{ errors.password }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            placeholder="07700 900123"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.phone" class="text-sm text-destructive">
                            {{ errors.phone }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="transmission_type">Transmission Type *</Label>
                        <select
                            id="transmission_type"
                            v-model="form.transmission_type"
                            :disabled="isSubmitting"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="manual">Manual</option>
                            <option value="automatic">Automatic</option>
                        </select>
                        <p
                            v-if="errors.transmission_type"
                            class="text-sm text-destructive"
                        >
                            {{ errors.transmission_type }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="bio">Bio</Label>
                        <textarea
                            id="bio"
                            v-model="form.bio"
                            placeholder="Brief instructor biography..."
                            :disabled="isSubmitting"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <p v-if="errors.bio" class="text-sm text-destructive">
                            {{ errors.bio }}
                        </p>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold">Address Information</h3>

                    <div class="space-y-2">
                        <Label for="address">Address</Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            type="text"
                            placeholder="123 Main Street, City"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.address" class="text-sm text-destructive">
                            {{ errors.address }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="postcode">Postcode</Label>
                        <Input
                            id="postcode"
                            v-model="form.postcode"
                            type="text"
                            placeholder="M1 1AA"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.postcode" class="text-sm text-destructive">
                            {{ errors.postcode }}
                        </p>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold">Additional Information</h3>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Input
                            id="status"
                            v-model="form.status"
                            type="text"
                            placeholder="active"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.status" class="text-sm text-destructive">
                            {{ errors.status }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pdi_status">PDI Status</Label>
                        <Input
                            id="pdi_status"
                            v-model="form.pdi_status"
                            type="text"
                            placeholder="e.g., qualified, trainee"
                            :disabled="isSubmitting"
                        />
                        <p v-if="errors.pdi_status" class="text-sm text-destructive">
                            {{ errors.pdi_status }}
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleOpenChange(false)"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ isEditMode ? 'Save Changes' : 'Create Instructor' }}
                    </Button>
                </div>
            </form>

            <!-- Danger Zone -->
            <div v-if="isEditMode && instructor" class="mt-8 border-t border-destructive/20 px-6 py-6">
                <div class="rounded-lg border border-destructive/50 bg-destructive/5 p-4">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        <h3 class="font-semibold text-destructive">Danger Zone</h3>
                    </div>
                    <p class="mt-2 text-sm text-destructive/80">
                        Once you request account deletion, an administrator will review your request.
                        This action cannot be undone.
                    </p>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        class="mt-4"
                        @click="showDeleteDialog = true"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Request Account Deletion
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-destructive">
                    <AlertTriangle class="h-5 w-5" />
                    Request Account Deletion
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to request deletion of your account?
                    An administrator will review this request and contact you if needed.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4">
                <Button
                    type="button"
                    variant="outline"
                    @click="showDeleteDialog = false"
                    :disabled="isRequestingDeletion"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="handleRequestDeletion"
                    :disabled="isRequestingDeletion"
                >
                    <Loader2 v-if="isRequestingDeletion" class="mr-2 h-4 w-4 animate-spin" />
                    <Trash2 v-else class="mr-2 h-4 w-4" />
                    Request Deletion
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
