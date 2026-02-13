<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Item,
    ItemContent,
    ItemTitle,
    ItemDescription,
    ItemMedia,
    ItemActions,
} from '@/components/ui/item'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import {
    BookOpen,
    PoundSterling,
    Smartphone,
    StickyNote,
    Trash2,
    Loader2,
    Plus,
    FileText,
} from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

interface Note {
    id: number
    note: string
    created_at: string
}

interface PaginationMeta {
    current_page: number
    total: number
    per_page: number
    last_page: number
}

interface StudentOverview {
    lessons_completed: number
    lessons_total: number
    revenue_pence: number
    has_app: boolean
}

interface Props {
    studentId: number
}

const props = defineProps<Props>()

// Student overview state
const overview = ref<StudentOverview | null>(null)
const overviewLoading = ref(true)

// Notes state
const notes = ref<Note[]>([])
const notesMeta = ref<PaginationMeta | null>(null)
const notesLoading = ref(true)
const isLoadingMore = ref(false)

// Add note state
const newNote = ref('')
const isSubmitting = ref(false)

// Delete state
const isDeleteDialogOpen = ref(false)
const deleteTarget = ref<Note | null>(null)
const isDeleting = ref(false)

// Computed
const hasMorePages = computed(() => {
    if (!notesMeta.value) return false
    return notesMeta.value.current_page < notesMeta.value.last_page
})

const formatCurrency = (pence: number): string => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(pence / 100)
}

const timeAgo = (dateString: string): string => {
    const date = new Date(dateString)
    const now = new Date()
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)

    if (seconds < 60) return 'Just now'

    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`

    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours} hour${hours !== 1 ? 's' : ''} ago`

    const days = Math.floor(hours / 24)
    if (days < 7) return `${days} day${days !== 1 ? 's' : ''} ago`

    const weeks = Math.floor(days / 7)
    if (weeks < 4) return `${weeks} week${weeks !== 1 ? 's' : ''} ago`

    const months = Math.floor(days / 30)
    if (months < 12) return `${months} month${months !== 1 ? 's' : ''} ago`

    const years = Math.floor(days / 365)
    return `${years} year${years !== 1 ? 's' : ''} ago`
}

// Load student overview data
const loadOverview = async () => {
    overviewLoading.value = true
    try {
        const response = await axios.get(`/students/${props.studentId}`)
        overview.value = response.data.student
    } catch {
        toast.error('Failed to load student overview')
    } finally {
        overviewLoading.value = false
    }
}

// Load notes
const loadNotes = async (page = 1, append = false) => {
    if (page === 1) {
        notesLoading.value = true
    } else {
        isLoadingMore.value = true
    }

    try {
        const response = await axios.get(`/students/${props.studentId}/notes`, {
            params: { page },
        })

        if (append) {
            notes.value.push(...response.data.notes)
        } else {
            notes.value = response.data.notes || []
        }

        notesMeta.value = response.data.meta
    } catch {
        toast.error('Failed to load notes')
    } finally {
        notesLoading.value = false
        isLoadingMore.value = false
    }
}

const loadMore = () => {
    if (!notesMeta.value || !hasMorePages.value) return
    loadNotes(notesMeta.value.current_page + 1, true)
}

// Add note
const handleAddNote = async () => {
    if (!newNote.value.trim()) return

    isSubmitting.value = true
    try {
        const response = await axios.post(`/students/${props.studentId}/notes`, {
            note: newNote.value.trim(),
        })
        notes.value.unshift(response.data.note)
        if (notesMeta.value) {
            notesMeta.value.total++
        }
        newNote.value = ''
        toast.success('Note added successfully')
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to add note'
        toast.error(message)
    } finally {
        isSubmitting.value = false
    }
}

// Delete note
const openDeleteDialog = (note: Note) => {
    deleteTarget.value = note
    isDeleteDialogOpen.value = true
}

const handleDelete = async () => {
    if (!deleteTarget.value) return

    isDeleting.value = true
    try {
        await axios.delete(`/students/${props.studentId}/notes/${deleteTarget.value.id}`)
        notes.value = notes.value.filter((n) => n.id !== deleteTarget.value!.id)
        if (notesMeta.value) {
            notesMeta.value.total--
        }
        isDeleteDialogOpen.value = false
        deleteTarget.value = null
        toast.success('Note deleted successfully')
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to delete note'
        toast.error(message)
    } finally {
        isDeleting.value = false
    }
}

onMounted(() => {
    loadOverview()
    loadNotes()
})
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Hero Stat Cards -->
        <div v-if="overviewLoading" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card v-for="n in 3" :key="n">
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <Skeleton class="h-4 w-4" />
                        <Skeleton class="h-8 w-20" />
                        <Skeleton class="h-4 w-24" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="overview" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Total Lessons -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <BookOpen class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">
                                {{ overview.lessons_completed }}/{{ overview.lessons_total }}
                            </p>
                            <p class="text-sm text-muted-foreground">Total Lessons</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Total Revenue -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <PoundSterling class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">
                                {{ formatCurrency(overview.revenue_pence) }}
                            </p>
                            <p class="text-sm text-muted-foreground">Total Revenue</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- App Installed -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <Smartphone class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <Badge :variant="overview.has_app ? 'default' : 'secondary'" class="text-sm">
                                {{ overview.has_app ? 'Installed' : 'Not Installed' }}
                            </Badge>
                            <p class="mt-1 text-sm text-muted-foreground">App Installed</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Notes Section -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <StickyNote class="h-5 w-5" />
                    Notes
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Add Note Form -->
                <form @submit.prevent="handleAddNote" class="mb-6 flex gap-2">
                    <Input
                        v-model="newNote"
                        placeholder="Add a note..."
                        :disabled="isSubmitting"
                        class="flex-1"
                    />
                    <Button
                        type="submit"
                        :disabled="isSubmitting || !newNote.trim()"
                        class="min-w-[100px]"
                    >
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        Add Note
                    </Button>
                </form>

                <!-- Notes Loading Skeleton -->
                <div v-if="notesLoading" class="space-y-2">
                    <div v-for="n in 3" :key="n" class="flex items-center gap-4 rounded-md p-4">
                        <Skeleton class="h-8 w-8 rounded-sm" />
                        <div class="flex-1 space-y-2">
                            <Skeleton class="h-4 w-3/4" />
                            <Skeleton class="h-3 w-24" />
                        </div>
                        <Skeleton class="h-8 w-8" />
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="notes.length === 0"
                    class="flex min-h-[150px] flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <FileText class="h-10 w-10" />
                    <div class="text-center">
                        <p class="font-medium">No notes yet</p>
                        <p class="mt-1 text-sm">Add a note above to get started</p>
                    </div>
                </div>

                <!-- Notes List -->
                <div v-else class="space-y-2">
                    <Item
                        v-for="note in notes"
                        :key="note.id"
                        variant="outline"
                    >
                        <ItemMedia variant="icon">
                            <StickyNote class="h-4 w-4 text-muted-foreground" />
                        </ItemMedia>
                        <ItemContent>
                            <ItemTitle>{{ note.note }}</ItemTitle>
                            <ItemDescription>{{ timeAgo(note.created_at) }}</ItemDescription>
                        </ItemContent>
                        <ItemActions>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="openDeleteDialog(note)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </ItemActions>
                    </Item>

                    <!-- Load More -->
                    <div v-if="hasMorePages" class="flex justify-center pt-4">
                        <Button
                            variant="outline"
                            @click="loadMore"
                            :disabled="isLoadingMore"
                            class="min-w-[140px]"
                        >
                            <Loader2
                                v-if="isLoadingMore"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Load More
                        </Button>
                    </div>

                    <!-- Total count -->
                    <div v-if="notesMeta" class="pt-2 text-center text-xs text-muted-foreground">
                        Showing {{ notes.length }} of {{ notesMeta.total }} notes
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Note?</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to delete this note? This action cannot be undone.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="isDeleteDialogOpen = false"
                        :disabled="isDeleting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="handleDelete"
                        :disabled="isDeleting"
                        class="min-w-[100px]"
                    >
                        <Loader2
                            v-if="isDeleting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Trash2
                            v-else
                            class="mr-2 h-4 w-4"
                        />
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
