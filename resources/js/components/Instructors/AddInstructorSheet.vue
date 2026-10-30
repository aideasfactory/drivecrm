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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2, UserPlus, UserPen, Save, Trash2, AlertTriangle, Camera, X } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import PasswordResetSection from '@/components/Shared/PasswordResetSection.vue'
import { destroy as destroyInstructor } from '@/actions/App/Http/Controllers/InstructorController'
import type {
    CreateInstructorData,
    InstructorDetail,
    InstructorFormOptions,
} from '@/types/instructor'

interface Props {
    open: boolean
    instructor?: InstructorDetail | null
    formOptions: InstructorFormOptions
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
const isDeletingInstructor = ref(false)
const showDeleteDialog = ref(false)

const defaultStatus = () =>
    props.formOptions.status[0]?.value ?? 'active'
const defaultPdiStatus = () =>
    props.formOptions.pdi_status[0]?.value ?? 'qualified'
const defaultTransmission = () =>
    (props.formOptions.transmission_type[0]?.value as
        | 'manual'
        | 'automatic'
        | 'both'
        | undefined) ?? 'manual'

const emptyForm = (): CreateInstructorData => ({
    name: '',
    email: '',
    phone: '',
    bio: '',
    transmission_type: defaultTransmission(),
    status: defaultStatus(),
    pdi_status: defaultPdiStatus(),
    address: '',
    postcode: '',
})

const form = ref<CreateInstructorData>(emptyForm())

const selectClass =
    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50'

const errors = ref<Record<string, string>>({})
const isUploadingPicture = ref(false)
const isDeletingPicture = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const handleAvatarUpload = (event: Event) => {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file || !props.instructor) return

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
    if (!allowedTypes.includes(file.type)) {
        toast({ title: 'Invalid file type. Please use JPG, PNG, or WebP.', variant: 'destructive' })
        return
    }

    if (file.size > 5 * 1024 * 1024) {
        toast({ title: 'File too large. Maximum size is 5MB.', variant: 'destructive' })
        return
    }

    isUploadingPicture.value = true

    router.post(`/instructors/${props.instructor.id}/profile-picture`, {
        profile_picture: file,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast({ title: 'Profile picture updated.' })
        },
        onError: () => {
            toast({ title: 'Failed to upload profile picture.', variant: 'destructive' })
        },
        onFinish: () => {
            isUploadingPicture.value = false
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

const handleAvatarDelete = () => {
    if (!props.instructor) return

    isDeletingPicture.value = true

    router.delete(`/instructors/${props.instructor.id}/profile-picture`, {
        preserveScroll: true,
        onSuccess: () => {
            toast({ title: 'Profile picture removed.' })
        },
        onError: () => {
            toast({ title: 'Failed to remove profile picture.', variant: 'destructive' })
        },
        onFinish: () => {
            isDeletingPicture.value = false
        },
    })
}

// Snap an incoming value to a valid option (or the default) so legacy
// rows with non-enum values can still be opened without crashing the select.
const snapToOption = (
    value: string | null | undefined,
    options: { value: string }[],
    fallback: string,
): string => {
    if (value && options.some((o) => o.value === value)) return value
    return fallback
}

// Watch for instructor prop changes and populate form
watch(
    () => props.instructor,
    (instructor) => {
        if (instructor) {
            form.value = {
                name: instructor.name,
                email: instructor.email,
                phone: instructor.phone || '',
                bio: instructor.bio || '',
                transmission_type: snapToOption(
                    instructor.transmission_type,
                    props.formOptions.transmission_type,
                    defaultTransmission(),
                ) as 'manual' | 'automatic' | 'both',
                status: snapToOption(
                    instructor.status,
                    props.formOptions.status,
                    defaultStatus(),
                ),
                pdi_status: snapToOption(
                    instructor.pdi_status,
                    props.formOptions.pdi_status,
                    defaultPdiStatus(),
                ),
                address: instructor.address || '',
                postcode: instructor.postcode || '',
            }
        } else {
            form.value = emptyForm()
        }
    },
    { immediate: true }
)

const showValidationToast = (formErrors: Record<string, string>) => {
    const messages = Object.values(formErrors).filter(Boolean)
    const description = messages.length > 0
        ? messages[0]
        : 'Please check the form and try again.'

    toast({
        title: isEditMode.value
            ? 'Could not update instructor'
            : 'Could not create instructor',
        description,
        variant: 'destructive',
    })
}

const handleSubmit = () => {
    isSubmitting.value = true
    errors.value = {}

    if (isEditMode.value && props.instructor) {
        // Update existing instructor
        router.put(`/instructors/${props.instructor.id}`, form.value, {
            preserveScroll: true,
            onSuccess: () => {
                toast({ title: 'Instructor updated.' })
                emit('instructor-updated')
                emit('update:open', false)
            },
            onError: (formErrors) => {
                errors.value = formErrors as Record<string, string>
                showValidationToast(errors.value)
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
                form.value = emptyForm()
                toast({ title: 'Instructor created. A setup email has been sent.' })
                emit('instructor-created')
                emit('update:open', false)
            },
            onError: (formErrors) => {
                errors.value = formErrors as Record<string, string>
                showValidationToast(errors.value)
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

const handleDeleteInstructor = () => {
    if (!props.instructor) return

    isDeletingInstructor.value = true

    router.delete(destroyInstructor.url(props.instructor.id), {
        onSuccess: (page) => {
            const flash = page.props.flash as { success?: string | null } | undefined
            showDeleteDialog.value = false
            emit('update:open', false)
            toast({ title: flash?.success ?? 'Instructor deleted.' })
        },
        onError: () => {
            toast({ title: 'Failed to delete instructor. Please try again.', variant: 'destructive' })
        },
        onFinish: () => {
            isDeletingInstructor.value = false
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
                            : 'Create a new instructor profile. They will be emailed a link to set their own password. All fields marked with * are required.'
                    }}
                </SheetDescription>
            </SheetHeader>

            <!-- Avatar Upload (Edit Mode Only) -->
            <div v-if="isEditMode && instructor" class="mt-6 px-6">
                <div class="flex items-center gap-4">
                    <Avatar class="h-16 w-16">
                        <AvatarImage v-if="instructor.avatar" :src="instructor.avatar" :alt="instructor.name" />
                        <AvatarFallback class="text-lg">
                            {{ getInitials(instructor.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="isUploadingPicture"
                                @click="fileInput?.click()"
                            >
                                <Loader2 v-if="isUploadingPicture" class="mr-2 h-4 w-4 animate-spin" />
                                <Camera v-else class="mr-2 h-4 w-4" />
                                {{ instructor.avatar ? 'Change Photo' : 'Upload Photo' }}
                            </Button>
                            <Button
                                v-if="instructor.avatar"
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="isDeletingPicture"
                                @click="handleAvatarDelete"
                            >
                                <Loader2 v-if="isDeletingPicture" class="mr-2 h-4 w-4 animate-spin" />
                                <X v-else class="mr-2 h-4 w-4" />
                                Remove
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">JPG, PNG or WebP. Max 5MB.</p>
                    </div>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="handleAvatarUpload"
                    />
                </div>
            </div>

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
                        <p v-if="!isEditMode" class="text-xs text-muted-foreground">
                            We'll email them a link to set their password. They can then sign in to the admin area.
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
                            :class="selectClass"
                        >
                            <option
                                v-for="option in formOptions.transmission_type"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
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
                        <Label for="postcode">Postcode *</Label>
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
                        <select
                            id="status"
                            v-model="form.status"
                            :disabled="isSubmitting"
                            :class="selectClass"
                        >
                            <option
                                v-for="option in formOptions.status"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="errors.status" class="text-sm text-destructive">
                            {{ errors.status }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pdi_status">PDI Status</Label>
                        <select
                            id="pdi_status"
                            v-model="form.pdi_status"
                            :disabled="isSubmitting"
                            :class="selectClass"
                        >
                            <option
                                v-for="option in formOptions.pdi_status"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
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

            <!-- Password Reset (Edit Mode Only) -->
            <div v-if="isEditMode && instructor" class="mt-8 border-t px-6 py-6">
                <PasswordResetSection :reset-url="`/instructors/${instructor.id}/password`" />
            </div>

            <!-- Danger Zone -->
            <div v-if="isEditMode && instructor" class="mt-8 border-t border-destructive/20 px-6 py-6">
                <div class="rounded-lg border border-destructive/50 bg-destructive/5 p-4">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        <h3 class="font-semibold text-destructive">Danger Zone</h3>
                    </div>
                    <p class="mt-2 text-sm text-destructive/80">
                        Deleting this instructor removes their personal details, signs them out of the app
                        and unassigns their pupils. Lesson and payment history is kept.
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
                        Delete Instructor
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
                    Delete Instructor
                </DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {{ instructor?.name ?? 'this instructor' }}?
                    Their pupils will be unassigned and they will no longer be able to sign in.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4">
                <Button
                    type="button"
                    variant="outline"
                    @click="showDeleteDialog = false"
                    :disabled="isDeletingInstructor"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="handleDeleteInstructor"
                    :disabled="isDeletingInstructor"
                >
                    <Loader2 v-if="isDeletingInstructor" class="mr-2 h-4 w-4 animate-spin" />
                    <Trash2 v-else class="mr-2 h-4 w-4" />
                    Delete Instructor
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
