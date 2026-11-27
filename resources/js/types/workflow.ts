export type WorkflowChannel = 'email' | 'push' | 'admin_email' | 'bird' | 'none'

export interface WorkflowReference {
    key: string
    title: string
}

export interface WorkflowMessage {
    channel: WorkflowChannel
    template: string | null
    label: string
    recipient: string
    note: string | null
    /** Email conditions that always hold when this workflow sends the email. */
    scenario: string[]
    /** Other workflows that send the same email. */
    shared_with: WorkflowReference[]
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
    scenario: string[]
    steps: WorkflowStep[]
}
