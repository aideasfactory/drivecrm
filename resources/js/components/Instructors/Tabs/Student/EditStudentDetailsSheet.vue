<script setup lang="ts">
import { ref, watch } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Separator } from '@/components/ui/separator'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { toast } from '@/components/ui/sonner'
import { Loader2, MapPin, Save, UserPen } from 'lucide-vue-next'
import { update } from '@/actions/App/Http/Controllers/PupilController'

export interface EditableStudentDetail {
    id: number
    first_name: string | null
    surname: string | null
    email: string | null
    phone: string | null
    date_of_birth: string | null
    contact_first_name: string | null
    contact_surname: string | null
    contact_email: string | null
    contact_phone: string | null
    owns_account: boolean
    default_pickup_point: {
        id: number
        label: string | null
        address: string | null
        postcode: string | null
    } | null
}

interface Props {
    open: boolean
    student: EditableStudentDetail
}

const props = defineProps<Props>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    updated: []
    'manage-pickup-points': []
}>()

const buildForm = (student: EditableStudentDetail) => ({
    first_name: student.first_name ?? '',
    surname: student.surname ?? '',
    email: student.email ?? '',
    phone: student.phone ?? '',
    date_of_birth: student.date_of_birth ?? '',
    contact_first_name: student.contact_first_name ?? '',
    contact_surname: student.contact_surname ?? '',
    contact_email: student.contact_email ?? '',
    contact_phone: student.contact_phone ?? '',
    owns_account: student.owns_account,
})

const form = ref(buildForm(props.student))
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.value = buildForm(props.student)
            errors.value = {}
        }
    },
)

const today = new Date().toISOString().slice(0, 10)

const nullIfEmpty = (value: string) => (value.trim() === '' ? null : value.trim())

const handleSubmit = async () => {
    errors.value = {}
    isSaving.value = true

    try {
        await axios.put(update.url(props.student.id), {
            first_name: form.value.first_name.trim(),
            surname: form.value.surname.trim(),
            email: nullIfEmpty(form.value.email),
            phone: nullIfEmpty(form.value.phone),
            date_of_birth: nullIfEmpty(form.value.date_of_birth),
            contact_first_name: nullIfEmpty(form.value.contact_first_name),
            contact_surname: nullIfEmpty(form.value.contact_surname),
            contact_email: nullIfEmpty(form.value.contact_email),
            contact_phone: nullIfEmpty(form.value.contact_phone),
            owns_account: form.value.owns_account,
        })
        toast.success('Pupil details updated')
        emit('update:open', false)
        emit('updated')
    } catch (error: any) {
        if (error.response?.status === 422) {
            const errs = error.response.data.errors || {}
            for (const key in errs) {
                errors.value[key] = Array.isArray(errs[key]) ? errs[key][0] : errs[key]
            }
        } else {
            toast.error(error.response?.data?.message || 'Failed to update pupil details')
        }
    } finally {
        isSaving.value = false
    }
}

const managePickupPoints = () => {
    emit('update:open', false)
    emit('manage-pickup-points')
}
</script>

<template>
    <Sheet :open="open" @update:open="(value: boolean) => emit('update:open', value)">
        <SheetContent side="right" class="overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <UserPen class="h-5 w-5" />
                    Edit Pupil Details
                </SheetTitle>
                <SheetDescription>
                    Update the pupil's contact details and date of birth.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-pupil-first-name">First Name *</Label>
                            <Input
                                id="edit-pupil-first-name"
                                v-model="form.first_name"
                                type="text"
                                :disabled="isSaving"
                            />
                            <p v-if="errors.first_name" class="text-sm text-destructive">
                                {{ errors.first_name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="edit-pupil-surname">Surname *</Label>
                            <Input
                                id="edit-pupil-surname"
                                v-model="form.surname"
                                type="text"
                                :disabled="isSaving"
                            />
                            <p v-if="errors.surname" class="text-sm text-destructive">
                                {{ errors.surname }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-pupil-email">Email</Label>
                        <Input
                            id="edit-pupil-email"
                            v-model="form.email"
                            type="email"
                            :disabled="isSaving"
                        />
                        <p class="text-xs text-muted-foreground">
                            Changing this also changes the pupil's app login email.
                        </p>
                        <p v-if="errors.email" class="text-sm text-destructive">
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-pupil-phone">Phone</Label>
                        <Input
                            id="edit-pupil-phone"
                            v-model="form.phone"
                            type="tel"
                            :disabled="isSaving"
                        />
                        <p v-if="errors.phone" class="text-sm text-destructive">
                            {{ errors.phone }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-pupil-dob">Date of Birth</Label>
                        <Input
                            id="edit-pupil-dob"
                            v-model="form.date_of_birth"
                            type="date"
                            :max="today"
                            :disabled="isSaving"
                        />
                        <p v-if="errors.date_of_birth" class="text-sm text-destructive">
                            {{ errors.date_of_birth }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="edit-pupil-owns-account"
                            v-model="form.owns_account"
                            :disabled="isSaving"
                        />
                        <Label for="edit-pupil-owns-account" class="cursor-pointer">
                            Learner owns this account
                        </Label>
                    </div>
                </div>

                <Separator />

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium">Address</h3>
                        <Button
                            type="button"
                            variant="link"
                            class="h-auto p-0"
                            @click="managePickupPoints"
                        >
                            <MapPin class="mr-1 h-4 w-4" />
                            Manage pickup points
                        </Button>
                    </div>
                    <div
                        v-if="student.default_pickup_point"
                        class="rounded-md border p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ student.default_pickup_point.label || 'Default pickup point' }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ student.default_pickup_point.address }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ student.default_pickup_point.postcode }}
                        </p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No default pickup point set yet.
                    </p>
                    <p class="text-xs text-muted-foreground">
                        The pupil's address is their default pickup point.
                    </p>
                </div>

                <Separator />

                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium">Booker</h3>
                        <p class="text-xs text-muted-foreground">
                            Only needed if someone else (e.g. a parent) books for the pupil.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit-pupil-contact-first-name">First Name</Label>
                            <Input
                                id="edit-pupil-contact-first-name"
                                v-model="form.contact_first_name"
                                type="text"
                                :disabled="isSaving"
                            />
                            <p v-if="errors.contact_first_name" class="text-sm text-destructive">
                                {{ errors.contact_first_name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="edit-pupil-contact-surname">Surname</Label>
                            <Input
                                id="edit-pupil-contact-surname"
                                v-model="form.contact_surname"
                                type="text"
                                :disabled="isSaving"
                            />
                            <p v-if="errors.contact_surname" class="text-sm text-destructive">
                                {{ errors.contact_surname }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-pupil-contact-email">Email</Label>
                        <Input
                            id="edit-pupil-contact-email"
                            v-model="form.contact_email"
                            type="email"
                            :disabled="isSaving"
                        />
                        <p v-if="errors.contact_email" class="text-sm text-destructive">
                            {{ errors.contact_email }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-pupil-contact-phone">Phone</Label>
                        <Input
                            id="edit-pupil-contact-phone"
                            v-model="form.contact_phone"
                            type="tel"
                            :disabled="isSaving"
                        />
                        <p v-if="errors.contact_phone" class="text-sm text-destructive">
                            {{ errors.contact_phone }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSaving"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSaving" class="min-w-[140px]">
                        <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        Save Changes
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
