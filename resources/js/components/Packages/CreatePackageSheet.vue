<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { toast } from '@/components/ui/sonner'
import { PackagePlus } from 'lucide-vue-next'
import PackageForm from '@/components/Instructors/PackageForm.vue'
import type { PackageFormData } from '@/components/Instructors/PackageForm.vue'

interface Props {
    open: boolean
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'package-created'): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()

const saving = ref(false)

const handleSave = async (data: PackageFormData) => {
    saving.value = true

    try {
        await axios.post('/packages', data)
        toast.success('Admin package created successfully!')
        emit('package-created')
        emit('update:open', false)
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to create package'
        toast.error(message)
    } finally {
        saving.value = false
    }
}

const handleOpenChange = (value: boolean) => {
    if (!saving.value) {
        emit('update:open', value)
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <PackagePlus class="h-5 w-5" />
                    Create Admin Package
                </SheetTitle>
                <SheetDescription>
                    Create a new admin-level package. This package will be
                    unassigned and available globally.
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 px-6 py-4">
                <PackageForm
                    :saving="saving"
                    @save="handleSave"
                    @cancel="handleOpenChange(false)"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
