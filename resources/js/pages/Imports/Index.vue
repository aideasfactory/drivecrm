<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
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
import { check, store, template } from '@/routes/imports'
import {
    AlertCircle,
    CheckCircle2,
    DatabaseZap,
    Download,
    FileSearch,
    Loader2,
    Mail,
    Upload,
    X,
} from 'lucide-vue-next'

interface BundleError {
    file: string
    line: number
    message: string
}

interface CheckResult {
    valid: boolean
    row_counts: Record<string, number>
    errors: BundleError[]
}

const FILE_LABELS: Record<string, string> = {
    instructors: 'instructors.csv',
    coverage: 'coverage.csv',
    students: 'students.csv',
    diary: 'diary.csv',
    finances: 'finances.csv',
    receipts: 'Receipt files (in finances.csv)',
}

const TOTAL_LABELS: Record<string, string> = {
    instructors_created: 'Instructors created',
    instructors_linked: 'Existing instructors linked',
    locations: 'Coverage sectors added',
    students: 'Students created',
    lessons: 'Diary lessons',
    diary_blocks: 'Unavailable diary blocks',
    finances: 'Finance entries',
    receipts: 'Receipts attached',
}

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const checking = ref(false)
const importing = ref(false)
const checkResult = ref<CheckResult | null>(null)
const importTotals = ref<Record<string, number> | null>(null)
const confirmOpen = ref(false)

const canImport = computed(
    () => checkResult.value?.valid === true && !importing.value && importTotals.value === null,
)

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    selectedFile.value = target.files?.[0] ?? null
    checkResult.value = null
    importTotals.value = null
}

const buildFormData = (): FormData => {
    const formData = new FormData()
    formData.append('file', selectedFile.value as File)
    return formData
}

const errorMessage = (error: any, fallback: string): string => {
    const errors = error.response?.data?.errors
    if (errors && !Array.isArray(errors)) {
        return (Object.values(errors).flat()[0] as string) ?? fallback
    }
    return error.response?.data?.message ?? fallback
}

const handleCheck = async () => {
    if (!selectedFile.value) return

    checking.value = true
    checkResult.value = null
    importTotals.value = null

    try {
        const response = await axios.post(check.url(), buildFormData())
        checkResult.value = response.data

        if (response.data.valid) {
            toast.success('File checked — ready to import.')
        } else {
            toast.error(`Found ${response.data.errors.length} problem(s). Nothing has been imported.`)
        }
    } catch (error: any) {
        toast.error(errorMessage(error, 'Could not check the file.'))
    } finally {
        checking.value = false
    }
}

const handleImport = async () => {
    if (!selectedFile.value) return

    importing.value = true

    try {
        const response = await axios.post(store.url(), buildFormData())
        importTotals.value = response.data.totals
        confirmOpen.value = false
        toast.success(response.data.message)
    } catch (error: any) {
        confirmOpen.value = false

        if (Array.isArray(error.response?.data?.errors)) {
            checkResult.value = {
                valid: false,
                row_counts: error.response.data.row_counts ?? {},
                errors: error.response.data.errors,
            }
        }

        toast.error(errorMessage(error, 'The import failed. Nothing was imported.'))
    } finally {
        importing.value = false
    }
}

const resetUpload = () => {
    selectedFile.value = null
    checkResult.value = null
    importTotals.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const breadcrumbs = [{ title: 'Data Import' }]
</script>

<template>
    <Head title="Data Import" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="flex items-center gap-3 text-3xl font-bold">
                    <DatabaseZap class="h-8 w-8" />
                    Data Import
                </h2>
                <p class="max-w-2xl text-muted-foreground">
                    Bring instructors, their students, coverage areas, diary and
                    finances across from another system. Imported lessons can be
                    signed off as normal but are never charged or paid out through
                    Stripe. No emails are sent during an import.
                </p>
            </div>

            <!-- Step 1: template -->
            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>1. Get the template</CardTitle>
                    <CardDescription>
                        A zip with the export spec (README) and an example of each CSV.
                        Send it to whoever is exporting the data — the example files
                        link together, so the zip is itself a valid import.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button variant="outline" as="a" :href="template.url()">
                        <Download class="mr-2 h-4 w-4" />
                        Download template
                    </Button>
                </CardContent>
            </Card>

            <!-- Step 2: upload + check -->
            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>2. Upload and check</CardTitle>
                    <CardDescription>
                        Upload the zip of CSVs. It's checked first — if any row has a
                        problem, nothing is imported and every problem is listed below.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="import-file">Import zip</Label>
                        <input
                            id="import-file"
                            ref="fileInput"
                            type="file"
                            accept=".zip"
                            :disabled="checking || importing"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            @change="handleFileSelect"
                        />
                        <p class="text-xs text-muted-foreground">
                            Max 100MB. Put receipts in a <code class="font-mono">receipts/</code> folder in the zip.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            :disabled="!selectedFile || checking || importing"
                            class="min-w-[140px]"
                            @click="handleCheck"
                        >
                            <Loader2 v-if="checking" class="mr-2 h-4 w-4 animate-spin" />
                            <FileSearch v-else class="mr-2 h-4 w-4" />
                            {{ checking ? 'Checking...' : 'Check file' }}
                        </Button>
                        <Button
                            v-if="checkResult?.valid"
                            :disabled="!canImport"
                            class="min-w-[140px]"
                            @click="confirmOpen = true"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            Import
                        </Button>
                    </div>

                    <!-- Row counts -->
                    <div v-if="checkResult" class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    <TableHead class="text-right">Rows</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(label, key) in FILE_LABELS" :key="key">
                                    <TableCell class="font-mono text-sm">{{ label }}</TableCell>
                                    <TableCell class="text-right">
                                        {{ checkResult.row_counts[key] ?? 0 }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <Alert v-if="checkResult?.valid && !importTotals">
                        <CheckCircle2 class="h-4 w-4" />
                        <AlertTitle>Ready to import</AlertTitle>
                        <AlertDescription>
                            Every row passed. Rows already imported on an earlier run
                            will be skipped, not duplicated.
                        </AlertDescription>
                    </Alert>

                    <!-- Errors -->
                    <div v-if="checkResult && !checkResult.valid" class="flex flex-col gap-2">
                        <Alert variant="destructive">
                            <AlertCircle class="h-4 w-4" />
                            <AlertTitle>{{ checkResult.errors.length }} problem(s) found</AlertTitle>
                            <AlertDescription>
                                Nothing has been imported. Fix these in the export and
                                upload again.
                            </AlertDescription>
                        </Alert>
                        <div class="max-h-96 overflow-y-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-36">File</TableHead>
                                        <TableHead class="w-16">Line</TableHead>
                                        <TableHead>Problem</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(problem, index) in checkResult.errors"
                                        :key="index"
                                    >
                                        <TableCell class="font-mono text-xs">{{ problem.file }}</TableCell>
                                        <TableCell class="font-mono text-xs">{{ problem.line || '—' }}</TableCell>
                                        <TableCell class="text-sm">{{ problem.message }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 3: result -->
            <Card v-if="importTotals" class="max-w-3xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CheckCircle2 class="h-5 w-5" />
                        Import complete
                    </CardTitle>
                    <CardDescription>
                        Counts are rows newly created by this upload.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="rounded-lg border">
                        <Table>
                            <TableBody>
                                <TableRow v-for="(label, key) in TOTAL_LABELS" :key="key">
                                    <TableCell>{{ label }}</TableCell>
                                    <TableCell class="text-right font-semibold">
                                        {{ importTotals[key] ?? 0 }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <Alert>
                        <Mail class="h-4 w-4" />
                        <AlertTitle>Welcome emails are on hold</AlertTitle>
                        <AlertDescription>
                            Imported instructors and students have accounts but haven't
                            been emailed. Send them when you're ready to go live with
                            <code class="font-mono text-xs">php artisan import:send-welcome-emails</code>.
                        </AlertDescription>
                    </Alert>

                    <div>
                        <Button variant="outline" @click="resetUpload">
                            <Upload class="mr-2 h-4 w-4" />
                            Import another file
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Confirm import -->
        <Dialog :open="confirmOpen" @update:open="(value) => !importing && (confirmOpen = value)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Upload class="h-5 w-5" />
                        Import this file?
                    </DialogTitle>
                    <DialogDescription>
                        This creates real instructor and student accounts, diary
                        entries and finance records. It can't be undone from here.
                        No emails will be sent.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" :disabled="importing" @click="confirmOpen = false">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button :disabled="importing" class="min-w-[140px]" @click="handleImport">
                        <Loader2 v-if="importing" class="mr-2 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        {{ importing ? 'Importing...' : 'Import' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
