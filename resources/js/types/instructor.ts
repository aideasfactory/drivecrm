export interface Instructor {
  id: number
  name: string
  email: string
  connection_status: 'connected' | 'not_connected'
  pupils_count: number
  last_sync: string
}

export interface InstructorDetail {
  id: number
  name: string
  email: string
  phone: string | null
  postcode: string | null
  bio: string | null
  rating: number | null
  transmission_type: 'manual' | 'automatic' | 'both'
  status: string
  stats: InstructorStats
  booking_hours: BookingHours
  locations: Location[]
}

export interface InstructorStats {
  current_pupils: number
  passed_pupils: number
  archived_pupils: number
  waiting_list: number
  open_enquiries: number
}

export interface BookingHours {
  current_week: number
  next_week: number
}

export interface CreateInstructorData {
  name: string
  email: string
  password?: string
  phone?: string
  bio?: string
  transmission_type: 'manual' | 'automatic' | 'both'
  status?: string
  pdi_status?: string
  address?: string
  postcode?: string
  latitude?: number
  longitude?: number
}

export interface Location {
  id: number
  postcode_sector: string
}

export interface Package {
  id: number
  name: string
  description: string | null
  total_price_pence: number
  lessons_count: number
  lesson_price_pence: number
  formatted_total_price: string
  formatted_lesson_price: string
  active: boolean
  is_platform_package: boolean
  is_bespoke_package: boolean
}

export interface Calendar {
  id: number
  instructor_id: number
  date: string
  calendar_items: CalendarItem[]
  created_at: string
  updated_at: string
}

export interface CalendarItem {
  id: number
  calendar_id: number
  start_time: string
  end_time: string
  is_available: boolean
  status: 'draft' | 'reserved' | 'booked' | 'completed' | null
  notes: string | null
  unavailability_reason: string | null
  created_at: string
  updated_at: string
}

export type RecurrencePattern = 'none' | 'weekly' | 'biweekly' | 'monthly'
export type CalendarItemTypeValue = 'slot' | 'travel' | 'practical_test'

export interface CalendarItemFormData {
  date: string
  start_time: string
  end_time: string
  is_available: boolean
  notes?: string
  unavailability_reason?: string
  recurrence_pattern?: RecurrencePattern
  recurrence_end_date?: string
  travel_time_minutes?: number | null
  is_practical_test?: boolean
}

export interface CalendarItemResponse {
  id: number
  calendar_id: number
  date: string
  start_time: string
  end_time: string
  is_available: boolean
  status: string | null
  item_type: CalendarItemTypeValue
  travel_time_minutes: number | null
  parent_item_id: number | null
  notes: string | null
  unavailability_reason: string | null
  student_name: string | null
  recurrence_pattern: RecurrencePattern
  recurrence_end_date: string | null
  recurrence_group_id: string | null
}

export interface InstructorPayout {
  id: number
  amount_pence: number
  formatted_amount: string
  status: 'pending' | 'paid' | 'failed'
  paid_at: string | null
  created_at: string
  stripe_transfer_id: string | null
  student_name: string | null
  package_name: string | null
  lesson_date: string | null
  lesson_start_time: string | null
  lesson_end_time: string | null
}
