export type WorkflowChannel = 'email' | 'push' | 'admin_email' | 'bird' | 'none'

export interface WorkflowMessage {
    channel: WorkflowChannel
    template: string | null
    label: string
    recipient: string
    note: string | null
}

export interface WorkflowStep {
    when: string
    what: string
    messages: WorkflowMessage[]
}

export interface Workflow {
    key: string
    group: string
    title: string
    summary: string
    steps: WorkflowStep[]
}
