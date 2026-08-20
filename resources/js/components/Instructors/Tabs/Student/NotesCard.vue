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
import { Textarea } from '@/components/ui/textarea'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { StickyNote, Lock, Trash2, Loader2, Plus, FileText } from 'lucide-vue-next'
import { toast } from '@/components/ui/sonner'

interface Note {
    id: number
    note: string
    is_internal?: boolean
    created_at: string
    user?: { id: number; name: string } | null
}

interface PaginationMeta {
    current_page: number
    total: number
    per_page: number
    last_page: number
}

interface Props {
    studentId: number
    /** Admin-only internal CRM notes (server enforces owner role) */
    internal?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    internal: false,
})

const notes = ref<Note[]>([])
const notesMeta = ref<PaginationMeta | null>(null)
const notesLoading = ref(true)
const isLoadingMore = ref(false)

const newNote = ref('')
const isSubmitting = ref(false)

const isDeleteDialogOpen = ref(false)
const deleteTarget = ref<Note | null>(null)
const isDeleting = ref(false)

const hasMorePages = computed(() => {
    if (!notesMeta.value) return false
    return notesMeta.value.current_page < notesMeta.value.last_page
})

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2)
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

const loadNotes = async (page = 1, append = false) => {
    if (page === 1) {
        notesLoading.value = true
    } else {
        isLoadingMore.value = true
    }

    try {
        const response = await axios.get(`/students/${props.studentId}/notes`, {
            params: { page, internal: props.internal ? 1 : 0 },
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

const handleAddNote = async () => {
    if (!newNote.value.trim()) return

    isSubmitting.value = true
    try {
        const response = await axios.post(`/students/${props.studentId}/notes`, {
            note: newNote.value.trim(),
            is_internal: props.internal,
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
    loadNotes()
})
</script>

<template>
    <div>
    <Card :class="internal ? 'border-2 border-primary/40 bg-primary/5' : ''">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Lock v-if="internal" class="h-5 w-5 text-primary" />
                <StickyNote v-else class="h-5 w-5" />
                {{ internal ? 'Internal Notes' : 'Notes' }}
                <Badge v-if="internal" variant="default">
                    <Lock class="mr-1 h-3 w-3" />
                    Private · Admin only
                </Badge>
            </CardTitle>
            <p class="text-sm text-muted-foreground">
                {{ internal
                    ? 'Private CRM notes about this pupil — never visible to instructors or pupils.'
                    : 'Notes shared between the instructor and admin.' }}
            </p>
        </CardHeader>
        <CardContent>
            <!-- Add Note Form -->
            <form @submit.prevent="handleAddNote" class="mb-6 space-y-2">
                <Textarea
                    v-model="newNote"
                    rows="3"
                    :placeholder="internal ? 'Add an internal note...' : 'Add a note...'"
                    :disabled="isSubmitting"
                    class="bg-background"
                />
                <div class="flex justify-end">
                    <Button
                        type="submit"
                        :disabled="isSubmitting || !newNote.trim()"
                        class="min-w-[100px]"
                    >
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        Add Note
                    </Button>
                </div>
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
                    <p class="font-medium">{{ internal ? 'No internal notes yet' : 'No notes yet' }}</p>
                    <p class="mt-1 text-sm">Add a note above to get started</p>
                </div>
            </div>

            <!-- Notes List -->
            <div v-else class="space-y-2">
                <Item
                    v-for="note in notes"
                    :key="note.id"
                    variant="outline"
                    :class="internal ? 'bg-background' : ''"
                >
                    <ItemMedia>
                        <Avatar class="h-8 w-8">
                            <AvatarFallback class="bg-primary text-xs font-semibold text-primary-foreground">
                                {{ note.user ? getInitials(note.user.name) : '?' }}
                            </AvatarFallback>
                        </Avatar>
                    </ItemMedia>
                    <ItemContent>
                        <ItemTitle class="whitespace-pre-wrap">{{ note.note }}</ItemTitle>
                        <ItemDescription>
                            {{ note.user ? note.user.name + ' · ' : '' }}{{ timeAgo(note.created_at) }}
                        </ItemDescription>
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
