export interface Pupil {
    id: number
    user_id: number | null
    name: string
    email: string | null
    phone: string | null
    lessons_completed: number
    lessons_total: number
    next_lesson_date: string | null
    next_lesson_time: string | null
    revenue_pence: number
    has_app: boolean
    status: 'active' | 'pending' | 'completed' | 'cancelled'
}

export interface PupilListing {
    id: number
    name: string
    email: string | null
    status: string
    instructor_id: number | null
    instructor_name: string | null
}
