import type { Component } from 'vue'
import { CircleSlash, Contact, Inbox, Mail, Smartphone } from 'lucide-vue-next'
import type { WorkflowChannel } from '@/types/workflow'

export interface ChannelMeta {
    label: string
    description: string
    icon: Component
}

export const channels: Record<WorkflowChannel, ChannelMeta> = {
    email: {
        label: 'Email',
        description:
            'Sent by the system. The wording is editable: click one to open it.',
        icon: Mail,
    },
    push: {
        label: 'Push',
        description:
            'A notification on the phone, for people who have the app and allowed notifications.',
        icon: Smartphone,
    },
    admin_email: {
        label: 'Head office email',
        description:
            'An alert to your team. The wording is fixed and cannot be edited here.',
        icon: Inbox,
    },
    bird: {
        label: 'Bird',
        description:
            'The contact is added to Bird. Any SMS or WhatsApp comes from Bird automations, not this system.',
        icon: Contact,
    },
    none: {
        label: 'Nothing sent',
        description: 'This step happens without anyone being notified.',
        icon: CircleSlash,
    },
}
