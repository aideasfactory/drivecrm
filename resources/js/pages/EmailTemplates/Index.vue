<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
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
import { Mail, Pencil, Search } from 'lucide-vue-next'
import EditEmailTemplateSheet, {
    type EmailTemplateItem,
} from '@/components/EmailTemplates/EditEmailTemplateSheet.vue'

interface Props {
    templates: EmailTemplateItem[]
}

const props = defineProps<Props>()

type AudienceFilter = 'all' | 'learner' | 'instructor' | 'both'

const searchQuery = ref('')
const audienceFilter = ref<AudienceFilter>('all')
const isEditSheetOpen = ref(false)
const editingTemplate = ref<EmailTemplateItem | null>(null)

const filteredTemplates = computed(() => {
    const query = searchQuery.value.toLowerCase().trim()

    return props.templates.filter((template) => {
        if (
            audienceFilter.value !== 'all' &&
            template.audience !== audienceFilter.value
        ) {
            return false
        }

        if (!query) {
            return true
        }

        return (
            template.name.toLowerCase().includes(query) ||
            template.description.toLowerCase().includes(query) ||
            template.subject.toLowerCase().includes(query) ||
            template.key.toLowerCase().includes(query)
        )
    })
})

const openEditor = (template: EmailTemplateItem) => {
    editingTemplate.value = template
    isEditSheetOpen.value = true
}

const handleSaved = () => {
    isEditSheetOpen.value = false
    editingTemplate.value = null
}

const breadcrumbs = [{ title: 'Email templates' }]
</script>

<template>
    <Head title="Email templates" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="text-3xl font-bold flex items-center gap-3">
                    <Mail class="h-8 w-8" />
                    Email templates
                </h2>
                <p class="text-muted-foreground">
                    View and edit the copy sent to instructors and learners.
                    Timing, recipients, and booking processes are unchanged.
                </p>
            </div>

            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="relative max-w-md flex-1">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        class="pl-9"
                        placeholder="Search by name, subject, or key"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        :class="
                            audienceFilter === 'all'
                                ? 'bg-accent'
                                : undefined
                        "
                        @click="audienceFilter = 'all'"
                    >
                        All
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        :class="
                            audienceFilter === 'learner'
                                ? 'bg-accent'
                                : undefined
                        "
                        @click="audienceFilter = 'learner'"
                    >
                        Learners
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        :class="
                            audienceFilter === 'instructor'
                                ? 'bg-accent'
                                : undefined
                        "
                        @click="audienceFilter = 'instructor'"
                    >
                        Instructors
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        :class="
                            audienceFilter === 'both' ? 'bg-accent' : undefined
                        "
                        @click="audienceFilter = 'both'"
                    >
                        Both
                    </Button>
                </div>
            </div>

            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Email</TableHead>
                                <TableHead>Audience</TableHead>
                                <TableHead>Subject</TableHead>
                                <TableHead class="w-[120px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="template in filteredTemplates"
                                :key="template.key"
                            >
                                <TableCell>
                                    <div class="flex flex-col gap-1">
                                        <span class="font-medium">
                                            {{ template.name }}
                                        </span>
                                        <span
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ template.description }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="secondary">
                                            {{ template.audience_label }}
                                        </Badge>
                                        <Badge
                                            v-if="template.is_customised"
                                            variant="outline"
                                        >
                                            Customised
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="max-w-xs truncate">
                                    {{ template.subject }}
                                </TableCell>
                                <TableCell>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="cursor-pointer"
                                        @click="openEditor(template)"
                                    >
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="filteredTemplates.length === 0">
                                <TableCell
                                    colspan="4"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    No email templates match that search.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <EditEmailTemplateSheet
            :open="isEditSheetOpen"
            :template="editingTemplate"
            @update:open="isEditSheetOpen = $event"
            @saved="handleSaved"
        />
    </AppLayout>
</template>
