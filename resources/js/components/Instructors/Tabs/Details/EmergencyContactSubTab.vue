<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
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
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import {
    Phone,
    Plus,
    Pencil,
    Trash2,
    Loader2,
    Save,
    UserRoundPlus,
    Mail,
    HeartHandshake,
    Shield,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'
import type { InstructorDetail } from '@/types/instructor'

interface Contact {
    id: number
    name: string
    relationship: string
    phone: string
    email: string | null
    is_primary: boolean
    created_at: string
    updated_at: string
}

interface ContactFormData {
    name: string
    relationship: string
    phone: string
    email: string
    is_primary: boolean
}

const props = defineProps<{
    instructor: InstructorDetail
}>()

const relationshipOptions = [
    'Spouse',
    'Parent',
    'Child',
    'Sibling',
    'Friend',
    'Doctor',
    'Other',
]

// State
const contacts = ref<Contact[]>([])
const isLoading = ref(true)
const isSheetOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const isSubmitting = ref(false)
const isDeleting = ref(false)
const isSettingPrimary = ref<number | null>(null)
const editingContact = ref<Contact | null>(null)
const deleteContactId = ref<number | null>(null)
const errors = ref<Record<string, string>>({})

const form = ref<ContactFormData>({
    name: '',
    relationship: '',
    phone: '',
    email: '',
    is_primary: false,
})

// Computed
const hasContacts = computed(() => contacts.value.length > 0)
const isEditMode = computed(() => editingContact.value !== null)
const deleteContact = computed(() =>
    contacts.value.find((c) => c.id === deleteContactId.value),
)

// Load contacts
const loadContacts = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(
            `/instructors/${props.instructor.id}/contacts`,
        )
        contacts.value = response.data.contacts || []
    } catch (error) {
        toast({ title: 'Failed to load emergency contacts', variant: 'destructive' })
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadContacts()
})

// Open sheet for adding
const openAddSheet = () => {
    editingContact.value = null
    form.value = {
        name: '',
        relationship: '',
        phone: '',
        email: '',
        is_primary: false,
    }
    errors.value = {}
    isSheetOpen.value = true
}

// Open sheet for editing
const openEditSheet = (contact: Contact) => {
    editingContact.value = contact
    form.value = {
        name: contact.name,
        relationship: contact.relationship,
        phone: contact.phone,
        email: contact.email || '',
        is_primary: contact.is_primary,
    }
    errors.value = {}
    isSheetOpen.value = true
}

// Handle form submit (create or update)
const handleSubmit = async () => {
    errors.value = {}
    isSubmitting.value = true

    try {
        if (isEditMode.value && editingContact.value) {
            const response = await axios.put(
                `/instructors/${props.instructor.id}/contacts/${editingContact.value.id}`,
                form.value,
            )

            // Update local state
            const index = contacts.value.findIndex(
                (c) => c.id === editingContact.value!.id,
            )
            if (index !== -1) {
                contacts.value[index] = response.data.contact
            }

            // If this was set as primary, unset others locally
            if (response.data.contact.is_primary) {
                contacts.value.forEach((c) => {
                    if (c.id !== response.data.contact.id) {
                        c.is_primary = false
                    }
                })
            }

            toast({ title: 'Contact updated successfully' })
        } else {
            const response = await axios.post(
                `/instructors/${props.instructor.id}/contacts`,
                form.value,
            )

            // If new contact is primary, unset others locally
            if (response.data.contact.is_primary) {
                contacts.value.forEach((c) => {
                    c.is_primary = false
                })
            }

            contacts.value.push(response.data.contact)
            toast({ title: 'Contact added successfully' })
        }

        // Sort: primary first, then by name
        contacts.value.sort((a, b) => {
            if (a.is_primary !== b.is_primary)
                return a.is_primary ? -1 : 1
            return a.name.localeCompare(b.name)
        })

        isSheetOpen.value = false
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            )
        } else {
            const message =
                error.response?.data?.message || 'Failed to save contact'
            toast({ title: message, variant: 'destructive' })
        }
    } finally {
        isSubmitting.value = false
    }
}

// Open delete dialog
const openDeleteDialog = (contactId: number) => {
    deleteContactId.value = contactId
    isDeleteDialogOpen.value = true
}

// Handle delete
const handleDelete = async () => {
    if (!deleteContactId.value) return

    isDeleting.value = true

    try {
        await axios.delete(
            `/instructors/${props.instructor.id}/contacts/${deleteContactId.value}`,
        )

        contacts.value = contacts.value.filter(
            (c) => c.id !== deleteContactId.value,
        )
        toast({ title: 'Contact deleted successfully' })
        isDeleteDialogOpen.value = false
        deleteContactId.value = null
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to delete contact'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isDeleting.value = false
    }
}

// Set as primary
const handleSetPrimary = async (contact: Contact) => {
    isSettingPrimary.value = contact.id

    try {
        await axios.patch(
            `/instructors/${props.instructor.id}/contacts/${contact.id}/primary`,
        )

        // Update local state
        contacts.value.forEach((c) => {
            c.is_primary = c.id === contact.id
        })

        // Re-sort
        contacts.value.sort((a, b) => {
            if (a.is_primary !== b.is_primary)
                return a.is_primary ? -1 : 1
            return a.name.localeCompare(b.name)
        })

        toast({ title: `${contact.name} set as primary contact` })
    } catch (error: any) {
        const message =
            error.response?.data?.message ||
            'Failed to set primary contact'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isSettingPrimary.value = null
    }
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <Shield class="h-5 w-5" />
                Emergency Contacts
            </h3>
            <Button v-if="!isLoading" @click="openAddSheet">
                <Plus class="mr-2 h-4 w-4" />
                Add Contact
            </Button>
        </div>

        <!-- Loading Skeletons -->
        <div v-if="isLoading" class="space-y-4">
            <Skeleton v-for="n in 3" :key="n" class="h-32 w-full" />
        </div>

        <!-- Loaded Content -->
        <div v-else>
            <!-- Empty State -->
            <Card v-if="!hasContacts">
                <CardContent class="p-6">
                    <div
                        class="flex min-h-[300px] flex-col items-center justify-center gap-4 text-muted-foreground"
                    >
                        <Phone class="h-12 w-12" />
                        <div class="text-center">
                            <p class="text-lg font-medium">
                                No emergency contacts
                            </p>
                            <p class="mt-2 text-sm">
                                Add emergency contacts using the button above
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Contact Cards -->
            <div v-else class="max-h-[600px] space-y-4 overflow-y-auto">
                <Card
                    v-for="contact in contacts"
                    :key="contact.id"
                >
                    <CardContent class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <!-- Name + Primary Badge -->
                                <div
                                    class="mb-3 flex items-center gap-3"
                                >
                                    <h4 class="text-lg font-semibold">
                                        {{ contact.name }}
                                    </h4>
                                    <Badge
                                        v-if="contact.is_primary"
                                        variant="default"
                                    >
                                        Primary
                                    </Badge>
                                    <Button
                                        v-else
                                        variant="secondary"
                                        size="sm"
                                        :disabled="
                                            isSettingPrimary ===
                                            contact.id
                                        "
                                        @click="
                                            handleSetPrimary(contact)
                                        "
                                        class="h-7 text-xs"
                                    >
                                        <Loader2
                                            v-if="
                                                isSettingPrimary ===
                                                contact.id
                                            "
                                            class="mr-1 h-3 w-3 animate-spin"
                                        />
                                        Set as Primary
                                    </Button>
                                </div>

                                <!-- Contact Details -->
                                <div class="space-y-2">
                                    <div
                                        class="flex items-center gap-3 text-sm text-muted-foreground"
                                    >
                                        <HeartHandshake class="h-4 w-4" />
                                        <span>{{
                                            contact.relationship
                                        }}</span>
                                    </div>
                                    <div
                                        class="flex items-center gap-3 text-sm text-muted-foreground"
                                    >
                                        <Phone class="h-4 w-4" />
                                        <span>{{ contact.phone }}</span>
                                    </div>
                                    <div
                                        v-if="contact.email"
                                        class="flex items-center gap-3 text-sm text-muted-foreground"
                                    >
                                        <Mail class="h-4 w-4" />
                                        <span>{{ contact.email }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditSheet(contact)"
                                    class="h-8 w-8 p-0"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        openDeleteDialog(contact.id)
                                    "
                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Add/Edit Contact Sheet -->
        <Sheet v-model:open="isSheetOpen">
            <SheetContent side="right" class="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <UserRoundPlus
                            v-if="!isEditMode"
                            class="h-5 w-5"
                        />
                        <Pencil v-else class="h-5 w-5" />
                        {{
                            isEditMode
                                ? 'Edit Emergency Contact'
                                : 'Add Emergency Contact'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            isEditMode
                                ? 'Update the emergency contact details.'
                                : 'Add a new emergency contact for this instructor.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <form
                    @submit.prevent="handleSubmit"
                    class="mt-6 space-y-6 px-6 py-4"
                >
                    <div class="space-y-2">
                        <Label for="contact_name">Full Name *</Label>
                        <Input
                            id="contact_name"
                            v-model="form.name"
                            placeholder="e.g., Sarah Mitchell"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact_relationship"
                            >Relationship *</Label
                        >
                        <select
                            id="contact_relationship"
                            v-model="form.relationship"
                            :disabled="isSubmitting"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" disabled>
                                Select relationship
                            </option>
                            <option
                                v-for="option in relationshipOptions"
                                :key="option"
                                :value="option"
                            >
                                {{ option }}
                            </option>
                        </select>
                        <p
                            v-if="errors.relationship"
                            class="text-sm text-destructive"
                        >
                            {{ errors.relationship }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact_phone"
                            >Phone Number *</Label
                        >
                        <Input
                            id="contact_phone"
                            v-model="form.phone"
                            type="tel"
                            placeholder="e.g., 07700 900456"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.phone"
                            class="text-sm text-destructive"
                        >
                            {{ errors.phone }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="contact_email"
                            >Email Address</Label
                        >
                        <Input
                            id="contact_email"
                            v-model="form.email"
                            type="email"
                            placeholder="e.g., sarah@email.com"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.email"
                            class="text-sm text-destructive"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="contact_primary"
                            type="checkbox"
                            v-model="form.is_primary"
                            :disabled="isSubmitting"
                            class="h-4 w-4 rounded border-input accent-primary"
                        />
                        <Label
                            for="contact_primary"
                            class="cursor-pointer text-sm"
                        >
                            Set as primary contact
                        </Label>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSheetOpen = false"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="isSubmitting"
                            class="min-w-[120px]"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Save
                                v-else
                                class="mr-2 h-4 w-4"
                            />
                            {{
                                isEditMode
                                    ? 'Save Changes'
                                    : 'Add Contact'
                            }}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Emergency Contact?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to remove
                        <strong class="font-semibold text-foreground">{{
                            deleteContact?.name
                        }}</strong>
                        from the emergency contacts? This action cannot
                        be undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isDeleteDialogOpen = false"
                        :disabled="isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleDelete"
                        :disabled="isDeleting"
                        class="min-w-[100px]"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2
                            v-else
                            class="mr-2 h-4 w-4"
                        />
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
