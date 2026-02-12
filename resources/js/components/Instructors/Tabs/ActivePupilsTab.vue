<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { toast } from '@/components/ui/sonner'
import {
    Search,
    Plus,
    Megaphone,
    Users,
    Check,
    X,
    Loader2,
    Send,
    UserPlus,
    Save,
} from 'lucide-vue-next'
import type { InstructorDetail } from '@/types/instructor'
import type { Pupil } from '@/types/pupil'

interface Props {
    instructor: InstructorDetail
}

const props = defineProps<Props>()

const pupils = ref<Pupil[]>([])
const loading = ref(true)
const searchQuery = ref('')

// Broadcast Message Sheet
const isBroadcastSheetOpen = ref(false)
const broadcastMessage = ref('')
const isSendingBroadcast = ref(false)
const broadcastErrors = ref<Record<string, string>>({})

// Add Pupil Sheet
const isAddPupilSheetOpen = ref(false)
const isCreatingPupil = ref(false)
const pupilErrors = ref<Record<string, string>>({})
const pupilForm = ref({
    first_name: '',
    surname: '',
    email: '',
    phone: '',
    owns_account: true,
})

const filteredPupils = computed(() => {
    if (!searchQuery.value) {
        return pupils.value
    }

    const query = searchQuery.value.toLowerCase()
    return pupils.value.filter(
        (pupil) =>
            pupil.name.toLowerCase().includes(query) ||
            (pupil.email && pupil.email.toLowerCase().includes(query)) ||
            (pupil.phone && pupil.phone.toLowerCase().includes(query)),
    )
})

const loadPupils = async () => {
    loading.value = true
    try {
        const response = await axios.get(
            `/instructors/${props.instructor.id}/pupils`,
        )
        pupils.value = response.data.pupils
    } catch (error: any) {
        const message =
            error.response?.data?.message || 'Failed to load pupils'
        toast.error(message)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadPupils()
})

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const formatRevenue = (pence: number) => {
    return `£${(pence / 100).toLocaleString('en-GB', { minimumFractionDigits: 0 })}`
}

const formatNextLesson = (date: string | null, time: string | null) => {
    if (!date) return null

    const lessonDate = new Date(date)
    const today = new Date()
    const tomorrow = new Date()
    tomorrow.setDate(today.getDate() + 1)

    let dateLabel: string
    if (lessonDate.toDateString() === today.toDateString()) {
        dateLabel = 'Today'
    } else if (lessonDate.toDateString() === tomorrow.toDateString()) {
        dateLabel = 'Tomorrow'
    } else {
        dateLabel = lessonDate.toLocaleDateString('en-GB', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        })
    }

    const timeLabel = time
        ? new Date(`1970-01-01T${time}`).toLocaleTimeString('en-GB', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : null

    return { dateLabel, timeLabel }
}

const getStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (status) {
        case 'active':
            return 'default'
        case 'completed':
            return 'secondary'
        case 'pending':
            return 'outline'
        case 'cancelled':
            return 'destructive'
        default:
            return 'outline'
    }
}

const getStatusLabel = (status: string) => {
    return status.charAt(0).toUpperCase() + status.slice(1)
}

const viewPupil = (pupilId: number) => {
    router.visit(`/instructors/${props.instructor.id}`, {
        data: { tab: 'student', student: pupilId, subtab: 'overview' },
        preserveState: true,
        preserveScroll: true,
    })
}

const handleSendBroadcast = async () => {
    broadcastErrors.value = {}

    if (!broadcastMessage.value.trim()) {
        broadcastErrors.value.message = 'Message is required'
        return
    }

    isSendingBroadcast.value = true
    try {
        const response = await axios.post(
            `/instructors/${props.instructor.id}/broadcast-message`,
            { message: broadcastMessage.value },
        )
        toast.success(
            `Message sent to ${response.data.recipients_count} pupils`,
        )
        broadcastMessage.value = ''
        isBroadcastSheetOpen.value = false
    } catch (error: any) {
        if (error.response?.status === 422) {
            broadcastErrors.value = error.response.data.errors || {}
        } else {
            const message =
                error.response?.data?.message ||
                'Failed to send broadcast message'
            toast.error(message)
        }
    } finally {
        isSendingBroadcast.value = false
    }
}

const handleBroadcastSheetChange = (value: boolean) => {
    if (!isSendingBroadcast.value) {
        isBroadcastSheetOpen.value = value
        if (!value) {
            broadcastMessage.value = ''
            broadcastErrors.value = {}
        }
    }
}

const resetPupilForm = () => {
    pupilForm.value = {
        first_name: '',
        surname: '',
        email: '',
        phone: '',
        owns_account: true,
    }
    pupilErrors.value = {}
}

const handleAddPupilSheetChange = (value: boolean) => {
    if (!isCreatingPupil.value) {
        isAddPupilSheetOpen.value = value
        if (!value) {
            resetPupilForm()
        }
    }
}

const handleCreatePupil = async () => {
    pupilErrors.value = {}
    isCreatingPupil.value = true

    try {
        await axios.post(
            `/instructors/${props.instructor.id}/pupils`,
            pupilForm.value,
        )
        toast.success('Pupil created successfully')
        resetPupilForm()
        isAddPupilSheetOpen.value = false
        await loadPupils()
    } catch (error: any) {
        if (error.response?.status === 422) {
            const errs = error.response.data.errors || {}
            // Flatten Laravel validation errors (arrays) to single strings
            for (const key in errs) {
                pupilErrors.value[key] = Array.isArray(errs[key])
                    ? errs[key][0]
                    : errs[key]
            }
        } else {
            const message =
                error.response?.data?.message || 'Failed to create pupil'
            toast.error(message)
        }
    } finally {
        isCreatingPupil.value = false
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Search and Action Buttons -->
        <div class="flex items-center justify-between gap-4">
            <div class="relative max-w-md flex-1">
                <Search
                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by phone, email or name"
                    class="pl-9"
                />
            </div>
            <div class="flex items-center gap-2">
                <Button
                    @click="isAddPupilSheetOpen = true"
                    class="cursor-pointer"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Add Pupil
                </Button>
                <Button
                    variant="secondary"
                    @click="isBroadcastSheetOpen = true"
                    class="cursor-pointer"
                >
                    <Megaphone class="mr-2 h-4 w-4" />
                    Broadcast Message
                </Button>
            </div>
        </div>

        <!-- Pupils Table -->
        <Card>
            <CardContent class="p-0">
                <!-- Loading State -->
                <div v-if="loading" class="space-y-4 p-6">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>

                <!-- Table Content -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Lessons</TableHead>
                            <TableHead>Next Lesson</TableHead>
                            <TableHead>Revenue</TableHead>
                            <TableHead>App</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="pupil in filteredPupils"
                            :key="pupil.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="viewPupil(pupil.id)"
                        >
                            <TableCell>
                                <div class="flex items-center gap-3">
                                    <Avatar>
                                        <AvatarFallback>
                                            {{ getInitials(pupil.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div class="font-semibold">
                                            {{ pupil.name }}
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ pupil.email || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span class="font-medium">
                                    {{
                                        pupil.lessons_completed
                                    }}/{{ pupil.lessons_total }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <template
                                    v-if="
                                        formatNextLesson(
                                            pupil.next_lesson_date,
                                            pupil.next_lesson_time,
                                        )
                                    "
                                >
                                    <div class="text-sm">
                                        {{
                                            formatNextLesson(
                                                pupil.next_lesson_date,
                                                pupil.next_lesson_time,
                                            )!.dateLabel
                                        }}
                                    </div>
                                    <div
                                        v-if="
                                            formatNextLesson(
                                                pupil.next_lesson_date,
                                                pupil.next_lesson_time,
                                            )!.timeLabel
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatNextLesson(
                                                pupil.next_lesson_date,
                                                pupil.next_lesson_time,
                                            )!.timeLabel
                                        }}
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </TableCell>
                            <TableCell>
                                <span class="font-semibold">
                                    {{ formatRevenue(pupil.revenue_pence) }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <Check
                                    v-if="pupil.has_app"
                                    class="h-4 w-4 text-primary"
                                />
                                <X
                                    v-else
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="getStatusVariant(pupil.status)"
                                >
                                    {{ getStatusLabel(pupil.status) }}
                                </Badge>
                            </TableCell>
                        </TableRow>

                        <!-- Empty State -->
                        <TableRow v-if="filteredPupils.length === 0">
                            <TableCell colspan="6" class="text-center">
                                <div
                                    class="flex flex-col items-center gap-3 py-12 text-muted-foreground"
                                >
                                    <Users class="h-10 w-10" />
                                    <div>
                                        <p class="font-medium">
                                            {{
                                                searchQuery
                                                    ? 'No pupils found'
                                                    : 'No pupils yet'
                                            }}
                                        </p>
                                        <p class="mt-1 text-sm">
                                            {{
                                                searchQuery
                                                    ? 'Try adjusting your search'
                                                    : 'Students assigned to this instructor will appear here'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>

    <!-- Broadcast Message Sheet -->
    <Sheet
        :open="isBroadcastSheetOpen"
        @update:open="handleBroadcastSheetChange"
    >
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <Megaphone class="h-5 w-5" />
                    Broadcast Message
                </SheetTitle>
                <SheetDescription>
                    Send a message to all pupils assigned to this instructor.
                </SheetDescription>
            </SheetHeader>

            <form
                @submit.prevent="handleSendBroadcast"
                class="mt-6 space-y-6 px-6 py-4"
            >
                <div class="space-y-2">
                    <Label for="broadcast-message">Message *</Label>
                    <textarea
                        id="broadcast-message"
                        v-model="broadcastMessage"
                        placeholder="Type your message here..."
                        :disabled="isSendingBroadcast"
                        rows="6"
                        class="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <p
                        v-if="broadcastErrors.message"
                        class="text-sm text-destructive"
                    >
                        {{ broadcastErrors.message }}
                    </p>
                </div>

                <p class="text-sm text-muted-foreground">
                    This message will be sent to
                    <span class="font-semibold">{{ pupils.length }}</span>
                    {{ pupils.length === 1 ? 'pupil' : 'pupils' }}.
                </p>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleBroadcastSheetChange(false)"
                        :disabled="isSendingBroadcast"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isSendingBroadcast || !broadcastMessage.trim()"
                        class="min-w-[120px]"
                    >
                        <Loader2
                            v-if="isSendingBroadcast"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Send Message
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <!-- Add Pupil Sheet -->
    <Sheet
        :open="isAddPupilSheetOpen"
        @update:open="handleAddPupilSheetChange"
    >
        <SheetContent side="right" class="sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    <UserPlus class="h-5 w-5" />
                    Add Pupil
                </SheetTitle>
                <SheetDescription>
                    Create a new pupil and assign them to this instructor.
                </SheetDescription>
            </SheetHeader>

            <form
                @submit.prevent="handleCreatePupil"
                class="mt-6 space-y-6 px-6 py-4"
            >
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="pupil-first-name">First Name *</Label>
                        <Input
                            id="pupil-first-name"
                            v-model="pupilForm.first_name"
                            type="text"
                            placeholder="John"
                            :disabled="isCreatingPupil"
                        />
                        <p
                            v-if="pupilErrors.first_name"
                            class="text-sm text-destructive"
                        >
                            {{ pupilErrors.first_name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pupil-surname">Surname *</Label>
                        <Input
                            id="pupil-surname"
                            v-model="pupilForm.surname"
                            type="text"
                            placeholder="Doe"
                            :disabled="isCreatingPupil"
                        />
                        <p
                            v-if="pupilErrors.surname"
                            class="text-sm text-destructive"
                        >
                            {{ pupilErrors.surname }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pupil-email">Email *</Label>
                        <Input
                            id="pupil-email"
                            v-model="pupilForm.email"
                            type="email"
                            placeholder="john@example.com"
                            :disabled="isCreatingPupil"
                        />
                        <p
                            v-if="pupilErrors.email"
                            class="text-sm text-destructive"
                        >
                            {{ pupilErrors.email }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="pupil-phone">Phone</Label>
                        <Input
                            id="pupil-phone"
                            v-model="pupilForm.phone"
                            type="tel"
                            placeholder="07700 900123"
                            :disabled="isCreatingPupil"
                        />
                        <p
                            v-if="pupilErrors.phone"
                            class="text-sm text-destructive"
                        >
                            {{ pupilErrors.phone }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="pupil-owns-account"
                            :checked="pupilForm.owns_account"
                            :disabled="isCreatingPupil"
                            @update:checked="(val: boolean) => pupilForm.owns_account = val"
                        />
                        <Label for="pupil-owns-account" class="cursor-pointer">
                            Learner owns this account
                        </Label>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    A user account will be created with a default password.
                </p>

                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleAddPupilSheetChange(false)"
                        :disabled="isCreatingPupil"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isCreatingPupil"
                        class="min-w-[120px]"
                    >
                        <Loader2
                            v-if="isCreatingPupil"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save v-else class="mr-2 h-4 w-4" />
                        Create Pupil
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
