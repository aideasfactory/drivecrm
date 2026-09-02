<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { BellRing, Clock, Loader2, Send } from 'lucide-vue-next'
import { toast } from '@/components/ui/toast'

interface PackageOption {
    id: number
    name: string
    description: string | null
    total_price_pence: number
    lessons_count: number
    formatted_total_price: string
    formatted_lesson_price: string
    active: boolean
    is_one_off?: boolean
}

interface SlotPreset {
    id: number
    date: string
    start_time: string
    end_time: string
}

const props = defineProps<{
    instructorId: number
    slot: SlotPreset | null
    open: boolean
}>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    offered: []
}>()

const packages = ref<PackageOption[]>([])
const isLoadingPackages = ref(false)
const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})

const message = ref('')
const selectedPackageId = ref<number | null>(null)
const oneOffPricePounds = ref<number | null>(null)

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
})

const usingOneOffPrice = computed(() => oneOffPricePounds.value !== null && oneOffPricePounds.value > 0)

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

        message.value = ''
        selectedPackageId.value = null
        oneOffPricePounds.value = null
        errors.value = {}
        loadPackages()
    },
)

watch(oneOffPricePounds, (value) => {
    if (value !== null && value > 0) {
        selectedPackageId.value = null
    }
})

watch(selectedPackageId, (value) => {
    if (value) {
        oneOffPricePounds.value = null
    }
})

const handleSubmit = async () => {
    errors.value = {}

    if (!props.slot) {
        toast({ title: 'No diary slot selected', variant: 'destructive' })
        return
    }

    if (!selectedPackageId.value && !usingOneOffPrice.value) {
        errors.value.package_id = 'Select a package or enter a one-off price.'
        return
    }

    isSubmitting.value = true

    try {
        const payload: Record<string, unknown> = {
            message: message.value.trim() || null,
        }

        if (usingOneOffPrice.value) {
            payload.one_off_price_pence = Math.round((oneOffPricePounds.value || 0) * 100)
        } else {
            payload.package_id = selectedPackageId.value
        }

        const response = await axios.post(
            `/instructors/${props.instructorId}/calendar/items/${props.slot.id}/offers`,
            payload,
        )

        const notified = response.data.notified_count ?? 0
        toast({
            title: notified > 0
                ? `Offer sent. ${notified} student${notified === 1 ? '' : 's'} notified.`
                : 'Offer created. Students with the app will see it as Short Notice Lesson Available.',
        })
        isOpen.value = false
        emit('offered')
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = Object.fromEntries(
                Object.entries(error.response.data.errors).map(
                    ([key, val]) => [key, (val as string[])[0]],
                ),
            )
        } else {
            toast({
                title: error.response?.data?.message || 'Failed to offer this slot',
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
                    <BellRing class="h-5 w-5" />
                    Offer Slot
                </SheetTitle>
                <SheetDescription>
                    Offer this available lesson to the instructor's students at short notice.
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
                    <Label for="offer-message">Message</Label>
                    <Textarea
                        id="offer-message"
                        v-model="message"
                        :disabled="isSubmitting"
                        maxlength="1000"
                        placeholder="Add a short message for students (optional)"
                    />
                    <p class="text-xs text-muted-foreground">{{ message.length }}/1000 characters</p>
                    <p v-if="errors.message" class="text-sm text-destructive">{{ errors.message }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="offer-package">Existing package</Label>
                    <select
                        id="offer-package"
                        v-model="selectedPackageId"
                        :disabled="isSubmitting || isLoadingPackages || usingOneOffPrice"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <option :value="null">Select a package...</option>
                        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                            {{ pkg.name }} — {{ pkg.formatted_total_price }}
                            {{ pkg.is_one_off ? '(one-off)' : '' }}
                        </option>
                    </select>
                    <p v-if="errors.package_id" class="text-sm text-destructive">{{ errors.package_id }}</p>
                </div>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t border-border" />
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-background px-2 text-muted-foreground">or</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="offer-one-off">One-off price (£)</Label>
                    <Input
                        id="offer-one-off"
                        v-model.number="oneOffPricePounds"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="e.g. 45.00"
                        :disabled="isSubmitting || !!selectedPackageId"
                    />
                    <p class="text-xs text-muted-foreground">
                        Creates a reusable One-Off Package at this price for future offers.
                    </p>
                    <p v-if="errors.one_off_price_pence" class="text-sm text-destructive">
                        {{ errors.one_off_price_pence }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" :disabled="isSubmitting" @click="isOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-[140px]">
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Send Offer
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
