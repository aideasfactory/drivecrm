<script setup lang="ts">
import { ref, reactive } from 'vue'
import axios from 'axios'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { toast } from '@/components/ui/sonner'
import { Percent, Loader2, Save } from 'lucide-vue-next'

interface Props {
    open: boolean
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'discount-code-created'): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()

const saving = ref(false)
const form = reactive({
    label: '',
    percentage: '',
})
const errors = reactive<Record<string, string>>({})

const percentageOptions = [
    { value: '5', label: '5%' },
    { value: '10', label: '10%' },
    { value: '15', label: '15%' },
    { value: '20', label: '20%' },
]

const resetForm = () => {
    form.label = ''
    form.percentage = ''
    Object.keys(errors).forEach((key) => delete errors[key])
}

const handleSave = async () => {
    saving.value = true
    Object.keys(errors).forEach((key) => delete errors[key])

    try {
        await axios.post('/discount-codes', {
            label: form.label,
            percentage: parseInt(form.percentage),
        })
        toast.success('Discount code created successfully!')
        emit('discount-code-created')
        emit('update:open', false)
        resetForm()
    } catch (error: any) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {}
            Object.entries(validationErrors).forEach(([key, msgs]) => {
                errors[key] = (msgs as string[])[0]
            })
        } else {
            const message =
                error.response?.data?.message ||
                'Failed to create discount code'
            toast.error(message)
        }
    } finally {
        saving.value = false
    }
}

const handleOpenChange = (value: boolean) => {
    if (!saving.value) {
        emit('update:open', value)
        if (!value) {
            resetForm()
        }
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Percent class="h-5 w-5" />
                    Create Discount Code
                </SheetTitle>
                <SheetDescription>
                    Create a new discount code. A unique UUID will be generated
                    automatically. Share the onboarding URL with the discount
                    parameter to apply the discount.
                </SheetDescription>
            </SheetHeader>

            <form
                @submit.prevent="handleSave"
                class="mt-6 space-y-6 px-6 py-4"
            >
                <div class="space-y-2">
                    <Label for="label">Label</Label>
                    <Input
                        id="label"
                        v-model="form.label"
                        type="text"
                        placeholder="e.g. Summer 2026 Promo"
                    />
                    <p v-if="errors.label" class="text-sm text-destructive">
                        {{ errors.label }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="percentage">Discount Percentage</Label>
                    <select
                        id="percentage"
                        v-model="form.percentage"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    >
                        <option value="" disabled>Select discount %</option>
                        <option
                            v-for="opt in percentageOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <p
                        v-if="errors.percentage"
                        class="text-sm text-destructive"
                    >
                        {{ errors.percentage }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleOpenChange(false)"
                        :disabled="saving"
                        class="cursor-pointer"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="saving || !form.label || !form.percentage"
                        class="cursor-pointer min-w-[120px]"
                    >
                        <Loader2
                            v-if="saving"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ saving ? 'Creating...' : 'Create' }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
