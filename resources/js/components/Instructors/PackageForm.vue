<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Save, X, Loader2 } from 'lucide-vue-next'
import type { Package } from '@/types/instructor'

interface Props {
    package?: Package | null
    saving?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    package: null,
    saving: false,
})

const emit = defineEmits<{
    save: [data: PackageFormData]
    cancel: []
}>()

export interface PackageFormData {
    name: string
    description: string
    total_price_pence: number
    lessons_count: number
}

// Form state
const formData = ref<PackageFormData>({
    name: '',
    description: '',
    total_price_pence: 0,
    lessons_count: 1,
})

// Watch for package changes and populate form
watch(
    () => props.package,
    (pkg) => {
        if (pkg) {
            formData.value = {
                name: pkg.name,
                description: pkg.description || '',
                total_price_pence: pkg.total_price_pence,
                lessons_count: pkg.lessons_count,
            }
        } else {
            // Reset form for new package
            formData.value = {
                name: '',
                description: '',
                total_price_pence: 0,
                lessons_count: 1,
            }
        }
    },
    { immediate: true }
)

// Computed: formatted price from pence
const formattedPrice = computed(() => {
    return '£' + (formData.value.total_price_pence / 100).toFixed(2)
})

// Computed: price per lesson
const pricePerLesson = computed(() => {
    if (formData.value.lessons_count === 0) return '£0.00'
    const perLesson =
        formData.value.total_price_pence / formData.value.lessons_count
    return '£' + (perLesson / 100).toFixed(2)
})

// Handle form submission
const handleSubmit = () => {
    emit('save', formData.value)
}

// Handle cancel
const handleCancel = () => {
    emit('cancel')
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Name Field -->
        <div class="space-y-2">
            <Label for="name">Package Name</Label>
            <Input
                id="name"
                v-model="formData.name"
                placeholder="Enter package name"
                required
            />
        </div>

        <!-- Description Field -->
        <div class="space-y-2">
            <Label for="description">Description</Label>
            <textarea
                id="description"
                v-model="formData.description"
                placeholder="Enter package description"
                rows="3"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            />
        </div>

        <!-- Lessons Count Field -->
        <div class="space-y-2">
            <Label for="lessons_count">Number of Lessons</Label>
            <Input
                id="lessons_count"
                v-model.number="formData.lessons_count"
                type="number"
                min="1"
                placeholder="Enter number of lessons"
                required
            />
        </div>

        <!-- Total Price in Pence Field -->
        <div class="space-y-2">
            <Label for="total_price_pence">Total Price (in pence)</Label>
            <Input
                id="total_price_pence"
                v-model.number="formData.total_price_pence"
                type="number"
                min="0"
                step="1"
                placeholder="Enter price in pence (e.g., 50000 = £500.00)"
                required
            />
            <p class="text-sm text-muted-foreground">
                {{ formattedPrice }} total ({{ pricePerLesson }} per lesson)
            </p>
        </div>
 
        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-4">
            <Button
                type="button"
                variant="outline"
                @click="handleCancel"
                :disabled="saving"
            >
                <X class="mr-2 h-4 w-4" />
                Cancel
            </Button>
            <Button type="submit" :disabled="saving">
                <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                <Save v-else class="mr-2 h-4 w-4" />
                {{ package ? 'Save Changes' : 'Create Package' }}
            </Button>
        </div>
    </form>
</template>
