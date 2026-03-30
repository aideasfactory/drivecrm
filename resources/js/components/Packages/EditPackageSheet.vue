<script setup lang="ts">
import { ref, watch } from 'vue'
import axios from 'axios'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { toast } from '@/components/ui/sonner'
import { Pencil } from 'lucide-vue-next'
import PackageForm from '@/components/Instructors/PackageForm.vue'
import type { PackageFormData } from '@/components/Instructors/PackageForm.vue'
import type { Package } from '@/types/instructor'

interface Props {
    open: boolean
    package: Package | null
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'package-updated'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const saving = ref(false)

const handleSave = async (data: PackageFormData) => {
    if (!props.package) return

    saving.value = true

    try {
        await axios.put(`/packages/${props.package.id}`, data)
        toast.success('Package updated successfully!')
        emit('package-updated')
        emit('update:open', false)
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to update package'
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
                    <Pencil class="h-5 w-5" />
                    Edit Package
                </SheetTitle>
                <SheetDescription>
                    Update package details.
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 px-6 py-4">
                <PackageForm
                    :package="package"
                    :saving="saving"
                    @save="handleSave"
                    @cancel="handleOpenChange(false)"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
