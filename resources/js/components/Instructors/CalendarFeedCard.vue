<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { CalendarPlus, Copy, Loader2, RefreshCw } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { toast } from '@/components/ui/toast'
import { reset } from '@/actions/App/Http/Controllers/InstructorCalendarFeedController'
import type { CalendarFeedLinks } from '@/types/instructor'

interface Props {
    instructorId: number
    feed: CalendarFeedLinks
}

const props = defineProps<Props>()

const links = ref<CalendarFeedLinks>(props.feed)
const isResetDialogOpen = ref(false)
const isResetting = ref(false)

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(links.value.url)
        toast({ title: 'Calendar link copied to clipboard' })
    } catch {
        toast({ title: 'Failed to copy calendar link', variant: 'destructive' })
    }
}

const resetLink = async () => {
    if (isResetting.value) {
        return
    }

    isResetting.value = true

    try {
        const { data } = await axios.post(reset.url(props.instructorId))
        links.value = data.calendar_feed
        isResetDialogOpen.value = false
        toast({ title: data?.message ?? 'Calendar link reset.' })
    } catch (error: any) {
        const message = error?.response?.data?.message ?? 'Failed to reset the calendar link.'
        toast({ title: message, variant: 'destructive' })
    } finally {
        isResetting.value = false
    }
}
</script>

<template>
    <div>
        <Card>
            <CardHeader>
                <CardTitle>Calendar Sync</CardTitle>
                <CardDescription>
                    Add booked lessons and driving tests to Google, Apple or Outlook Calendar. Calendar apps
                    refresh the link every few hours. Keep it private: anyone with the link can see pupil names.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div class="flex gap-2">
                    <Input :model-value="links.url" readonly aria-label="Calendar feed link" @focus="($event.target as HTMLInputElement).select()" />
                    <Button variant="outline" @click="copyLink">
                        <Copy class="mr-2 h-4 w-4" />
                        Copy
                    </Button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as="a" variant="outline" :href="links.google_url" target="_blank" rel="noopener noreferrer">
                        <CalendarPlus class="mr-2 h-4 w-4" />
                        Add to Google Calendar
                    </Button>
                    <Button as="a" variant="outline" :href="links.webcal_url">
                        <CalendarPlus class="mr-2 h-4 w-4" />
                        Add to Apple Calendar
                    </Button>
                    <Button variant="ghost" @click="isResetDialogOpen = true">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Reset link
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="isResetDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reset calendar link?</DialogTitle>
                    <DialogDescription>
                        The current link stops working straight away. Any calendar using it stops updating and
                        needs the new link adding again.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" :disabled="isResetting" @click="isResetDialogOpen = false">Cancel</Button>
                    <Button variant="destructive" :disabled="isResetting" @click="resetLink">
                        <Loader2 v-if="isResetting" class="mr-2 h-4 w-4 animate-spin" />
                        Reset link
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
