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
  transmission_type: 'manual' | 'automatic'
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
  transmission_type: 'manual' | 'automatic'
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
  status: 'draft' | 'reserved' | 'booked' | null
  created_at: string
  updated_at: string
}

export interface CalendarItemFormData {
  date: string
  start_time: string
  end_time: string
}
