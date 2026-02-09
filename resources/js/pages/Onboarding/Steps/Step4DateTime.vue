<template>
    <div>
        <!-- Instructor Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Your Instructor</h3>
                <button 
                    v-if="!showInstructorDropdown"
                    @click="showInstructorDropdown = true"
                    class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center"
                >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Change
                </button>
            </div>
            
            <div v-if="!showInstructorDropdown" class="space-y-4">
                <div class="flex items-start space-x-4">
                    <img 
                        :src="selectedInstructor.avatar" 
                        :alt="selectedInstructor.name" 
                        class="w-16 h-16 rounded-full object-cover"
                    >
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 mb-1">{{ selectedInstructor.name }}</h4>
                        <div class="flex items-center space-x-1 mb-2">
                            <div class="flex text-yellow-400">
                                <svg v-for="i in 5" :key="i" class="w-3 h-3 fill-current" viewBox="0 0 20 20">
                                    <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                </svg>
                            </div>
                            <span class="text-xs text-gray-600 ml-1">{{ selectedInstructor.rating }} ({{ selectedInstructor.reviews }} reviews)</span>
                        </div>
                        <div class="flex items-center space-x-3 text-xs text-gray-600">
                            <span class="flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                </svg>
                                {{ selectedInstructor.experience }} years exp.
                            </span>
                            <span class="flex items-center">
                                <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                {{ selectedInstructor.passRate }}% pass rate
                            </span>
                        </div>
                    </div>
                </div>
                
                <p class="text-sm text-gray-600 leading-relaxed">{{ selectedInstructor.bio }}</p>
                
                <div class="flex flex-wrap gap-2">
                    <span v-for="tag in selectedInstructor.tags" :key="tag" 
                          class="px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded-full">
                        {{ tag }}
                    </span>
                </div>
            </div>
            
            <!-- Instructor Dropdown -->
            <div v-else class="mt-4 space-y-3 max-h-96 overflow-y-auto">
                <div class="text-sm text-gray-600 mb-3 pb-3 border-b">
                    <p class="font-medium text-gray-900 mb-1">Select a different instructor</p>
                    <p class="text-xs">Choose from available instructors in your area</p>
                </div>
                
                <div v-for="instructor in availableInstructors" :key="instructor.id"
                     @click="selectInstructor(instructor)"
                     class="cursor-pointer p-3 border-2 rounded-lg transition-colors"
                     :class="selectedInstructor.id === instructor.id ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-blue-600'">
                    <div class="flex items-start space-x-3">
                        <img :src="instructor.avatar" :alt="instructor.name" 
                             class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h5 class="font-semibold text-gray-900 text-sm">{{ instructor.name }}</h5>
                                <span v-if="selectedInstructor.id === instructor.id" 
                                      class="px-2 py-0.5 bg-blue-600 text-white text-xs rounded-full flex-shrink-0">
                                    Current
                                </span>
                                <span v-else 
                                      class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full flex-shrink-0">
                                    Available
                                </span>
                            </div>
                            <div class="flex items-center space-x-1 mb-1">
                                <svg class="w-3 h-3 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                    <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                </svg>
                                <span class="text-xs text-gray-600">{{ instructor.rating }} ({{ instructor.reviews }})</span>
                            </div>
                            <p class="text-xs text-gray-600">{{ instructor.experience }} years exp. • {{ instructor.passRate }}% pass rate</p>
                        </div>
                    </div>
                </div>
                
                <button @click="showInstructorDropdown = false" 
                        class="w-full mt-2 px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Choose your lesson start date</h1>
                <p class="text-gray-600">Select when you'd like to begin your driving lessons. We'll coordinate exact times with your instructor after payment.</p>
            </div>

            <div class="space-y-8">
                <!-- Date Selection -->
                <div id="calendar-section">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Select a date for your lessons</h3>
                        
                        <button @click="showCalendarSheet = true" 
                                class="flex items-center space-x-2 px-4 py-2 text-sm text-gray-600 hover:text-blue-600 border border-gray-300 rounded-lg hover:border-blue-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Month View</span>
                        </button>
                    </div>
                    
                    <div class="flex items-center">
                        <button @click="previousWeek" :disabled="weekOffset === 0"
                                class="p-2 rounded-full hover:bg-gray-100 mr-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        
                        <div class="flex-1 overflow-hidden">
                            <div class="flex space-x-3 transition-transform duration-300" :style="carouselStyle">
                                <div v-for="date in visibleDates" :key="date.dateString"
                                     @click="date.available && selectDate(date.dateString)"
                                     class="flex-shrink-0 p-4 border-2 rounded-lg text-center min-w-[100px] transition-colors"
                                     :class="getDateClasses(date)">
                                    <div class="text-xs mb-1" :class="getDateTextClasses(date)">{{ date.dayName }}</div>
                                    <div class="text-lg font-semibold" :class="getDateTextClasses(date)">{{ date.day }}</div>
                                    <div class="text-xs" :class="getDateTextClasses(date)">{{ date.monthName }}</div>
                                </div>
                            </div>
                        </div>
                        
                        <button @click="nextWeek" 
                                class="p-2 rounded-full hover:bg-gray-100 ml-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Time Slots -->
                <div v-if="form.date" id="timeslot-section" class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Available time slots with {{ selectedInstructor.name.split(' ')[0] }}
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">{{ formatSelectedDate }} • Select your preferred lesson times</p>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <button v-for="slot in timeSlots" :key="slot.time"
                                @click="!slot.booked && selectTime(slot.time)"
                                :disabled="slot.booked"
                                class="p-3 border-2 rounded-lg transition-colors text-center"
                                :class="getTimeSlotClasses(slot)">
                            <div class="font-medium" :class="form.time === slot.time ? 'text-blue-600' : (slot.booked ? 'text-gray-500' : 'text-gray-900')">
                                {{ slot.displayTime }}
                            </div>
                            <div class="text-xs" :class="getTimeSlotStatusClasses(slot)">
                                {{ getTimeSlotStatus(slot) }}
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Reservation Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium mb-1">Lesson slots reserved</p>
                            <p>We'll hold your lesson slots for up to 24 hours while you complete payment. Your instructor {{ selectedInstructor.name.split(' ')[0] }} will coordinate exact times with you after booking confirmation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Sheet Modal -->
        <Teleport to="body">
            <div v-if="showCalendarSheet" class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black bg-opacity-50" @click="showCalendarSheet = false"></div>
                <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl shadow-2xl transform transition-transform duration-300 max-h-[80vh] overflow-y-auto"
                     :class="showCalendarSheet ? 'translate-y-0' : 'translate-y-full'">
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 rounded-t-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold text-gray-900">Select a date</h3>
                            <button @click="showCalendarSheet = false" 
                                    class="p-2 hover:bg-gray-100 rounded-full">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <button @click="previousMonth" class="p-2 hover:bg-gray-100 rounded-full">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <h4 class="text-lg font-semibold text-gray-900">{{ currentMonthYear }}</h4>
                            <button @click="nextMonth" class="p-2 hover:bg-gray-100 rounded-full">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-7 gap-2 mb-2">
                            <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                                 class="text-center text-xs font-medium text-gray-500 py-2">
                                {{ day }}
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-7 gap-2">
                            <div v-for="n in firstDayOfMonth" :key="`empty-${n}`"></div>
                            <div v-for="day in daysInMonth" :key="day"
                                 @click="isDateAvailable(day) && selectDateFromCalendar(day)"
                                 class="p-3 text-center rounded-lg transition-colors"
                                 :class="getCalendarDayClasses(day)">
                                {{ day }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    form: Object
});

const emit = defineEmits(['updateForm']);

const showInstructorDropdown = ref(false);
const showCalendarSheet = ref(false);
const weekOffset = ref(0);
const currentViewMonth = ref(new Date());

const selectedInstructor = ref({
    id: 1,
    name: 'Sarah Thompson',
    avatar: 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-5.jpg',
    rating: 4.9,
    reviews: 127,
    experience: 8,
    passRate: 95,
    bio: 'Patient and encouraging instructor specializing in nervous learners. Excellent track record with first-time test takers.',
    tags: ['Manual', 'Automatic', 'Intensive courses']
});

const availableInstructors = ref([
    {
        id: 1,
        name: 'Sarah Thompson',
        avatar: 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-5.jpg',
        rating: 4.9,
        reviews: 127,
        experience: 8,
        passRate: 95,
        bio: 'Patient and encouraging instructor specializing in nervous learners.',
        tags: ['Manual', 'Automatic', 'Intensive courses']
    },
    {
        id: 2,
        name: 'James Wilson',
        avatar: 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-2.jpg',
        rating: 4.8,
        reviews: 89,
        experience: 5,
        passRate: 92,
        bio: 'Experienced instructor with a calm and methodical approach.',
        tags: ['Manual', 'Automatic']
    },
    {
        id: 3,
        name: 'Emma Davies',
        avatar: 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-1.jpg',
        rating: 5.0,
        reviews: 43,
        experience: 3,
        passRate: 97,
        bio: 'Young and energetic instructor who makes learning fun.',
        tags: ['Automatic', 'Intensive courses']
    },
    {
        id: 4,
        name: 'Michael Brown',
        avatar: 'https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-8.jpg',
        rating: 4.7,
        reviews: 156,
        experience: 12,
        passRate: 91,
        bio: 'Veteran instructor with extensive experience in all conditions.',
        tags: ['Manual', 'Automatic', 'Pass Plus']
    }
]);

const timeSlots = ref([
    { time: '09:00', displayTime: '9:00 AM', booked: false },
    { time: '10:00', displayTime: '10:00 AM', booked: false },
    { time: '11:00', displayTime: '11:00 AM', booked: false },
    { time: '14:00', displayTime: '2:00 PM', booked: false },
    { time: '15:00', displayTime: '3:00 PM', booked: false },
    { time: '16:00', displayTime: '4:00 PM', booked: false },
    { time: '17:00', displayTime: '5:00 PM', booked: true },
    { time: '18:00', displayTime: '6:00 PM', booked: false }
]);

const visibleDates = computed(() => {
    const dates = [];
    const today = new Date();
    const startDate = new Date(today);
    startDate.setDate(startDate.getDate() + (weekOffset.value * 7));
    
    for (let i = 0; i < 7; i++) {
        const date = new Date(startDate);
        date.setDate(date.getDate() + i);
        
        const daysDiff = Math.floor((date - today) / (1000 * 60 * 60 * 24));
        const available = daysDiff >= 2 && daysDiff <= 7;
        
        dates.push({
            date: date,
            dateString: formatDateString(date),
            dayName: date.toLocaleDateString('en-US', { weekday: 'short' }),
            day: date.getDate(),
            monthName: date.toLocaleDateString('en-US', { month: 'short' }),
            available: available
        });
    }
    
    return dates;
});

const carouselStyle = computed(() => ({
    transform: `translateX(0px)`
}));

const formatSelectedDate = computed(() => {
    if (!props.form.date) return '';
    const date = new Date(props.form.date);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
});

const currentMonthYear = computed(() => {
    return currentViewMonth.value.toLocaleDateString('en-US', { 
        month: 'long', 
        year: 'numeric' 
    });
});

const firstDayOfMonth = computed(() => {
    const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), 1);
    return date.getDay();
});

const daysInMonth = computed(() => {
    const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth() + 1, 0);
    return date.getDate();
});

function formatDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getDateClasses(date) {
    if (!date.available) {
        return 'border-gray-300 bg-gray-100 cursor-not-allowed opacity-60';
    }
    if (props.form.date === date.dateString) {
        return 'border-blue-600 bg-blue-50 cursor-pointer';
    }
    return 'border-gray-200 hover:border-blue-600 hover:bg-blue-50 cursor-pointer';
}

function getDateTextClasses(date) {
    if (!date.available) {
        return 'text-gray-400';
    }
    if (props.form.date === date.dateString) {
        return 'text-blue-600';
    }
    return 'text-gray-600';
}

function getTimeSlotClasses(slot) {
    if (slot.booked) {
        return 'border-gray-300 bg-gray-100 cursor-not-allowed opacity-60';
    }
    if (props.form.time === slot.time) {
        return 'border-blue-600 bg-blue-50';
    }
    return 'border-gray-200 hover:border-blue-600 hover:bg-blue-50';
}

function getTimeSlotStatus(slot) {
    if (slot.booked) return 'Booked';
    if (props.form.time === slot.time) return 'Selected';
    return 'Available';
}

function getTimeSlotStatusClasses(slot) {
    if (slot.booked) return 'text-gray-500';
    if (props.form.time === slot.time) return 'text-blue-600';
    return 'text-green-600';
}

function getCalendarDayClasses(day) {
    const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), day);
    const today = new Date();
    const daysDiff = Math.floor((date - today) / (1000 * 60 * 60 * 24));
    
    if (daysDiff < 2 || daysDiff > 7) {
        return 'text-gray-300 cursor-not-allowed';
    }
    
    const dateString = formatDateString(date);
    if (props.form.date === dateString) {
        return 'bg-blue-600 text-white font-semibold cursor-pointer';
    }
    
    return 'hover:bg-blue-50 text-gray-900 cursor-pointer';
}

function isDateAvailable(day) {
    const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), day);
    const today = new Date();
    const daysDiff = Math.floor((date - today) / (1000 * 60 * 60 * 24));
    return daysDiff >= 2 && daysDiff <= 7;
}

function selectDate(dateString) {
    emit('updateForm', { date: dateString });
}

function selectTime(time) {
    emit('updateForm', { time: time });
}

function selectInstructor(instructor) {
    selectedInstructor.value = instructor;
    showInstructorDropdown.value = false;
    emit('updateForm', { instructor_id: instructor.id });
}

function selectDateFromCalendar(day) {
    const date = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth(), day);
    const dateString = formatDateString(date);
    selectDate(dateString);
    showCalendarSheet.value = false;
}

function previousWeek() {
    if (weekOffset.value > 0) {
        weekOffset.value--;
    }
}

function nextWeek() {
    weekOffset.value++;
}

function previousMonth() {
    currentViewMonth.value = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth() - 1, 1);
}

function nextMonth() {
    currentViewMonth.value = new Date(currentViewMonth.value.getFullYear(), currentViewMonth.value.getMonth() + 1, 1);
}

onMounted(() => {
    // Initialize with first available date if not set
    if (!props.form.date) {
        const firstAvailable = visibleDates.value.find(d => d.available);
        if (firstAvailable) {
            selectDate(firstAvailable.dateString);
        }
    }
    // Initialize with first available time slot if not set
    if (!props.form.time) {
        const firstAvailable = timeSlots.value.find(s => !s.booked);
        if (firstAvailable) {
            selectTime(firstAvailable.time);
        }
    }
    // Initialize instructor if not set
    if (!props.form.instructor_id) {
        emit('updateForm', { instructor_id: selectedInstructor.value.id });
    }
});
</script>