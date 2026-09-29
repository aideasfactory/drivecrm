<script setup lang="ts">
import { computed } from 'vue'
import { Separator } from '@/components/ui/separator'

export interface PackagePricing {
    lessons_count: number
    formatted_total_price: string
    formatted_lesson_price: string
    booking_fee: string
    digital_fee: string
    total_price: string
    weekly_payment: string
}

const props = defineProps<{
    pkg: PackagePricing
}>()

const isZero = (formatted: string) => formatted === '£0.00'

const showBookingFee = computed(() => !isZero(props.pkg.booking_fee))
const showDigitalFee = computed(() => !isZero(props.pkg.digital_fee))
</script>

<template>
    <div class="mt-2 space-y-1 text-muted-foreground">
        <div class="flex items-center justify-between gap-4">
            <span>{{ pkg.lessons_count }} lessons ({{ pkg.formatted_lesson_price }}/lesson)</span>
            <span>{{ pkg.formatted_total_price }}</span>
        </div>
        <div v-if="showBookingFee" class="flex items-center justify-between gap-4">
            <span>Booking fee</span>
            <span>{{ pkg.booking_fee }}</span>
        </div>
        <div v-if="showDigitalFee" class="flex items-center justify-between gap-4">
            <span>Digital fee</span>
            <span>{{ pkg.digital_fee }}</span>
        </div>
        <Separator class="my-2" />
        <div class="flex items-center justify-between gap-4 font-medium text-foreground">
            <span>Pupil pays</span>
            <span>{{ pkg.total_price }}</span>
        </div>
        <p class="text-xs">Or {{ pkg.weekly_payment }} per lesson if paid weekly</p>
    </div>
</template>
