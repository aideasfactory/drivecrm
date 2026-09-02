<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { CalendarPlus, Clock, Loader2, Send } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'

interface PackageOption {
    id: number
    name: string
    description: string | null
    total_price_pence: number
    lessons_count: number
    lesson_price_pence: number
    formatted_total_price: string
    formatted_lesson_price: string
    active: boolean
}

interface StudentOption {
    id: number
    name: string
}

interface SlotPreset {
    id: number
    date: string
    start_time: string
    end_time: string
}

const props = defineProps<{
    instructorId: number
    students: StudentOption[]
    slot: SlotPreset | null
    open: boolean
}>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    booked: []
}>()

const packages = ref<PackageOption[]>([])
const isLoadingPackages = ref(false)
const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})

const selectedStudentId = ref<number | null>(null)
const selectedPackageId = ref<number | null>(null)
const paymentMode = ref('upfront')

const selectedPackage = computed(() =>
    packages.value.find((pkg) => pkg.id === selectedPackageId.value),
)

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
})

const loadPackages = async () => {
    isLoadingPackages.value = true
    try {
        const response = await axios.get(`/instructors/${props.instructorId}/packages`)
        packages.value = (response.data.packages || []).filter((pkg: PackageOption) => pkg.active)
    } catch {
        toast({ title: 'Failed to load packages', variant: 'destructive' })
    } finally {
        isLoadingPackages.value = false
    }
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return
        }

        selectedStudentId.value = null
        selectedPackageId.value = null
        paymentMode.value = 'upfront'
        errors.value = {}
        loadPackages()
    },
)

const handleSubmit = async () => {
    errors.value = {}

    if (!props.slot) {
        toast({ title: 'No diary slot selected', variant: 'destructive' })
        return
    }

    if (!selectedStudentId.value) {
        errors.value.student_id = 'Please select a student.'
        return
    }

    if (!selectedPackageId.value) {
        errors.value.package_id = 'Please select a package.'
        return
    }

    isSubmitting.value = true

    try {
        const response = await axios.post(`/students/${selectedStudentId.value}/orders`, {
            package_id: selectedPackageId.value,
            payment_mode: paymentMode.value,
            calendar_item_id: props.slot.id,
        })
        toast({ title: response.data.message || 'Booking created. The student has been emailed.' })
        isOpen.value = false
        emit('booked')
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            )
        } else {
            toast({
                title: error.response?.data?.message || 'Failed to create booking',
                variant: 'destructive',
            })
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <CalendarPlus class="h-5 w-5" />
                    Add Booking
                </SheetTitle>
                <SheetDescription>
                    Book this diary slot for a student. The date and time come from the slot you clicked.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">
                <div class="rounded-md bg-muted p-3 text-sm">
                    <p class="flex items-center gap-2 font-medium">
                        <Clock class="h-4 w-4" />
                        {{ slot?.date }} · {{ slot?.start_time }} – {{ slot?.end_time }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="diary-booking-student">Student *</Label>
                    <select
                        id="diary-booking-student"
                        v-model="selectedStudentId"
                        :disabled="isSubmitting"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option :value="null" disabled>Select a student...</option>
                        <option v-for="student in students" :key="student.id" :value="student.id">
                            {{ student.name }}
                        </option>
                    </select>
                    <p v-if="errors.student_id" class="text-sm text-destructive">{{ errors.student_id }}</p>
                    <p v-if="students.length === 0" class="text-xs text-muted-foreground">
                        No students found for this instructor.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="diary-booking-package">Package *</Label>
                    <select
                        id="diary-booking-package"
                        v-model="selectedPackageId"
                        :disabled="isSubmitting || isLoadingPackages"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option :value="null" disabled>
                            {{ isLoadingPackages ? 'Loading packages...' : 'Select a package...' }}
                        </option>
                        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                            {{ pkg.name }} — {{ pkg.formatted_total_price }} ({{ pkg.lessons_count }} lessons)
                        </option>
                    </select>
                    <p v-if="errors.package_id" class="text-sm text-destructive">{{ errors.package_id }}</p>
                    <div v-if="selectedPackage" class="rounded-md bg-muted p-3 text-sm">
                        <p class="font-medium">{{ selectedPackage.name }}</p>
                        <p v-if="selectedPackage.description" class="mt-1 text-muted-foreground">
                            {{ selectedPackage.description }}
                        </p>
                        <div class="mt-2 flex gap-4 text-muted-foreground">
                            <span>{{ selectedPackage.lessons_count }} lessons</span>
                            <span>{{ selectedPackage.formatted_lesson_price }}/lesson</span>
                            <span class="font-medium text-foreground">{{ selectedPackage.formatted_total_price }} total</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="diary-booking-payment">Payment preference *</Label>
                    <select
                        id="diary-booking-payment"
                        v-model="paymentMode"
                        :disabled="isSubmitting"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option value="upfront">Pay all at once — student receives a Stripe payment link</option>
                        <option value="weekly">Pay weekly — invoices sent before each lesson</option>
                    </select>
                    <p v-if="errors.payment_mode" class="text-sm text-destructive">{{ errors.payment_mode }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" :disabled="isSubmitting" @click="isOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-[140px]">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Create Booking
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
