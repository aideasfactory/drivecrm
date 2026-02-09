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
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'

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
      zoom: 13,
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

// Add instructor markers to the map
const addInstructorMarkers = async () => {
  if (!props.instructors || props.instructors.length === 0) return
  
  // Clear existing instructor markers
  markers.value.forEach(marker => marker.setMap(null))
  markers.value = []
  
  // Add markers for each instructor
  for (const instructor of props.instructors) {
    if (!instructor.postcode) continue
    
    try {
      const result = await new Promise((resolve, reject) => {
        geocoder.value.geocode({ address: instructor.postcode + ', UK' }, (results, status) => {
          if (status === 'OK' && results[0]) {
            resolve(results[0])
          } else {
            reject(new Error(`Geocoding failed for ${instructor.postcode}`))
          }
        })
      })
      
      const position = result.geometry.location
      
      // Create custom marker icon
      const markerIcon = {
        url: instructor.isTopPick 
          ? 'http://maps.google.com/mapfiles/ms/icons/yellow-dot.png'
          : 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
        scaledSize: new google.maps.Size(35, 35)
      }
      
      // Create marker
      const marker = new google.maps.Marker({
        position: position,
        map: map.value,
        title: instructor.name,
        icon: markerIcon,
        animation: google.maps.Animation.DROP,
        zIndex: instructor.isTopPick ? 999 : 1
      })
      
      // Create info window content
      const infoContent = `
        <div class="p-3 max-w-xs">
          <div class="flex items-start space-x-3">
            <img src="${instructor.image}" alt="${instructor.name}" class="w-12 h-12 rounded-full object-cover">
            <div class="flex-1">
              <h4 class="font-semibold text-gray-900">${instructor.name}</h4>
              ${instructor.isTopPick ? '<span class="inline-block px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded-full">Top Pick</span>' : ''}
              <div class="mt-1 space-y-1">
                <p class="text-xs text-gray-600">
                  <i class="fa-solid fa-map-marker-alt mr-1"></i>${instructor.location}
                </p>
                <p class="text-xs text-gray-600">
                  <i class="fa-solid fa-road mr-1"></i>${instructor.distance} from you
                </p>
                <p class="text-xs text-green-600 font-medium">
                  <i class="fa-solid fa-calendar-check mr-1"></i>Next: ${instructor.nextAvailable}
                </p>
                <div class="flex space-x-1 mt-1">
                  ${instructor.transmissions.map(t => 
                    `<span class="px-1.5 py-0.5 ${t === 'manual' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'} text-xs font-medium rounded">${t === 'manual' ? 'Manual' : 'Auto'}</span>`
                  ).join('')}
                </div>
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
      
      // Add click listener
      marker.addListener('click', () => {
        infoWindow.value.setContent(infoContent)
        infoWindow.value.open(map.value, marker)
      })
      
      // Store marker reference
      marker.instructorId = instructor.id
      markers.value.push(marker)
      
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