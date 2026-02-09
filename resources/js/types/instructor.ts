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
