<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
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
import { checkExport, store } from '@/routes/imports'
import { show as showRun } from '@/routes/imports/runs'
import type { ImportRun } from '@/types/imports'
import { AlertCircle, CheckCircle2, FileSearch, Loader2, Mail, Upload, X } from 'lucide-vue-next'


const props = defineProps<{
    latestRun: ImportRun | null
}>()

const emit = defineEmits<{
    running: [value: boolean]
}>()

const FILE_ORDER = [
    'instructors',
    'coverage',
    'vehicles',
    'students',
    'pickup_points',
    'diary',
    'tests',
    'progress',
    'reflective_logs',
    'finances',
    'payment_lessons',
    'mileage',
]

const STAGE_LABELS: Record<string, string> = {
    instructors: 'Instructors and coverage',
    students: 'Pupils, checklists and pick-up points',
    diary: 'Diary: lessons, tests and blocks',
    tests: 'Test results',
    progress: 'Progress scores',
    reflective_logs: 'Reflective logs',
    finances: 'Finances',
    mileage: 'Mileage',
    finish: 'Past lessons completed',
}

const STATUS_TEXT: Record<string, string> = {
    reading: 'Reading the zip…',
    checking: 'Checking every row…',
    importing: 'Starting the import…',
    finishing: 'Finishing off…',
}

const POLL_INTERVAL_MS = 3000

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const uploading = ref(false)
const importing = ref(false)
const confirmOpen = ref(false)
const checkRun = ref<ImportRun | null>(null)
const importRun = ref<ImportRun | null>(props.latestRun)
const error = ref<string | null>(null)
let pollTimer: ReturnType<typeof setInterval> | null = null


const isActive = (run: ImportRun | null): boolean => run?.status === 'queued' || run?.status === 'running'
const checking = computed(() => isActive(checkRun.value))
const importActive = computed(() => isActive(importRun.value))
const checkTotals = computed(() => (checkRun.value?.status === 'completed' ? checkRun.value.totals : null))
const canImport = computed(
    () => checkTotals.value?.valid === true && !!selectedFile.value && !importActive.value && !importing.value,
)

watch(importActive, (value) => emit('running', value), { immediate: true })

const fileRows = computed(() => {
    const files = checkTotals.value?.check.files ?? {}

    return FILE_ORDER.filter((key) => files[key]).map((key) => {
        const file = files[key]
        const skipped = Object.values(file.skipped ?? {}).reduce((sum, count) => sum + count, 0)

        return { key, name: `${key}.csv`, ...file, skippedTotal: skipped }
    })
})

const detailRows = computed(() =>
    fileRows.value.flatMap((file) => [
        ...Object.entries(file.skipped ?? {}).map(([reason, count]) => ({ file: file.name, kind: 'Skipped', reason, count })),
        ...Object.entries(file.notes ?? {})
            .filter(([, count]) => count > 0)
            .map(([reason, count]) => ({ file: file.name, kind: 'Note', reason, count })),
    ]),
)

const stageRows = computed(() =>
    Object.entries(importRun.value?.stage_totals ?? {}).map(([stage, totals]) => {
        const { jobs_total: jobsTotal, jobs_done: jobsDone, ...counts } = totals

        return {
            stage,
            label: STAGE_LABELS[stage] ?? stage,
            progress: jobsTotal !== undefined ? `${jobsDone ?? 0} / ${jobsTotal}` : '',
            counts: Object.entries(counts)
                .filter(([, count]) => count > 0)
                .map(([key, count]) => `${key.replace(/_/g, ' ')}: ${formatNumber(count)}`)
                .join(' · '),
        }
    }),
)

const runStatusText = (run: ImportRun | null): string => {
    if (run?.status === 'queued') {
        return 'Waiting to start (another import or check may be running)…'
    }

    return STATUS_TEXT[run?.stage ?? ''] ?? `Importing: ${STAGE_LABELS[run?.stage ?? ''] ?? '…'}`
}

const formatNumber = (value: number): string => value.toLocaleString('en-GB')

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

const fetchRun = async (run: ImportRun): Promise<ImportRun | null> => {
    try {
        const response = await axios.get(showRun.url(run.id), { headers: { Accept: 'application/json' } })
        return response.data.run as ImportRun
    } catch {
        return null
    }
}

const poll = async () => {
    if (checking.value && checkRun.value) {
        checkRun.value = (await fetchRun(checkRun.value)) ?? checkRun.value

        if (!checking.value) {
            if (checkRun.value.status === 'failed') {
                toast.error('The check stopped before it finished.')
            } else if (checkRun.value.totals?.valid) {
                toast.success('File checked — ready to import.')
            } else {
                toast.error('Problems found. Nothing was imported.')
            }
        }
    }

    if (importActive.value && importRun.value) {
        importRun.value = (await fetchRun(importRun.value)) ?? importRun.value

        if (!importActive.value) {
            if (importRun.value.status === 'completed') {
                toast.success('Import complete. No emails have been sent.')
            } else {
                toast.error('The import stopped before it finished.')
            }
        }
    }

    if (!checking.value && !importActive.value) {
        stopPolling()
    }
}

const startPolling = () => {
    stopPolling()
    pollTimer = setInterval(poll, POLL_INTERVAL_MS)
}

onMounted(() => {
    if (importActive.value) {
        startPolling()
    }
})

onUnmounted(stopPolling)

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    selectedFile.value = target.files?.[0] ?? null
    checkRun.value = null
    error.value = null

    if (!importActive.value) {
        importRun.value = null
    }
}

const upload = async (url: string): Promise<{ run: ImportRun; message: string } | null> => {
    if (!selectedFile.value) {
        error.value = 'Please choose a zip file to upload.'
        toast.error(error.value)
        return null
    }

    const formData = new FormData()
    formData.append('file', selectedFile.value)
    error.value = null

    try {
        const response = await axios.post(url, formData, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            withXSRFToken: true,
        })
        return { run: response.data.run as ImportRun, message: response.data.message as string }
    } catch (caught: unknown) {
        const data = (caught as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data
        error.value = Object.values(data?.errors ?? {}).flat()[0] ?? data?.message ?? 'Could not upload the file. Try again.'
        toast.error(error.value)
        return null
    }
}

const handleCheck = async () => {
    uploading.value = true
    const result = await upload(checkExport.url())
    uploading.value = false

    if (result) {
        checkRun.value = result.run
        toast.success(result.message)
        startPolling()
    }
}

const handleImport = async () => {
    importing.value = true
    const result = await upload(store.url())
    importing.value = false
    confirmOpen.value = false

    if (result) {
        importRun.value = result.run
        toast.success(result.message)
        startPolling()
    }
}

const reset = () => {
    selectedFile.value = null
    checkRun.value = null
    error.value = null

    if (!importActive.value) {
        importRun.value = null
    }

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>

<template>
    <div>
    <Card class="max-w-4xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Upload class="h-5 w-5" />
                2. Upload, check and import
            </CardTitle>
            <CardDescription>
                Upload the export zip and check it first — the check writes nothing and shows what the
                import will add, what it will skip and why. Both run in the background, so you can leave
                this page. The import only adds what's missing: nothing already in Drive is changed or
                deleted, and no emails are sent.
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
                    :disabled="uploading || checking || importing || importActive"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    @change="handleFileSelect"
                />
                <p class="text-xs text-muted-foreground">Max 100MB.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    class="min-w-[140px]"
                    :disabled="!selectedFile || uploading || checking || importActive"
                    @click="handleCheck"
                >
                    <Loader2 v-if="uploading || checking" class="mr-2 h-4 w-4 animate-spin" />
                    <FileSearch v-else class="mr-2 h-4 w-4" />
                    {{ uploading ? 'Uploading…' : checking ? 'Checking…' : 'Check file' }}
                </Button>
                <Button
                    v-if="checkTotals?.valid"
                    type="button"
                    class="min-w-[140px]"
                    :disabled="!canImport"
                    @click="confirmOpen = true"
                >
                    <Upload class="mr-2 h-4 w-4" />
                    Import
                </Button>
                <Button v-if="(checkRun || importRun) && !checking && !importActive" type="button" variant="ghost" @click="reset">
                    <X class="mr-2 h-4 w-4" />
                    Clear
                </Button>
            </div>

            <p v-if="checking" class="text-sm text-muted-foreground" role="status">{{ runStatusText(checkRun) }}</p>

            <Alert v-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>The file was not accepted</AlertTitle>
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <Alert v-if="checkRun?.status === 'failed'" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>The check stopped</AlertTitle>
                <AlertDescription>{{ checkRun.error ?? 'Try uploading the zip again.' }}</AlertDescription>
            </Alert>

            <!-- Check report -->
            <template v-if="checkTotals && !importRun">
                <Alert v-if="checkTotals.valid">
                    <CheckCircle2 class="h-4 w-4" />
                    <AlertTitle>Ready to import</AlertTitle>
                    <AlertDescription>
                        <span v-if="checkTotals.manifest.present">Every file matches its manifest. </span>
                        Nothing has been imported yet. Rows already imported are skipped, not duplicated.
                    </AlertDescription>
                </Alert>
                <Alert v-else variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>{{ formatNumber(checkTotals.check.error_count) }} problem(s) would block the import</AlertTitle>
                    <AlertDescription>Nothing has been imported. The first problems are listed below.</AlertDescription>
                </Alert>

                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead class="text-right">Rows</TableHead>
                                <TableHead class="text-right">New</TableHead>
                                <TableHead class="text-right">Already imported</TableHead>
                                <TableHead class="text-right">Skipped</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="file in fileRows" :key="file.key">
                                <TableCell class="font-mono text-sm">{{ file.name }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ formatNumber(file.rows) }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ formatNumber(file.new) }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ formatNumber(file.already_imported) }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ formatNumber(file.skippedTotal) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="detailRows.length > 0" class="max-h-96 overflow-y-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead></TableHead>
                                <TableHead>What happens</TableHead>
                                <TableHead class="text-right">Rows</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in detailRows" :key="`${row.file}-${row.kind}-${row.reason}`">
                                <TableCell class="font-mono text-sm">{{ row.file }}</TableCell>
                                <TableCell>
                                    <Badge :variant="row.kind === 'Skipped' ? 'secondary' : 'outline'">{{ row.kind }}</Badge>
                                </TableCell>
                                <TableCell>{{ row.reason }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ formatNumber(row.count) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="checkTotals.check.errors.length > 0" class="max-h-96 overflow-y-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead class="text-right">Line</TableHead>
                                <TableHead>Problem</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(problem, index) in checkTotals.check.errors" :key="`${problem.file}-${index}`">
                                <TableCell class="font-mono text-sm">{{ problem.file }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ problem.line || '' }}</TableCell>
                                <TableCell>{{ problem.message }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </template>

            <!-- Import progress / result -->
            <template v-if="importRun">
                <Alert v-if="importActive">
                    <Loader2 class="h-4 w-4 animate-spin" />
                    <AlertTitle>Importing <span v-if="importRun.file_name" class="font-mono">{{ importRun.file_name }}</span></AlertTitle>
                    <AlertDescription>{{ runStatusText(importRun) }} You can leave this page.</AlertDescription>
                </Alert>
                <Alert v-else-if="importRun.status === 'completed'">
                    <CheckCircle2 class="h-4 w-4" />
                    <AlertTitle>Import complete</AlertTitle>
                    <AlertDescription>Counts below are what this run added or filled. No emails were sent.</AlertDescription>
                </Alert>
                <Alert v-else-if="importRun.status === 'failed'" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>The import stopped before it finished</AlertTitle>
                    <AlertDescription>{{ importRun.error ?? 'Upload the same zip again to carry on.' }}</AlertDescription>
                </Alert>

                <div v-if="stageRows.length > 0" class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Stage</TableHead>
                                <TableHead class="text-right">Jobs</TableHead>
                                <TableHead>Result</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in stageRows" :key="row.stage">
                                <TableCell class="font-medium">{{ row.label }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ row.progress }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ row.counts || '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <Alert v-if="importRun.status === 'completed'">
                    <Mail class="h-4 w-4" />
                    <AlertTitle>Welcome emails are on hold</AlertTitle>
                    <AlertDescription>
                        Imported instructors and pupils have accounts but haven't been emailed. Pupils with no real
                        email address are never emailed.
                    </AlertDescription>
                </Alert>
            </template>
        </CardContent>
    </Card>

    <Dialog :open="confirmOpen" @update:open="(value) => !importing && (confirmOpen = value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Upload class="h-5 w-5" />
                    Import this file?
                </DialogTitle>
                <DialogDescription>
                    This adds the instructors, pupils, diary, tests, progress, reflective logs, finances and
                    mileage that aren't in Drive yet, exactly as the check showed. Nothing already in Drive is
                    changed or deleted — only empty fields are filled. No emails are sent. It runs in the
                    background and can take a while.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button type="button" variant="outline" :disabled="importing" @click="confirmOpen = false">
                    <X class="mr-2 h-4 w-4" />
                    Cancel
                </Button>
                <Button type="button" :disabled="importing" class="min-w-[140px]" @click="handleImport">
                    <Loader2 v-if="importing" class="mr-2 h-4 w-4 animate-spin" />
                    <Upload v-else class="mr-2 h-4 w-4" />
                    {{ importing ? 'Uploading…' : 'Import' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
    </div>
</template>
