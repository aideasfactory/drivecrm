<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
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
import { store } from '@/routes/discount-codes'
import { Percent, PoundSterling, Loader2, Save, Tag } from 'lucide-vue-next'

interface Props {
    open: boolean
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'discount-code-created'): void
}

type DiscountType = 'percentage' | 'amount'

defineProps<Props>()
const emit = defineEmits<Emits>()

const saving = ref(false)
const form = reactive({
    code: '',
    label: '',
    type: 'amount' as DiscountType,
    percentage: '',
    amount: '',
})
const errors = reactive<Record<string, string>>({})

const valueEntered = computed(() =>
    form.type === 'percentage' ? form.percentage !== '' : form.amount !== '',
)

const canSave = computed(
    () => !saving.value && form.code.trim() !== '' && form.label.trim() !== '' && valueEntered.value,
)

const clearErrors = () => {
    Object.keys(errors).forEach((key) => delete errors[key])
}

const resetForm = () => {
    form.code = ''
    form.label = ''
    form.type = 'amount'
    form.percentage = ''
    form.amount = ''
    clearErrors()
}

const handleSave = async () => {
    saving.value = true
    clearErrors()

    try {
        await axios.post(store.url(), {
            code: form.code.trim().toUpperCase(),
            label: form.label,
            type: form.type,
            percentage: form.type === 'percentage' ? parseInt(form.percentage) : null,
            amount: form.type === 'amount' ? form.amount : null,
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
        <SheetContent side="right" class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Tag class="h-5 w-5" />
                    Create Discount Code
                </SheetTitle>
                <SheetDescription>
                    Learners type the code at the last step of the booking form. The discount comes off the
                    package price (never the booking or digital fee). Codes stay live until you deactivate them.
                </SheetDescription>
            </SheetHeader>

            <form
                @submit.prevent="handleSave"
                class="mt-6 space-y-6 px-6 py-4"
            >
                <div class="space-y-2">
                    <Label for="code">Code</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        type="text"
                        placeholder="e.g. FEEDBACK10"
                        autocomplete="off"
                        maxlength="32"
                        class="uppercase"
                    />
                    <p class="text-xs text-muted-foreground">
                        Letters, numbers, dashes and underscores. Not case sensitive for learners.
                    </p>
                    <p v-if="errors.code" class="text-sm text-destructive">
                        {{ errors.code }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="label">Label</Label>
                    <Input
                        id="label"
                        v-model="form.label"
                        type="text"
                        placeholder="e.g. Failed conversion survey £10 off"
                    />
                    <p v-if="errors.label" class="text-sm text-destructive">
                        {{ errors.label }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Discount type</Label>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :variant="form.type === 'amount' ? 'default' : 'outline'"
                            class="flex-1 cursor-pointer"
                            @click="form.type = 'amount'"
                        >
                            <PoundSterling class="mr-2 h-4 w-4" />
                            Fixed amount
                        </Button>
                        <Button
                            type="button"
                            :variant="form.type === 'percentage' ? 'default' : 'outline'"
                            class="flex-1 cursor-pointer"
                            @click="form.type = 'percentage'"
                        >
                            <Percent class="mr-2 h-4 w-4" />
                            Percentage
                        </Button>
                    </div>
                    <p v-if="errors.type" class="text-sm text-destructive">
                        {{ errors.type }}
                    </p>
                </div>

                <div v-if="form.type === 'amount'" class="space-y-2">
                    <Label for="amount">Amount off</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                        >
                            £
                        </span>
                        <Input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="e.g. 10"
                            class="pl-7"
                        />
                    </div>
                    <p v-if="errors.amount" class="text-sm text-destructive">
                        {{ errors.amount }}
                    </p>
                </div>

                <div v-else class="space-y-2">
                    <Label for="percentage">Percentage off</Label>
                    <div class="relative">
                        <Input
                            id="percentage"
                            v-model="form.percentage"
                            type="number"
                            min="1"
                            max="100"
                            step="1"
                            inputmode="numeric"
                            placeholder="e.g. 10"
                            class="pr-8"
                        />
                        <span
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                        >
                            %
                        </span>
                    </div>
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
                        :disabled="!canSave"
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
