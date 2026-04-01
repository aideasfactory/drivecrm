<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { CalendarPlus, Clock, Loader2, Send } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'

interface Package {
    id: number
    name: string
    description: string | null
    total_price_pence: number
    lessons_count: number
    lesson_price_pence: number
    formatted_total_price: string
    formatted_lesson_price: string
    active: boolean
    is_platform_package: boolean
    is_bespoke_package: boolean
}

interface Slot {
    id: number
    start_time: string
    end_time: string
}

const props = withDefaults(defineProps<{
    studentId: number
    instructorId: number
    headerMode?: boolean
}>(), {
    headerMode: false,
})

const packages = ref<Package[]>([])
const slots = ref<Slot[]>([])
const isLoadingPackages = ref(true)
const isLoadingSlots = ref(false)
const isSheetOpen = ref(false)
const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})

const selectedPackageId = ref<number | null>(null)
const paymentMode = ref('upfront')
const firstLessonDate = ref('')
const selectedSlotId = ref<number | null>(null)

const selectedPackage = computed(() =>
    packages.value.find((p) => p.id === selectedPackageId.value),
)

const selectedSlot = computed(() =>
    slots.value.find((s) => s.id === selectedSlotId.value),
)

const loadPackages = async () => {
    isLoadingPackages.value = true
    try {
        const response = await axios.get(
            `/instructors/${props.instructorId}/packages`,
        )
        packages.value = (response.data.packages || []).filter(
            (p: Package) => p.active,
        )
    } catch {
        toast({ title: 'Failed to load packages', variant: 'destructive' })
    } finally {
        isLoadingPackages.value = false
    }
}

const loadSlots = async (date: string) => {
    if (!date) {
        slots.value = []
        return
    }

    isLoadingSlots.value = true
    selectedSlotId.value = null

    try {
        const response = await axios.get(
            `/students/${props.studentId}/available-slots`,
            { params: { date } },
        )
        slots.value = response.data.slots || []
    } catch {
        toast({ title: 'Failed to load available slots', variant: 'destructive' })
        slots.value = []
    } finally {
        isLoadingSlots.value = false
    }
}

watch(firstLessonDate, (date) => {
    loadSlots(date)
})

onMounted(() => {
    loadPackages()
})

const openSheet = () => {
    selectedPackageId.value = null
    paymentMode.value = 'upfront'
    firstLessonDate.value = ''
    selectedSlotId.value = null
    slots.value = []
    errors.value = {}
    isSheetOpen.value = true
}

const handleSubmit = async () => {
    errors.value = {}

    if (!selectedSlot.value) {
        errors.value.start_time = 'Please select an available slot.'
        return
    }

    isSubmitting.value = true

    try {
        const response = await axios.post(
            `/students/${props.studentId}/orders`,
            {
                package_id: selectedPackageId.value,
                payment_mode: paymentMode.value,
                first_lesson_date: firstLessonDate.value,
                start_time: selectedSlot.value.start_time,
                end_time: selectedSlot.value.end_time,
            },
        )
        toast({ title: response.data.message })
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
                error.response?.data?.message || 'Failed to book lessons'
            toast({ title: message, variant: 'destructive' })
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div>
        <!-- Header mode: just the button -->
        <template v-if="headerMode">
            <Button
                v-if="!isLoadingPackages && packages.length > 0"
                @click="openSheet"
                class="min-w-[140px]"
            >
                <CalendarPlus class="mr-2 h-4 w-4" />
                Book Lessons
            </Button>
        </template>

        <!-- Full mode: heading + description + button -->
        <template v-else>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="flex items-center gap-2 text-lg font-semibold">
                        <CalendarPlus class="h-5 w-5" />
                        Book Lessons
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Book a lesson package on behalf of this student
                    </p>
                </div>
            </div>

            <div v-if="isLoadingPackages" class="space-y-3">
                <Skeleton class="h-10 w-full" />
            </div>

            <div v-else-if="packages.length === 0" class="text-sm text-muted-foreground">
                No active packages available for this instructor.
            </div>

            <div v-else>
                <Button @click="openSheet" class="min-w-[140px]">
                    <CalendarPlus class="mr-2 h-4 w-4" />
                    Book Lessons
                </Button>
            </div>
        </template>

        <!-- Book Lesson Sheet -->
        <Sheet v-model:open="isSheetOpen">
            <SheetContent side="right" class="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <CalendarPlus class="h-5 w-5" />
                        Book Lessons
                    </SheetTitle>
                    <SheetDescription>
                        Select a package, date, and available time slot. The student will receive an email to complete payment.
                    </SheetDescription>
                </SheetHeader>

                <form
                    @submit.prevent="handleSubmit"
                    class="mt-6 space-y-6 px-6 py-4"
                >
                    <!-- Package Selection -->
                    <div class="space-y-2">
                        <Label for="bl_package">Package *</Label>
                        <select
                            id="bl_package"
                            v-model="selectedPackageId"
                            :disabled="isSubmitting"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option :value="null" disabled>
                                Select a package...
                            </option>
                            <option
                                v-for="pkg in packages"
                                :key="pkg.id"
                                :value="pkg.id"
                            >
                                {{ pkg.name }} — {{ pkg.formatted_total_price }} ({{ pkg.lessons_count }} lessons)
                            </option>
                        </select>
                        <p
                            v-if="errors.package_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.package_id }}
                        </p>
                        <div
                            v-if="selectedPackage"
                            class="rounded-md bg-muted p-3 text-sm"
                        >
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

                    <!-- Payment Mode -->
                    <div class="space-y-2">
                        <Label for="bl_payment_mode">Payment Mode *</Label>
                        <select
                            id="bl_payment_mode"
                            v-model="paymentMode"
                            :disabled="isSubmitting"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="upfront">
                                Upfront — Student pays in full via Stripe link
                            </option>
                            <option value="weekly">
                                Weekly — Invoices sent before each lesson
                            </option>
                        </select>
                        <p
                            v-if="errors.payment_mode"
                            class="text-sm text-destructive"
                        >
                            {{ errors.payment_mode }}
                        </p>
                    </div>

                    <!-- First Lesson Date -->
                    <div class="space-y-2">
                        <Label for="bl_date">First Lesson Date *</Label>
                        <Input
                            id="bl_date"
                            type="date"
                            v-model="firstLessonDate"
                            :disabled="isSubmitting"
                        />
                        <p
                            v-if="errors.first_lesson_date"
                            class="text-sm text-destructive"
                        >
                            {{ errors.first_lesson_date }}
                        </p>
                    </div>

                    <!-- Available Slots -->
                    <div v-if="firstLessonDate" class="space-y-2">
                        <Label>Available Slot *</Label>

                        <!-- Loading -->
                        <div v-if="isLoadingSlots" class="space-y-2">
                            <Skeleton class="h-10 w-full" />
                            <Skeleton class="h-10 w-full" />
                        </div>

                        <!-- No slots -->
                        <div
                            v-else-if="slots.length === 0"
                            class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
                        >
                            No available slots on this date. Try a different date.
                        </div>

                        <!-- Slot list -->
                        <div v-else class="grid grid-cols-2 gap-2">
                            <button
                                v-for="slot in slots"
                                :key="slot.id"
                                type="button"
                                @click="selectedSlotId = slot.id"
                                :disabled="isSubmitting"
                                :class="[
                                    'flex items-center justify-center gap-2 rounded-md border px-3 py-2.5 text-sm font-medium transition-colors',
                                    selectedSlotId === slot.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input bg-background hover:bg-accent hover:text-accent-foreground',
                                ]"
                            >
                                <Clock class="h-3.5 w-3.5" />
                                {{ slot.start_time }} – {{ slot.end_time }}
                            </button>
                        </div>

                        <p
                            v-if="errors.start_time"
                            class="text-sm text-destructive"
                        >
                            {{ errors.start_time }}
                        </p>
                    </div>

                    <!-- Submit -->
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
                            :disabled="isSubmitting || !selectedSlot"
                            class="min-w-[140px]"
                        >
                            <Loader2
                                v-if="isSubmitting"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <Send
                                v-else
                                class="mr-2 h-4 w-4"
                            />
                            Book Lessons
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
