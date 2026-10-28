<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
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
import { welcomeEmails } from '@/routes/imports'
import { AlertCircle, FileSearch, Loader2, Mail, Send, X } from 'lucide-vue-next'

interface PendingInstructor {
    id: number
    name: string
    email: string
}

interface WelcomePreview {
    pending: PendingInstructor[]
    already_sent: string[]
    not_instructors: string[]
    sent: number
    failed: string[]
}

defineProps<{
    importRunning: boolean
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const previewing = ref(false)
const sending = ref(false)
const preview = ref<WelcomePreview | null>(null)
const error = ref<string | null>(null)
const confirmOpen = ref(false)

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement
    selectedFile.value = target.files?.[0] ?? null
    preview.value = null
    error.value = null
}

const post = async (send: boolean): Promise<WelcomePreview | null> => {
    if (!selectedFile.value) {
        error.value = 'Please choose a CSV of instructor emails.'
        return null
    }

    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('send', send ? '1' : '0')

    try {
        const response = await axios.post(welcomeEmails.url(), formData, {
            headers: { Accept: 'application/json' },
            withXSRFToken: true,
        })
        error.value = null
        return response.data as WelcomePreview
    } catch (caught: unknown) {
        const data = (caught as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
            .response?.data
        error.value = Object.values(data?.errors ?? {}).flat()[0] ?? data?.message ?? 'Something went wrong. Try again.'
        toast.error(error.value)
        return null
    }
}

const handlePreview = async () => {
    previewing.value = true
    preview.value = await post(false)
    previewing.value = false
}

const handleSend = async () => {
    sending.value = true
    const result = await post(true)
    sending.value = false
    confirmOpen.value = false

    if (!result) {
        return
    }

    if (result.failed.length > 0) {
        toast.error(`Sent ${result.sent}; ${result.failed.length} failed and are still pending.`)
    } else {
        toast.success(`Queued ${result.sent} welcome email(s).`)
    }

    // Re-check so the lists show who is still waiting after the send.
    preview.value = await post(false)
}

const reset = () => {
    selectedFile.value = null
    preview.value = null
    error.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>

<template>
    <Card class="max-w-3xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Mail class="h-5 w-5" />
                Send welcome emails to selected instructors
            </CardTitle>
            <CardDescription>
                Upload a CSV with the instructors' email addresses (any column, header
                optional). Only imported instructors still waiting for their welcome email
                are sent one — their students aren't emailed, and nobody is emailed twice.
            </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <Label for="welcome-file">Instructor emails CSV</Label>
                <input
                    id="welcome-file"
                    ref="fileInput"
                    type="file"
                    accept=".csv,.txt"
                    :disabled="previewing || sending"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    @change="handleFileSelect"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="!selectedFile || previewing || sending"
                    class="min-w-[140px]"
                    @click="handlePreview"
                >
                    <Loader2 v-if="previewing" class="mr-2 h-4 w-4 animate-spin" />
                    <FileSearch v-else class="mr-2 h-4 w-4" />
                    {{ previewing ? 'Checking...' : 'Check list' }}
                </Button>
                <Button
                    v-if="preview && preview.pending.length > 0"
                    type="button"
                    :disabled="sending"
                    class="min-w-[140px]"
                    @click="confirmOpen = true"
                >
                    <Send class="mr-2 h-4 w-4" />
                    Send {{ preview.pending.length }} welcome email(s)
                </Button>
                <Button v-if="preview" type="button" variant="ghost" @click="reset">
                    <X class="mr-2 h-4 w-4" />
                    Clear
                </Button>
            </div>

            <Alert v-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Couldn't read the list</AlertTitle>
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <template v-if="preview">
                <Alert v-if="importRunning && preview.pending.length > 0">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>An import is running</AlertTitle>
                    <AlertDescription>
                        Emails are queued behind it, so they go out once the import finishes
                        (sooner if there is a second queue worker).
                    </AlertDescription>
                </Alert>

                <p v-if="preview.pending.length === 0" class="text-sm text-muted-foreground">
                    Nobody on this list is waiting for a welcome email.
                </p>

                <div v-else class="max-h-72 overflow-y-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Will be emailed</TableHead>
                                <TableHead>Email</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="instructor in preview.pending" :key="instructor.id">
                                <TableCell>{{ instructor.name }}</TableCell>
                                <TableCell class="font-mono text-xs">{{ instructor.email }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="preview.already_sent.length > 0" class="text-sm">
                    <p class="font-medium">Already sent ({{ preview.already_sent.length }}) — skipped</p>
                    <p class="font-mono text-xs text-muted-foreground">{{ preview.already_sent.join(', ') }}</p>
                </div>

                <div v-if="preview.not_instructors.length > 0" class="text-sm">
                    <p class="font-medium">Not an instructor ({{ preview.not_instructors.length }}) — skipped</p>
                    <p class="font-mono text-xs text-muted-foreground">{{ preview.not_instructors.join(', ') }}</p>
                </div>
            </template>
        </CardContent>

        <Dialog :open="confirmOpen" @update:open="(value) => !sending && (confirmOpen = value)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Send class="h-5 w-5" />
                        Send {{ preview?.pending.length ?? 0 }} welcome email(s)?
                    </DialogTitle>
                    <DialogDescription>
                        Each instructor gets a link to set their password and sign in. This
                        can't be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="sending" @click="confirmOpen = false">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button type="button" :disabled="sending" class="min-w-[140px]" @click="handleSend">
                        <Loader2 v-if="sending" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        {{ sending ? 'Sending...' : 'Send' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </Card>
</template>
