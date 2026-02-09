<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-24">
    <div class="mb-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-2">DRIVE Driving School</h3>
      <div class="space-y-2 text-sm text-gray-600">
        <div class="flex items-center">
          <i class="fa-solid fa-map-marker-alt mr-2 text-gray-400"></i>
          <span>London & Surrounding Areas</span>
        </div>
        <div class="flex items-center">
          <i class="fa-solid fa-phone mr-2 text-gray-400"></i>
          <span>020 1234 5678</span>
        </div>
        <div class="flex items-center">
          <i class="fa-solid fa-envelope mr-2 text-gray-400"></i>
          <span>hello@DRIVE.com</span>
        </div>
      </div>
    </div>

    <div class="mb-6">
      <div class="flex items-center space-x-3 mb-4">
        <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium flex items-center">
          <i class="fa-solid fa-shield-alt mr-1"></i>
          DVSA Approved
        </div>
        <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium flex items-center">
          <i class="fa-solid fa-lock mr-1"></i>
          Secure Checkout
        </div>
      </div>
    </div>

    <div>
      <h4 class="font-semibold text-gray-900 mb-3">Why book with us?</h4>
      <ul class="space-y-2 text-sm text-gray-600">
        <li class="flex items-start">
          <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
          <span>Full refund policy - cancel up to 24 hours before</span>
        </li>
        <li class="flex items-start">
          <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
          <span>Flexible scheduling - reschedule anytime</span>
        </li>
        <li class="flex items-start">
          <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
          <span>Qualified DVSA approved instructors</span>
        </li>
        <li class="flex items-start">
          <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5 flex-shrink-0"></i>
          <span>Modern dual-control vehicles</span>
        </li>
      </ul>
    </div>

    <!-- Extra slot for step-specific content like booking summary -->
    <slot name="extra-content"></slot>
  </div>
</template>

<script setup>
// This is a reusable component for the left sidebar in onboarding steps 4-6
// It provides consistent branding and information across all these steps
</script>