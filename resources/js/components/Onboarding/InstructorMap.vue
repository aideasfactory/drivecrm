<template>
  <div class="relative h-full w-full">
    <div ref="mapContainer" class="h-full w-full rounded-lg"></div>

    <!-- Map Loading State -->
    <div v-if="loading" class="absolute inset-0 bg-gray-100 rounded-lg flex items-center justify-center">
      <div class="text-center">
        <i class="fa-solid fa-spinner fa-spin text-4xl text-blue-600 mb-4"></i>
        <p class="text-gray-600">Loading map...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="absolute inset-0 bg-gray-100 rounded-lg flex items-center justify-center">
      <div class="text-center">
        <i class="fa-solid fa-map-marked-alt text-6xl text-gray-400 mb-4"></i>
        <p class="text-gray-600">Unable to load map</p>
        <p class="text-sm text-gray-500 mt-2">{{ error }}</p>
      </div>
    </div>

    <!-- Marker Legend -->
    <div
      v-if="!loading && !error"
      class="absolute bottom-3 left-3 bg-white/95 rounded-lg shadow-md px-3 py-2 text-xs text-gray-700 space-y-1.5"
    >
      <div class="flex items-center gap-2">
        <span class="h-3 w-3 rounded-full shrink-0" :style="{ background: TRANSMISSION_COLORS.manual }"></span>
        Manual
      </div>
      <div class="flex items-center gap-2">
        <span class="h-3 w-3 rounded-full shrink-0" :style="{ background: TRANSMISSION_COLORS.automatic }"></span>
        Automatic
      </div>
      <div class="flex items-center gap-2">
        <span class="h-3 w-3 rounded-full shrink-0" :style="{ background: TRANSMISSION_COLORS.both }"></span>
        Manual &amp; Automatic
      </div>
      <div class="flex items-center gap-2">
        <Flame class="h-3.5 w-3.5 shrink-0 text-amber-500" />
        Top Pick
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { Flame } from 'lucide-vue-next'

const props = defineProps({
  apiKey: {
    type: String,
    required: true
  },
  userPostcode: {
    type: String,
    required: true
  },
  instructors: {
    type: Array,
    default: () => []
  },
  selectedInstructorId: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['instructor-selected', 'map-loaded'])

const mapContainer = ref(null)
const map = ref(null)
const markers = ref([])
const userMarker = ref(null)
const geocoder = ref(null)
const infoWindow = ref(null)
const loading = ref(true)
const error = ref(null)

let googleMapsLoaded = false

const TRANSMISSION_COLORS = {
  manual: '#3b82f6',
  automatic: '#22c55e',
  both: '#8b5cf6'
}
const FALLBACK_PIN_COLOR = '#6b7280'
const TOP_PICK_RING_COLOR = '#f59e0b'

// lucide "flame" path (24x24 viewBox)
const FLAME_PATH = 'M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z'

// Load Google Maps Script
const loadGoogleMapsScript = () => {
  return new Promise((resolve, reject) => {
    if (window.google && window.google.maps) {
      googleMapsLoaded = true
      resolve()
      return
    }

    // Check if script is already being loaded
    const existingScript = document.querySelector('script[src*="maps.googleapis.com"]')
    if (existingScript) {
      existingScript.addEventListener('load', () => {
        googleMapsLoaded = true
        resolve()
      })
      return
    }

    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${props.apiKey}&libraries=places`
    script.async = true
    script.defer = true

    script.onload = () => {
      googleMapsLoaded = true
      resolve()
    }

    script.onerror = () => {
      reject(new Error('Failed to load Google Maps'))
    }

    document.head.appendChild(script)
  })
}

// Initialize the map
const initializeMap = async () => {
  try {
    loading.value = true
    error.value = null

    if (!props.apiKey) {
      throw new Error('Google Maps API key is required')
    }

    await loadGoogleMapsScript()

    if (!mapContainer.value) {
      throw new Error('Map container not found')
    }

    // Create the map
    map.value = new google.maps.Map(mapContainer.value, {
      zoom: 8,
      center: { lat: 51.5074, lng: -0.1278 }, // Default to London
      mapTypeControl: false,
      fullscreenControl: false,
      streetViewControl: false,
      styles: [
        {
          featureType: 'poi',
          stylers: [{ visibility: 'off' }]
        }
      ]
    })

    // Initialize geocoder and info window
    geocoder.value = new google.maps.Geocoder()
    infoWindow.value = new google.maps.InfoWindow()

    // Geocode user postcode and add marker
    await geocodeAndAddUserMarker()

    // Add instructor markers
    await addInstructorMarkers()

    // Fit bounds to show all markers
    fitMapBounds()

    loading.value = false
    emit('map-loaded')
  } catch (err) {
    console.error('Error initializing map:', err)
    error.value = err.message || 'Failed to initialize map'
    loading.value = false
  }
}

// Geocode postcode and add user marker
const geocodeAndAddUserMarker = async () => {
  if (!props.userPostcode || !geocoder.value) return

  try {
    const result = await new Promise((resolve, reject) => {
      geocoder.value.geocode({ address: props.userPostcode + ', UK' }, (results, status) => {
        if (status === 'OK' && results[0]) {
          resolve(results[0])
        } else {
          reject(new Error(`Geocoding failed: ${status}`))
        }
      })
    })

    const position = result.geometry.location

    // Add user marker (red pin)
    userMarker.value = new google.maps.Marker({
      position: position,
      map: map.value,
      title: 'Your Location',
      icon: {
        url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
        scaledSize: new google.maps.Size(40, 40)
      },
      animation: google.maps.Animation.DROP,
      zIndex: 1000
    })

    // Center map on user location
    map.value.setCenter(position)

    // Add info window for user marker
    userMarker.value.addListener('click', () => {
      infoWindow.value.setContent(`
        <div class="p-2">
          <h4 class="font-semibold text-gray-900">Your Location</h4>
          <p class="text-sm text-gray-600">${props.userPostcode}</p>
        </div>
      `)
      infoWindow.value.open(map.value, userMarker.value)
    })
  } catch (err) {
    console.error('Error geocoding user postcode:', err)
  }
}

// Instructors store latitude/longitude as decimal strings; geocoding is only
// a fallback for records created before coordinates were captured
const resolveInstructorPosition = async (instructor) => {
  const lat = parseFloat(instructor.latitude)
  const lng = parseFloat(instructor.longitude)

  if (Number.isFinite(lat) && Number.isFinite(lng)) {
    return { lat, lng }
  }

  if (!instructor.postcode || !geocoder.value) return null

  const result = await new Promise((resolve, reject) => {
    geocoder.value.geocode({ address: instructor.postcode + ', UK' }, (results, status) => {
      if (status === 'OK' && results[0]) {
        resolve(results[0])
      } else {
        reject(new Error(`Geocoding failed for ${instructor.postcode}`))
      }
    })
  })

  return result.geometry.location
}

const transmissionsFor = (instructor) => {
  if (instructor.transmission_type === 'both') {
    return ['manual', 'automatic']
  }
  return instructor.transmission_type ? [instructor.transmission_type] : []
}

// Pin body coloured by transmission type; top picks get a flame glyph and an
// amber ring instead of the plain white dot
const buildMarkerIcon = (instructor) => {
  const color = TRANSMISSION_COLORS[instructor.transmission_type] ?? FALLBACK_PIN_COLOR
  const isTopPick = !!instructor.priority
  const width = isTopPick ? 38 : 32
  const height = isTopPick ? 52 : 44

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 44" width="${width}" height="${height}">
    <g transform="translate(2,2)">
      <path d="M14 0C6.3 0 0 6.3 0 14c0 10.5 14 26 14 26s14-15.5 14-26C28 6.3 21.7 0 14 0z" fill="${color}" stroke="${isTopPick ? TOP_PICK_RING_COLOR : '#ffffff'}" stroke-width="2"/>
      ${isTopPick
        ? `<path d="${FLAME_PATH}" fill="#ffffff" transform="translate(6.8,6.8) scale(0.6)"/>`
        : '<circle cx="14" cy="14" r="5" fill="#ffffff"/>'}
    </g>
  </svg>`

  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    scaledSize: new google.maps.Size(width, height),
    anchor: new google.maps.Point(width / 2, height)
  }
}

const buildInfoContent = (instructor) => {
  const image = instructor.avatar
    || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(instructor.name || '') + '&background=0D8ABC&color=fff'

  const transmissionBadges = transmissionsFor(instructor).map(t =>
    `<span class="px-1.5 py-0.5 ${t === 'manual' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'} text-xs font-medium rounded">${t === 'manual' ? 'Manual' : 'Auto'}</span>`
  ).join('')

  const nextAvailableRow = instructor.next_available
    ? `<p class="text-xs text-green-600 font-medium">
        <i class="fa-solid fa-calendar-check mr-1"></i>Next: ${instructor.next_available}
      </p>`
    : ''

  return `
    <div class="p-3 max-w-xs">
      <div class="flex items-start space-x-3">
        <img src="${image}" alt="${instructor.name}" class="w-12 h-12 rounded-full object-cover">
        <div class="flex-1">
          <h4 class="font-semibold text-gray-900">${instructor.name}</h4>
          ${instructor.priority ? '<span class="inline-block px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded-full">Top Pick</span>' : ''}
          <div class="mt-1 space-y-1">
            <p class="text-xs text-gray-600">
              <i class="fa-solid fa-map-marker-alt mr-1"></i>${instructor.address ?? ''} ${instructor.postcode ? '• ' + instructor.postcode : ''}
            </p>
            ${nextAvailableRow}
            <div class="flex space-x-1 mt-1">${transmissionBadges}</div>
          </div>
          <button
            onclick="window.selectInstructorFromMap(${instructor.id})"
            class="mt-2 w-full bg-blue-600 text-white px-3 py-1.5 rounded text-xs font-medium hover:bg-blue-700"
          >
            Select Instructor
          </button>
        </div>
      </div>
    </div>
  `
}

// Add instructor markers to the map
const addInstructorMarkers = async () => {
  // Clear existing instructor markers
  markers.value.forEach(marker => marker.setMap(null))
  markers.value = []

  if (!props.instructors || props.instructors.length === 0) return

  for (const instructor of props.instructors) {
    try {
      const position = await resolveInstructorPosition(instructor)

      if (!position) continue

      const marker = new google.maps.Marker({
        position: position,
        map: map.value,
        title: instructor.name,
        icon: buildMarkerIcon(instructor),
        animation: google.maps.Animation.DROP,
        zIndex: instructor.priority ? 999 : 1
      })

      // Store the reference immediately so a later failure can never orphan
      // an already-placed marker (it must stay clearable on re-filter)
      marker.instructorId = instructor.id
      markers.value.push(marker)

      const infoContent = buildInfoContent(instructor)

      marker.addListener('click', () => {
        infoWindow.value.setContent(infoContent)
        infoWindow.value.open(map.value, marker)
      })

      // Highlight selected instructor
      if (props.selectedInstructorId === instructor.id) {
        marker.setAnimation(google.maps.Animation.BOUNCE)
        setTimeout(() => {
          marker.setAnimation(null)
        }, 2000)
      }
    } catch (err) {
      console.error(`Error adding marker for instructor ${instructor.name}:`, err)
    }
  }
}

// Fit map bounds to show all markers
const fitMapBounds = () => {
  if (!map.value) return

  const bounds = new google.maps.LatLngBounds()

  // Include user marker
  if (userMarker.value) {
    bounds.extend(userMarker.value.getPosition())
  }

  // Include instructor markers
  markers.value.forEach(marker => {
    bounds.extend(marker.getPosition())
  })

  if (!bounds.isEmpty()) {
    map.value.fitBounds(bounds)

    // Don't zoom in too far
    const listener = google.maps.event.addListener(map.value, 'idle', () => {
      if (map.value.getZoom() > 15) {
        map.value.setZoom(15)
      }
      google.maps.event.removeListener(listener)
    })
  }
}

// Handle instructor selection from map
window.selectInstructorFromMap = (instructorId) => {
  emit('instructor-selected', instructorId)

  // Highlight the selected marker
  markers.value.forEach(marker => {
    if (marker.instructorId === instructorId) {
      marker.setAnimation(google.maps.Animation.BOUNCE)
      setTimeout(() => {
        marker.setAnimation(null)
      }, 2000)
    } else {
      marker.setAnimation(null)
    }
  })
}

// Watch for selected instructor changes
watch(() => props.selectedInstructorId, (newId) => {
  if (!map.value || !markers.value.length) return

  markers.value.forEach(marker => {
    if (marker.instructorId === newId) {
      marker.setAnimation(google.maps.Animation.BOUNCE)
      setTimeout(() => {
        marker.setAnimation(null)
      }, 2000)

      // Pan to selected instructor
      map.value.panTo(marker.getPosition())
      map.value.setZoom(14)
    } else {
      marker.setAnimation(null)
    }
  })
})

// Watch for instructor list changes
watch(() => props.instructors, async () => {
  if (map.value) {
    await addInstructorMarkers()
    fitMapBounds()
  }
}, { deep: true })

// Initialize map when component is mounted
onMounted(() => {
  nextTick(() => {
    initializeMap()
  })
})
</script>
