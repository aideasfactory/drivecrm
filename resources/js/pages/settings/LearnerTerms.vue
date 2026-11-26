<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import { Eye, FilePlus2, Loader2, Pencil, Save, ScrollText, Send, Trash2 } from 'lucide-vue-next'
import LearnerTermsController from '@/actions/App/Http/Controllers/Settings/LearnerTermsController'
import Heading from '@/components/Heading.vue'
import InputError from '@/components/InputError.vue'
import LearnerTermsContent from '@/components/Settings/LearnerTermsContent.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { toast } from '@/components/ui/sonner'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { edit } from '@/routes/learner-terms-settings'
import { type BreadcrumbItem } from '@/types'

interface Draft {
    id: number
    version: string
    body: string
    updated_at: string | null
}

interface PublishedVersion {
    id: number
    version: string
    body: string
    html: string
    published_at: string | null
    published_by: string | null
    acceptances_count: number
}

const props = defineProps<{
    draft: Draft | null
    versions: PublishedVersion[]
}>()

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Learner Terms', href: edit().url }]

const current = computed(() => props.versions[0] ?? null)

const formatUk = (iso: string | null): string => {
    if (!iso) return '—'

    return new Date(iso).toLocaleString('en-GB', {
        timeZone: 'Europe/London',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

/** 1.0 → 1.1, 2 → 2.1. Only a suggestion; the owner can change it. */
const suggestNextVersion = (version: string | undefined): string => {
    if (!version) return '1.0'

    const parts = version.split('.').map((part) => Number.parseInt(part, 10) || 0)
    if (parts.length === 1) parts.push(0)
    parts[parts.length - 1] += 1

    return parts.join('.')
}

// ── Editor ─────────────────────────────────────────────────────
const editing = ref(props.draft !== null)
const versionInput = ref(props.draft?.version ?? '')
const bodyInput = ref(props.draft?.body ?? '')
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const mode = ref<'write' | 'preview'>('write')
const previewHtml = ref('')
const previewLoading = ref(false)

watch(
    () => props.draft,
    (draft) => {
        editing.value = draft !== null
        versionInput.value = draft?.version ?? ''
        bodyInput.value = draft?.body ?? ''
    },
)

const isDirty = computed(
    () => props.draft === null || versionInput.value !== props.draft.version || bodyInput.value !== props.draft.body,
)

const startNewVersion = () => {
    versionInput.value = suggestNextVersion(current.value?.version)
    bodyInput.value = current.value?.body ?? ''
    errors.value = {}
    mode.value = 'write'
    editing.value = true
}

const cancelNewVersion = () => {
    editing.value = false
    errors.value = {}
}

const showPreview = async () => {
    mode.value = 'preview'
    previewLoading.value = true
    try {
        const response = await axios.post(LearnerTermsController.preview.url(), { body: bodyInput.value })
        previewHtml.value = response.data.html
    } catch {
        toast.error('Could not load the preview')
        mode.value = 'write'
    } finally {
        previewLoading.value = false
    }
}

const saveDraft = () => {
    router.put(
        LearnerTermsController.saveDraft.url(),
        { version: versionInput.value.trim(), body: bodyInput.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => {
                errors.value = {}
                toast.success('Draft saved')
            },
            onError: (validationErrors) => {
                errors.value = validationErrors
                toast.error('Please fix the errors and try again')
            },
        },
    )
}

// ── Publish / discard ──────────────────────────────────────────
const publishOpen = ref(false)
const publishing = ref(false)

const publish = () => {
    router.post(
        LearnerTermsController.publish.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (publishing.value = true),
            onFinish: () => (publishing.value = false),
            onSuccess: () => {
                publishOpen.value = false
                toast.success('Learner Terms published')
            },
            onError: (publishErrors) => {
                toast.error(publishErrors.publish ?? 'Could not publish the terms')
            },
        },
    )
}

const discardOpen = ref(false)
const discarding = ref(false)

const discardDraft = () => {
    router.delete(LearnerTermsController.destroyDraft.url(), {
        preserveScroll: true,
        onStart: () => (discarding.value = true),
        onFinish: () => (discarding.value = false),
        onSuccess: () => {
            discardOpen.value = false
            toast.success('Draft discarded')
        },
        onError: () => toast.error('Could not discard the draft'),
    })
}

// ── View a published version ───────────────────────────────────
const viewing = ref<PublishedVersion | null>(null)
const viewOpen = computed({
    get: () => viewing.value !== null,
    set: (open: boolean) => {
        if (!open) viewing.value = null
    },
})
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Learner Terms" />

        <h1 class="sr-only">Learner Terms</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    title="Learner Terms"
                    description="The DRIVE+ Learner Terms and Conditions every learner must accept in the student app. Publishing a new version asks every learner to accept it again on their next app open."
                />

                <!-- Current version -->
                <Card>
                    <CardContent class="flex flex-wrap items-center justify-between gap-4 p-4">
                        <div v-if="current" class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Current version v{{ current.version }}</span>
                                <Badge variant="secondary">Live</Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Published {{ formatUk(current.published_at) }} ·
                                {{ current.acceptances_count }}
                                {{ current.acceptances_count === 1 ? 'learner has' : 'learners have' }} accepted
                            </p>
                        </div>
                        <div v-else class="space-y-1">
                            <span class="font-semibold">No version published</span>
                            <p class="text-sm text-muted-foreground">
                                Learners aren't asked to accept anything until you publish the first version.
                            </p>
                        </div>
                        <Button v-if="current" variant="outline" @click="viewing = current">
                            <Eye class="mr-2 h-4 w-4" />
                            View
                        </Button>
                    </CardContent>
                </Card>

                <!-- Draft editor -->
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold">
                            {{ draft ? `Draft v${draft.version}` : editing ? 'New version' : 'Next version' }}
                        </h3>
                        <Button v-if="!editing" @click="startNewVersion">
                            <FilePlus2 class="mr-2 h-4 w-4" />
                            Start new version
                        </Button>
                    </div>

                    <p v-if="!editing" class="text-sm text-muted-foreground">
                        Start a new version to edit the terms. Published versions can't be changed, so learners'
                        acceptances always point at the exact text they agreed to.
                    </p>

                    <template v-else>
                        <div class="grid gap-2">
                            <Label for="version">Version number</Label>
                            <Input id="version" v-model="versionInput" placeholder="1.0" class="max-w-40" />
                            <InputError :message="errors.version" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <Label for="body">Terms text (Markdown)</Label>
                                <div class="flex items-center gap-1 rounded-md border p-1">
                                    <Button
                                        size="sm"
                                        :variant="mode === 'write' ? 'default' : 'ghost'"
                                        @click="mode = 'write'"
                                    >
                                        <Pencil class="mr-1 h-3.5 w-3.5" />
                                        Write
                                    </Button>
                                    <Button
                                        size="sm"
                                        :variant="mode === 'preview' ? 'default' : 'ghost'"
                                        @click="showPreview"
                                    >
                                        <Eye class="mr-1 h-3.5 w-3.5" />
                                        Preview
                                    </Button>
                                </div>
                            </div>

                            <Textarea
                                v-show="mode === 'write'"
                                id="body"
                                v-model="bodyInput"
                                rows="22"
                                class="font-mono text-xs"
                                placeholder="# DRIVE+ Learner Terms and Conditions"
                            />
                            <div
                                v-if="mode === 'preview'"
                                class="max-h-[560px] min-h-40 overflow-y-auto rounded-md border p-4"
                            >
                                <div v-if="previewLoading" class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Loader2 class="h-4 w-4 animate-spin" />
                                    Loading preview…
                                </div>
                                <LearnerTermsContent v-else :html="previewHtml" />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Use <code># Heading</code>, <code>## Sub-heading</code>, <code>**bold**</code>,
                                <code>- list item</code> and a blank line between paragraphs. The app shows headings as
                                headings and follows the learner's text-size setting.
                            </p>
                            <InputError :message="errors.body" />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button :disabled="saving || !isDirty" class="min-w-32" @click="saveDraft">
                                <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                                <Save v-else class="mr-2 h-4 w-4" />
                                Save draft
                            </Button>
                            <Button
                                variant="secondary"
                                :disabled="!draft || isDirty"
                                :title="isDirty ? 'Save the draft before publishing' : undefined"
                                @click="publishOpen = true"
                            >
                                <Send class="mr-2 h-4 w-4" />
                                Publish
                            </Button>
                            <Button v-if="draft" variant="ghost" @click="discardOpen = true">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Discard draft
                            </Button>
                            <Button v-else variant="ghost" @click="cancelNewVersion">Cancel</Button>
                            <span v-if="draft && isDirty" class="text-xs text-muted-foreground">Unsaved changes</span>
                        </div>
                    </template>
                </div>

                <!-- History -->
                <div v-if="versions.length" class="space-y-3">
                    <h3 class="text-base font-semibold">Published versions</h3>
                    <Card>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Version</TableHead>
                                        <TableHead>Published</TableHead>
                                        <TableHead>By</TableHead>
                                        <TableHead class="text-right">Accepted</TableHead>
                                        <TableHead class="w-16" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="version in versions" :key="version.id">
                                        <TableCell class="font-medium">v{{ version.version }}</TableCell>
                                        <TableCell>{{ formatUk(version.published_at) }}</TableCell>
                                        <TableCell>{{ version.published_by ?? '—' }}</TableCell>
                                        <TableCell class="text-right">{{ version.acceptances_count }}</TableCell>
                                        <TableCell>
                                            <Button size="icon" variant="ghost" @click="viewing = version">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </SettingsLayout>

        <!-- Publish confirmation -->
        <Dialog v-model:open="publishOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Publish Learner Terms v{{ draft?.version }}?</DialogTitle>
                    <DialogDescription>
                        Every learner will have to read and accept these terms the next time they open the app. A
                        published version can't be edited.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="publishOpen = false">Cancel</Button>
                    <Button :disabled="publishing" class="min-w-28" @click="publish">
                        <Loader2 v-if="publishing" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Publish
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Discard confirmation -->
        <Dialog v-model:open="discardOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Discard draft v{{ draft?.version }}?</DialogTitle>
                    <DialogDescription>The draft text will be deleted. Published versions aren't affected.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="discardOpen = false">Cancel</Button>
                    <Button variant="destructive" :disabled="discarding" class="min-w-28" @click="discardDraft">
                        <Loader2 v-if="discarding" class="mr-2 h-4 w-4 animate-spin" />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        Discard
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- View a published version -->
        <Sheet v-model:open="viewOpen">
            <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle class="flex items-center gap-2">
                        <ScrollText class="h-5 w-5" />
                        Learner Terms v{{ viewing?.version }}
                    </SheetTitle>
                    <SheetDescription>Published {{ formatUk(viewing?.published_at ?? null) }}</SheetDescription>
                </SheetHeader>
                <div class="px-6 py-4">
                    <LearnerTermsContent v-if="viewing" :html="viewing.html" />
                </div>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
