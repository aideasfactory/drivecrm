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
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { toast } from '@/components/ui/sonner'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Upload, Loader2, FileUp, CheckCircle, AlertCircle, XCircle } from 'lucide-vue-next'

interface ImportError {
    row: number
    field: string | null
    message: string
}

interface ImportResult {
    message: string
    imported: number
    skipped: number
    errors: ImportError[]
}

interface Props {
    open: boolean
    title: string
    description: string
    importUrl: string
    extraFormData?: Record<string, string | number>
}

interface Emits {
    (e: 'update:open', value: boolean): void
    (e: 'imported'): void
}

const props = withDefaults(defineProps<Props>(), {
    extraFormData: () => ({}),
})
const emit = defineEmits<Emits>()

const isUploading = ref(false)
const selectedFile = ref<File | null>(null)
const result = ref<ImportResult | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0]
        result.value = null
    }
}

const handleImport = async () => {
    if (!selectedFile.value) return

    isUploading.value = true
    result.value = null

    const formData = new FormData()
    formData.append('file', selectedFile.value)

    // Append any extra form data (e.g., resource_folder_id)
    if (props.extraFormData) {
        Object.entries(props.extraFormData).forEach(([key, value]) => {
            formData.append(key, String(value))
        })
    }

    try {
        const response = await axios.post(props.importUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        result.value = response.data

        if (response.data.imported > 0) {
            toast.success(response.data.message)
            emit('imported')
        }

        if (response.data.imported === 0 && response.data.skipped > 0) {
            toast.error('No records were imported. Check the errors below.')
        }
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to import CSV.'
        toast.error(message)

        if (error.response?.data?.errors) {
            result.value = {
                message,
                imported: 0,
                skipped: 0,
                errors: Array.isArray(error.response.data.errors)
                    ? error.response.data.errors
                    : Object.values(error.response.data.errors).flat().map((msg: string) => ({
                          row: 0,
                          field: null,
                          message: msg,
                      })),
            }
        }
    } finally {
        isUploading.value = false
    }
}

const handleOpenChange = (value: boolean) => {
    if (!isUploading.value) {
        emit('update:open', value)
        if (!value) {
            selectedFile.value = null
            result.value = null
            if (fileInput.value) {
                fileInput.value.value = ''
            }
        }
    }
}
</script>

<template>
    <Sheet :open="props.open" @update:open="handleOpenChange">
        <SheetContent class="overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <FileUp class="h-5 w-5" />
                    {{ title }}
                </SheetTitle>
                <SheetDescription>
                    {{ description }}
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 space-y-6 px-6 py-4">
                <!-- File Upload -->
                <div class="space-y-2">
                    <Label for="csv-file">CSV File</Label>
                    <input
                        id="csv-file"
                        ref="fileInput"
                        type="file"
                        accept=".csv"
                        :disabled="isUploading"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        @change="handleFileSelect"
                    />
                    <p class="text-xs text-muted-foreground">
                        Upload a CSV file with the same headers as the template. Max 5MB.
                    </p>
                </div>

                <!-- Upload Button -->
                <div class="flex justify-end">
                    <Button
                        :disabled="!selectedFile || isUploading"
                        class="min-w-[140px]"
                        @click="handleImport"
                    >
                        <Loader2 v-if="isUploading" class="mr-2 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        {{ isUploading ? 'Importing...' : 'Import CSV' }}
                    </Button>
                </div>

                <!-- Results -->
                <div v-if="result" class="space-y-4">
                    <!-- Summary -->
                    <div class="rounded-lg border p-4">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2 text-sm">
                                <CheckCircle class="h-4 w-4 text-green-600" />
                                <span>
                                    <strong>{{ result.imported }}</strong> imported
                                </span>
                            </div>
                            <div v-if="result.skipped > 0" class="flex items-center gap-2 text-sm">
                                <XCircle class="h-4 w-4 text-destructive" />
                                <span>
                                    <strong>{{ result.skipped }}</strong> skipped
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Error Details -->
                    <div v-if="result.errors.length > 0" class="space-y-2">
                        <h4 class="flex items-center gap-2 text-sm font-semibold">
                            <AlertCircle class="h-4 w-4 text-destructive" />
                            Errors
                        </h4>
                        <div class="max-h-64 overflow-y-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-16">Row</TableHead>
                                        <TableHead>Error</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(err, idx) in result.errors"
                                        :key="idx"
                                    >
                                        <TableCell class="font-mono text-xs">
                                            {{ err.row || '—' }}
                                        </TableCell>
                                        <TableCell class="text-sm">
                                            {{ err.message }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
