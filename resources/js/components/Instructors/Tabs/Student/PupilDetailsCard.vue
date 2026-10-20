<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { toast } from '@/components/ui/sonner'
import { User, Pencil, Loader2, Save } from 'lucide-vue-next'

interface AddressDetails {
    address_line_1: string | null
    address_line_2: string | null
    city: string | null
    postcode: string | null
    date_of_birth: string | null
}

interface PupilDetails extends AddressDetails {
    first_name: string | null
    surname: string | null
    email: string | null
    phone: string | null
    owns_account: boolean
    has_app: boolean
    contact_first_name: string | null
    contact_surname: string | null
    contact_email: string | null
    contact_phone: string | null
    booking_details: AddressDetails
}

interface PupilDetailsForm {
    first_name: string
    surname: string
    email: string
    phone: string
    address_line_1: string
    address_line_2: string
    city: string
    postcode: string
    date_of_birth: string
    owns_account: boolean
    contact_first_name: string
    contact_surname: string
    contact_email: string
    contact_phone: string
}

type AddressField = keyof AddressDetails

const props = defineProps<{
    studentId: number
}>()

const emit = defineEmits<{
    (e: 'updated'): void
}>()

const details = ref<PupilDetails | null>(null)
const isLoading = ref(true)
const isSheetOpen = ref(false)
const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})
const form = ref<PupilDetailsForm>(emptyForm())

const basePath = `/students/${props.studentId}`

function emptyForm(): PupilDetailsForm {
    return {
        first_name: '',
        surname: '',
        email: '',
        phone: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        postcode: '',
        date_of_birth: '',
        owns_account: true,
        contact_first_name: '',
        contact_surname: '',
        contact_email: '',
        contact_phone: '',
    }
}

const storedOrBooked = (field: AddressField): string | null =>
    details.value?.[field] || details.value?.booking_details?.[field] || null

const isFromBooking = (field: AddressField): boolean =>
    !details.value?.[field] && !!details.value?.booking_details?.[field]

const hasBookingFallback = computed(() =>
    (['address_line_1', 'address_line_2', 'city', 'postcode', 'date_of_birth'] as AddressField[]).some(isFromBooking),
)

const formatDate = (value: string | null): string | null => {
    if (!value) return null
    const date = new Date(`${value}T00:00:00`)
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

const fullName = (first: string | null, last: string | null): string | null =>
    [first, last].filter(Boolean).join(' ') || null

const loadDetails = async () => {
    isLoading.value = true
    try {
        const response = await axios.get(basePath)
        details.value = response.data.student
    } catch {
        toast.error('Failed to load pupil details')
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadDetails()
})

const openEditSheet = () => {
    if (!details.value) return
    const d = details.value

    form.value = {
        first_name: d.first_name ?? '',
        surname: d.surname ?? '',
        email: d.email ?? '',
        phone: d.phone ?? '',
        address_line_1: storedOrBooked('address_line_1') ?? '',
        address_line_2: storedOrBooked('address_line_2') ?? '',
        city: storedOrBooked('city') ?? '',
        postcode: storedOrBooked('postcode') ?? '',
        date_of_birth: storedOrBooked('date_of_birth') ?? '',
        owns_account: d.owns_account,
        contact_first_name: d.contact_first_name ?? '',
        contact_surname: d.contact_surname ?? '',
        contact_email: d.contact_email ?? '',
        contact_phone: d.contact_phone ?? '',
    }
    errors.value = {}
    isSheetOpen.value = true
}

const handleSubmit = async () => {
    errors.value = {}
    isSubmitting.value = true

    try {
        const response = await axios.put(basePath, form.value)
        details.value = response.data.student
        isSheetOpen.value = false
        toast.success(response.data.message || 'Pupil details updated')
        emit('updated')
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(([key, val]) => [
                    key,
                    (val as string[])[0],
                ]),
            )
            toast.error('Please fix the highlighted fields')
        } else {
            toast.error(error.response?.data?.message || 'Failed to update pupil details')
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-lg">
                <User class="h-5 w-5" />
                Pupil Details
            </CardTitle>
            <CardAction v-if="!isLoading && details">
                <Button variant="outline" size="sm" @click="openEditSheet">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit Details
                </Button>
            </CardAction>
        </CardHeader>

        <CardContent>
            <!-- Loading Skeleton -->
            <div v-if="isLoading" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="n in 6" :key="n" class="flex flex-col gap-2">
                    <Skeleton class="h-4 w-24" />
                    <Skeleton class="h-5 w-40" />
                </div>
            </div>

            <div v-else-if="details" class="flex flex-col gap-6">
                <dl class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-sm text-muted-foreground">Name</dt>
                        <dd class="font-medium">{{ fullName(details.first_name, details.surname) ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Email</dt>
                        <dd class="font-medium break-all">{{ details.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Phone</dt>
                        <dd class="font-medium">{{ details.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Address</dt>
                        <dd class="font-medium">
                            <template v-if="storedOrBooked('address_line_1') || storedOrBooked('address_line_2') || storedOrBooked('city')">
                                <span v-if="storedOrBooked('address_line_1')" class="block">{{ storedOrBooked('address_line_1') }}</span>
                                <span v-if="storedOrBooked('address_line_2')" class="block">{{ storedOrBooked('address_line_2') }}</span>
                                <span v-if="storedOrBooked('city')" class="block">{{ storedOrBooked('city') }}</span>
                            </template>
                            <template v-else>—</template>
                            <Badge
                                v-if="isFromBooking('address_line_1') || isFromBooking('address_line_2') || isFromBooking('city')"
                                variant="secondary"
                                class="mt-1"
                            >
                                From booking
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Postcode</dt>
                        <dd class="flex items-center gap-2 font-medium">
                            {{ storedOrBooked('postcode') ?? '—' }}
                            <Badge v-if="isFromBooking('postcode')" variant="secondary">From booking</Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Date of birth</dt>
                        <dd class="flex items-center gap-2 font-medium">
                            {{ formatDate(storedOrBooked('date_of_birth')) ?? '—' }}
                            <Badge v-if="isFromBooking('date_of_birth')" variant="secondary">From booking</Badge>
                        </dd>
                    </div>
                </dl>

                <p v-if="hasBookingFallback" class="text-sm text-muted-foreground">
                    Details marked "From booking" come from the pupil's booking form and haven't been saved to their
                    profile yet. Saving the pupil's details stores them on the profile.
                </p>

                <template v-if="!details.owns_account">
                    <Separator />
                    <div class="flex flex-col gap-4">
                        <h4 class="text-sm font-semibold">Booked by (parent / guardian)</h4>
                        <dl class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-sm text-muted-foreground">Name</dt>
                                <dd class="font-medium">
                                    {{ fullName(details.contact_first_name, details.contact_surname) ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm text-muted-foreground">Email</dt>
                                <dd class="font-medium break-all">{{ details.contact_email ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-muted-foreground">Phone</dt>
                                <dd class="font-medium">{{ details.contact_phone ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </template>
            </div>
        </CardContent>

        <!-- Edit Sheet -->
        <Sheet v-model:open="isSheetOpen">
            <SheetContent side="right" class="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <Pencil class="h-5 w-5" />
                        Edit Pupil Details
                    </SheetTitle>
                    <SheetDescription>
                        Update the pupil's personal and contact details. Changes to the name and email also update
                        their app login.
                    </SheetDescription>
                </SheetHeader>

                <form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="pd_first_name">First name *</Label>
                            <Input id="pd_first_name" v-model="form.first_name" :disabled="isSubmitting" />
                            <p v-if="errors.first_name" class="text-sm text-destructive">{{ errors.first_name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pd_surname">Surname *</Label>
                            <Input id="pd_surname" v-model="form.surname" :disabled="isSubmitting" />
                            <p v-if="errors.surname" class="text-sm text-destructive">{{ errors.surname }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="pd_email">Email{{ details?.has_app ? ' *' : '' }}</Label>
                        <Input id="pd_email" v-model="form.email" type="email" :disabled="isSubmitting" />
                        <p v-if="errors.email" class="text-sm text-destructive">{{ errors.email }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="pd_phone">Phone</Label>
                            <Input id="pd_phone" v-model="form.phone" type="tel" :disabled="isSubmitting" />
                            <p v-if="errors.phone" class="text-sm text-destructive">{{ errors.phone }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pd_date_of_birth">Date of birth</Label>
                            <Input
                                id="pd_date_of_birth"
                                v-model="form.date_of_birth"
                                type="date"
                                :disabled="isSubmitting"
                            />
                            <p v-if="errors.date_of_birth" class="text-sm text-destructive">
                                {{ errors.date_of_birth }}
                            </p>
                        </div>
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <Label for="pd_address_line_1">Address line 1</Label>
                        <Input id="pd_address_line_1" v-model="form.address_line_1" :disabled="isSubmitting" />
                        <p v-if="errors.address_line_1" class="text-sm text-destructive">
                            {{ errors.address_line_1 }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pd_address_line_2">Address line 2</Label>
                        <Input id="pd_address_line_2" v-model="form.address_line_2" :disabled="isSubmitting" />
                        <p v-if="errors.address_line_2" class="text-sm text-destructive">
                            {{ errors.address_line_2 }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="pd_city">Town / city</Label>
                            <Input id="pd_city" v-model="form.city" :disabled="isSubmitting" />
                            <p v-if="errors.city" class="text-sm text-destructive">{{ errors.city }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pd_postcode">Postcode</Label>
                            <Input
                                id="pd_postcode"
                                v-model="form.postcode"
                                placeholder="e.g., TS7 1AB"
                                class="uppercase"
                                :disabled="isSubmitting"
                            />
                            <p v-if="errors.postcode" class="text-sm text-destructive">{{ errors.postcode }}</p>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="pd_booked_by_someone_else"
                            :model-value="!form.owns_account"
                            :disabled="isSubmitting"
                            @update:model-value="(value) => (form.owns_account = !value)"
                        />
                        <Label for="pd_booked_by_someone_else" class="cursor-pointer text-sm">
                            Booked by someone else (e.g., a parent or guardian)
                        </Label>
                    </div>

                    <template v-if="!form.owns_account">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="pd_contact_first_name">Booker first name</Label>
                                <Input
                                    id="pd_contact_first_name"
                                    v-model="form.contact_first_name"
                                    :disabled="isSubmitting"
                                />
                                <p v-if="errors.contact_first_name" class="text-sm text-destructive">
                                    {{ errors.contact_first_name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="pd_contact_surname">Booker surname</Label>
                                <Input
                                    id="pd_contact_surname"
                                    v-model="form.contact_surname"
                                    :disabled="isSubmitting"
                                />
                                <p v-if="errors.contact_surname" class="text-sm text-destructive">
                                    {{ errors.contact_surname }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="pd_contact_email">Booker email</Label>
                            <Input
                                id="pd_contact_email"
                                v-model="form.contact_email"
                                type="email"
                                :disabled="isSubmitting"
                            />
                            <p v-if="errors.contact_email" class="text-sm text-destructive">
                                {{ errors.contact_email }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pd_contact_phone">Booker phone</Label>
                            <Input
                                id="pd_contact_phone"
                                v-model="form.contact_phone"
                                type="tel"
                                :disabled="isSubmitting"
                            />
                            <p v-if="errors.contact_phone" class="text-sm text-destructive">
                                {{ errors.contact_phone }}
                            </p>
                        </div>
                    </template>

                    <div class="flex justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isSheetOpen = false"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isSubmitting" class="min-w-[140px]">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            Save Changes
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </Card>
</template>
