import {
    Activity,
    AlertTriangle,
    ArrowRightLeft,
    Ban,
    BellRing,
    CalendarCheck,
    CalendarClock,
    CalendarPlus,
    CalendarX,
    Car,
    CheckCircle2,
    ClipboardCheck,
    CreditCard,
    FileText,
    GraduationCap,
    Mail,
    MailCheck,
    MailX,
    MapPin,
    MessageSquare,
    Package,
    PoundSterling,
    RefreshCcw,
    Send,
    StickyNote,
    User,
    UserCheck,
    UserCog,
    UserMinus,
    UserPlus,
    Users,
} from 'lucide-vue-next'
import type { FunctionalComponent } from 'vue'

export type NotificationTone =
    | 'success'
    | 'info'
    | 'warning'
    | 'danger'
    | 'neutral'

export interface ActivityLogItem {
    id: number
    category: string
    message: string
    metadata: Record<string, unknown> | null
    created_at: string
}

export interface FriendlyNotification {
    icon: FunctionalComponent
    tone: NotificationTone
    title: string
    summary: string
    friendlyDate: string
}

type IconComponent = FunctionalComponent

/**
 * Map the metadata.type on notification-category log rows to a fine-grained
 * icon, tone, and friendly title. This is where the "human-friendly" copy
 * lives — the raw log messages stay untouched in the DB (they're searchable
 * and instructors sometimes want the raw form), but the UI reads through
 * this table.
 */
const TYPE_MAP: Record<
    string,
    { icon: IconComponent; tone: NotificationTone; title: string }
> = {
    lesson_signed_off: { icon: ClipboardCheck, tone: 'success', title: 'Lesson signed off' },
    lesson_feedback_request: { icon: Send, tone: 'info', title: 'Feedback request sent' },
    order_confirmation: { icon: MailCheck, tone: 'success', title: 'Booking confirmed' },
    welcome_student: { icon: UserPlus, tone: 'info', title: 'Welcome email sent' },
    instructor_welcome: { icon: UserPlus, tone: 'info', title: 'Welcome email sent' },
    instructor_welcome_failed: { icon: MailX, tone: 'danger', title: 'Welcome email failed' },
    resend_student_invite: { icon: Mail, tone: 'info', title: 'Invite re-sent' },
    student_registered: { icon: UserCheck, tone: 'success', title: 'Registered' },
    payment_link: { icon: PoundSterling, tone: 'info', title: 'Payment link sent' },
    lesson_payment_reminder: { icon: PoundSterling, tone: 'warning', title: 'Payment reminder sent' },
    lesson_payment_reminder_resend: { icon: RefreshCcw, tone: 'warning', title: 'Payment reminder re-sent' },
    lesson_payment_reminder_push: { icon: BellRing, tone: 'warning', title: 'Payment reminder pushed' },
    'payment-due-48h': { icon: PoundSterling, tone: 'warning', title: 'Payment due soon' },
    payment_due_48h: { icon: PoundSterling, tone: 'warning', title: 'Payment due soon' },
    lesson_reminder: { icon: CalendarClock, tone: 'info', title: 'Lesson reminder sent' },
    'miles-reminder': { icon: Car, tone: 'info', title: 'Miles reminder' },
    miles_reminder: { icon: Car, tone: 'info', title: 'Miles reminder' },
}

/**
 * Broad category fallback. Used when the row has no `metadata.type` or
 * when we hit an unknown type.
 */
const CATEGORY_MAP: Record<
    string,
    { icon: IconComponent; tone: NotificationTone; title: string }
> = {
    lesson: { icon: Car, tone: 'info', title: 'Lesson update' },
    booking: { icon: CalendarPlus, tone: 'info', title: 'Booking update' },
    payment: { icon: PoundSterling, tone: 'info', title: 'Payment update' },
    profile: { icon: UserCog, tone: 'neutral', title: 'Profile updated' },
    message: { icon: MessageSquare, tone: 'info', title: 'Message' },
    note: { icon: StickyNote, tone: 'neutral', title: 'Note' },
    notification: { icon: BellRing, tone: 'info', title: 'Notification' },
    package: { icon: Package, tone: 'info', title: 'Package update' },
    student: { icon: Users, tone: 'info', title: 'Student update' },
    student_gained: { icon: UserPlus, tone: 'success', title: 'Student added' },
    student_lost: { icon: UserMinus, tone: 'warning', title: 'Student removed' },
    instructor_assigned: { icon: GraduationCap, tone: 'info', title: 'Instructor assigned' },
    instructor_transfer: { icon: ArrowRightLeft, tone: 'info', title: 'Student transfer' },
}

const asString = (value: unknown): string | null => {
    if (typeof value === 'string' && value.trim() !== '') return value
    if (typeof value === 'number') return String(value)
    return null
}

const shortenEmail = (email: string): string => {
    // If the email is long, keep the local part and a hint of the domain.
    if (email.length <= 32) return email
    const [local, domain] = email.split('@')
    if (!domain) return email
    return `${local}@${domain.split('.')[0]}…`
}

/**
 * Derive a short body line from the raw message + metadata. Reuses the
 * metadata's structured fields (recipient_email, lesson_date, etc.) so we
 * don't rely on parsing the free-form message string.
 */
const buildSummary = (item: ActivityLogItem): string => {
    const meta = item.metadata ?? {}

    const recipient = asString(meta.recipient_email) ?? asString(meta.recipient_name)
    const lessonDate = asString(meta.lesson_date)
    const reason = asString(meta.reason)
    const bookingId = asString(meta.order_id)
    const orderId = bookingId
    const count = asString(meta.moved_count) ?? asString(meta.cancelled_count)

    const parts: string[] = []

    if (recipient) parts.push(`to ${shortenEmail(recipient)}`)
    if (lessonDate) parts.push(`for lesson on ${lessonDate}`)
    if (count) parts.push(`(${count} lesson${Number(count) === 1 ? '' : 's'})`)
    if (orderId) parts.push(`· booking #${orderId}`)
    if (reason) parts.push(`· ${reason}`)

    if (parts.length > 0) return parts.join(' ')

    // Nothing structured — fall back to the raw message but trimmed.
    const raw = item.message.trim()
    if (raw.length > 90) return `${raw.slice(0, 87)}…`
    return raw
}

/**
 * Relative time. Short and human — "Just now", "5m ago", "3h ago",
 * "Yesterday", "3d ago", or a formatted date if older than 2 weeks.
 */
export const relativeTime = (dateString: string): string => {
    const date = new Date(dateString)
    if (Number.isNaN(date.getTime())) return ''

    const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
    if (seconds < 45) return 'Just now'
    if (seconds < 90) return '1 min ago'

    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes} mins ago`

    const hours = Math.floor(minutes / 60)
    if (hours === 1) return '1 hour ago'
    if (hours < 24) return `${hours} hours ago`

    const days = Math.floor(hours / 24)
    if (days === 1) return 'Yesterday'
    if (days < 14) return `${days} days ago`

    return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

/**
 * Turn an activity log item into a friendly, iconised notification for UI.
 */
export const toFriendlyNotification = (
    item: ActivityLogItem,
): FriendlyNotification => {
    const type = asString(item.metadata?.type)
    const byType = type ? TYPE_MAP[type] : null
    const byCategory = CATEGORY_MAP[item.category] ?? null

    const base = byType ??
        byCategory ?? {
            icon: Activity,
            tone: 'neutral' as NotificationTone,
            title: 'Activity',
        }

    return {
        icon: base.icon,
        tone: base.tone,
        title: base.title,
        summary: buildSummary(item),
        friendlyDate: relativeTime(item.created_at),
    }
}

/**
 * Tailwind classes for the tone-coloured icon container.
 */
export const toneContainerClasses = (tone: NotificationTone): string => {
    switch (tone) {
        case 'success':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
        case 'warning':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
        case 'danger':
            return 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'
        case 'info':
            return 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'
        default:
            return 'bg-muted text-muted-foreground'
    }
}

/**
 * Badge variant for the shadcn Badge component based on tone.
 */
export const toneBadgeVariant = (
    tone: NotificationTone,
): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (tone) {
        case 'danger':
            return 'destructive'
        case 'success':
        case 'warning':
        case 'info':
            return 'default'
        default:
            return 'secondary'
    }
}

/**
 * A generic icon lookup for the log filter buttons (no message context).
 */
export const iconForCategory = (category: string): IconComponent => {
    if (category === 'all') return Activity
    return CATEGORY_MAP[category]?.icon ?? Activity
}

/**
 * Human-friendly label for a broad category.
 */
export const labelForCategory = (category: string): string => {
    switch (category) {
        case 'lesson':
            return 'Lessons'
        case 'booking':
            return 'Bookings'
        case 'payment':
            return 'Payments'
        case 'profile':
            return 'Profile'
        case 'message':
            return 'Messages'
        case 'note':
            return 'Notes'
        case 'notification':
            return 'Notifications'
        case 'package':
            return 'Packages'
        default:
            return category.charAt(0).toUpperCase() + category.slice(1)
    }
}

// Re-exports so callers don't need a second lucide import for common icons.
export {
    Activity,
    AlertTriangle,
    Ban,
    BellRing,
    CalendarCheck,
    CalendarClock,
    CalendarPlus,
    CalendarX,
    Car,
    CheckCircle2,
    ClipboardCheck,
    CreditCard,
    FileText,
    Mail,
    MailCheck,
    MailX,
    MapPin,
    MessageSquare,
    Package,
    PoundSterling,
    Send,
    StickyNote,
    User,
    UserCog,
    UserPlus,
    Users,
}
