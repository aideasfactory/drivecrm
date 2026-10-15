<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    ArrowLeft,
    ArrowRight,
    ChevronsUpDown,
    Workflow as WorkflowIcon,
} from 'lucide-vue-next'
import WorkflowTimeline from '@/components/Workflows/WorkflowTimeline.vue'
import { channels } from '@/components/Workflows/channels'
import EditEmailTemplateSheet, {
    type EmailTemplateItem,
} from '@/components/EmailTemplates/EditEmailTemplateSheet.vue'
import type { Workflow, WorkflowChannel } from '@/types/workflow'

interface Props {
    workflows: Workflow[]
    templates: EmailTemplateItem[]
}

const props = defineProps<Props>()

const selectedKey = ref<string>(props.workflows[0]?.key ?? '')
const isEditSheetOpen = ref(false)
const editingTemplateKey = ref<string | null>(null)

const channelLegend = Object.entries(channels) as [
    WorkflowChannel,
    (typeof channels)[WorkflowChannel],
][]

const groupedWorkflows = computed(() => {
    const groups = new Map<string, Workflow[]>()

    props.workflows.forEach((workflow) => {
        groups.set(workflow.group, [
            ...(groups.get(workflow.group) ?? []),
            workflow,
        ])
    })

    return [...groups.entries()].map(([group, workflows]) => ({
        group,
        workflows,
    }))
})

const selectedIndex = computed(() =>
    props.workflows.findIndex((workflow) => workflow.key === selectedKey.value),
)

const selectedWorkflow = computed(
    () => props.workflows[selectedIndex.value] ?? null,
)

const previousWorkflow = computed(
    () => props.workflows[selectedIndex.value - 1] ?? null,
)

const nextWorkflow = computed(
    () => props.workflows[selectedIndex.value + 1] ?? null,
)

const editingTemplate = computed(
    () =>
        props.templates.find(
            (template) => template.key === editingTemplateKey.value,
        ) ?? null,
)

const openTemplate = (key: string) => {
    editingTemplateKey.value = key
    isEditSheetOpen.value = true
}

const handleSaved = () => {
    isEditSheetOpen.value = false
    editingTemplateKey.value = null
}

watch(selectedKey, (key) => {
    const url = new URL(window.location.href)
    url.searchParams.set('workflow', key)
    window.history.replaceState(window.history.state, '', url)
    window.scrollTo({ top: 0, behavior: 'smooth' })
})

onMounted(() => {
    const requested = new URL(window.location.href).searchParams.get('workflow')

    if (props.workflows.some((workflow) => workflow.key === requested)) {
        selectedKey.value = requested as string
    }
})

const breadcrumbs = [{ title: 'Workflows' }]
</script>

<template>
    <Head title="Workflows" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h2 class="flex items-center gap-3 text-3xl font-bold">
                    <WorkflowIcon class="h-8 w-8" />
                    Workflows
                </h2>
                <p class="text-muted-foreground">
                    Every journey from first enquiry to last lesson: what
                    happens at each stage, what triggers it, and what is sent to
                    whom. Timings shown are the system's current settings.
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <Label>Choose a workflow</Label>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            class="h-auto w-full cursor-pointer justify-between gap-3 py-3 text-left"
                        >
                            <span class="flex min-w-0 flex-col">
                                <span
                                    v-if="selectedWorkflow"
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    {{ selectedWorkflow.group }}
                                </span>
                                <span class="truncate text-base">
                                    {{
                                        selectedWorkflow?.title ??
                                        'Choose a workflow'
                                    }}
                                </span>
                            </span>
                            <ChevronsUpDown
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        class="max-h-[60vh] w-(--reka-dropdown-menu-trigger-width)"
                    >
                        <DropdownMenuRadioGroup v-model="selectedKey">
                            <template
                                v-for="(section, index) in groupedWorkflows"
                                :key="section.group"
                            >
                                <DropdownMenuSeparator v-if="index > 0" />
                                <DropdownMenuLabel
                                    class="text-xs uppercase tracking-wide text-muted-foreground"
                                >
                                    {{ section.group }}
                                </DropdownMenuLabel>
                                <DropdownMenuRadioItem
                                    v-for="workflow in section.workflows"
                                    :key="workflow.key"
                                    :value="workflow.key"
                                    class="cursor-pointer"
                                >
                                    {{ workflow.title }}
                                </DropdownMenuRadioItem>
                            </template>
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <WorkflowTimeline
                v-if="selectedWorkflow"
                :workflow="selectedWorkflow"
                @open-template="openTemplate"
            />

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-between">
                <Button
                    v-if="previousWorkflow"
                    variant="outline"
                    class="h-auto cursor-pointer justify-start whitespace-normal py-2 text-left"
                    @click="selectedKey = previousWorkflow.key"
                >
                    <ArrowLeft class="mr-2 h-4 w-4 shrink-0" />
                    {{ previousWorkflow.title }}
                </Button>
                <span v-else />
                <Button
                    v-if="nextWorkflow"
                    variant="outline"
                    class="h-auto cursor-pointer justify-end whitespace-normal py-2 text-right"
                    @click="selectedKey = nextWorkflow.key"
                >
                    {{ nextWorkflow.title }}
                    <ArrowRight class="ml-2 h-4 w-4 shrink-0" />
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">How messages are sent</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="[key, channel] in channelLegend"
                            :key="key"
                            class="flex items-start gap-3"
                        >
                            <component
                                :is="channel.icon"
                                class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-medium">
                                    {{ channel.label }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ channel.description }}
                                </span>
                            </div>
                        </div>
                    </div>
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
