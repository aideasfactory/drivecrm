# Agent Guide: Frontend (Vue + Inertia)

## 1. Package Identity
- **Role**: Client-side application powered by Inertia.js v2.
- **Stack**: Vue 3 (Composition API), TypeScript, Tailwind CSS v4.
- **Router**: Inertia (Server-driven routing).

## 2. Setup & Run
```bash
*# Run dev server*
npm run dev

*# Build for production*
npm run build

```

## 3. Patterns & Conventions

### Mandatory UI Patterns (Strict Enforcement)
- **1. Shadcn First**: Always use components from `@/components/ui/**`. Never build raw HTML/Tailwind replacements for existing UI components.
- **2. Sheet for Forms (MANDATORY)**:
  - ✅ DO: Use `Sheet` component (slideout from right/left) for ALL create/edit/update forms
  - ✅ DO: Sheet slides from right side by default: `<SheetContent side="right">`
  - ✅ DO: Add padding classes `px-6 py-4` to ALL forms inside sheets
  - ✅ DO: ALWAYS add an accompanying icon to SheetTitle (e.g., `<SheetTitle class="flex items-center gap-2"><Menu class="h-5 w-5" />Title</SheetTitle>`)
  - ❌ DON'T: Use Dialog/Modal for forms - only use Dialog for confirmations/alerts
  - ❌ DON'T: Forget form padding - forms without `px-6 py-4` are incorrect
  - ❌ DON'T: Create SheetTitle without an icon
  - Pattern: `<Sheet v-model:open="isOpen"><SheetContent side="right"><SheetHeader><SheetTitle class="flex items-center gap-2"><Icon class="h-5 w-5" />Title</SheetTitle></SheetHeader><form @submit.prevent="handleSubmit" class="mt-6 space-y-6 px-6 py-4">...</form></SheetContent></Sheet>`
- **3. Button Styling**:
  - ✅ DO: Use `variant` prop (default, secondary, destructive, outline, ghost, link).
  - ❌ DON'T: Override colors with utility classes like `bg-blue-500` or `text-red-600`.
- **4. Button Preloaders**:
  - All buttons triggering async actions MUST show a loading state.
  - Pattern: `<Button :disabled="form.processing"><Loader2 v-if="form.processing" class="animate-spin mr-2" /> Save</Button>`
  - Always give buttons icons
  - Use `min-w-[...]` classes to prevent button size changes when loading
- **5. API Feedback (Toasts)**:
  - All API interactions (creates/updates/deletes) MUST trigger a toast notification.
  - Use a Toast library (e.g., `sonner`). If missing, install it immediately.

**Example:**
```vue
<script setup lang="ts">
import { toast } from '@/components/ui/sonner'
import { store } from '@/actions/ContactController'

const handleSubmit = async () => {
  try {
    await store.submit()
    toast.success('Contact saved successfully!')
  } catch (error) {
    toast.error('Failed to save contact')
  }
}
</script>
```

- **6. Custom Components**:
  - Check for specialized variations before styling generic ones.
  - Example: Use `CardGradient` (if available) instead of adding gradient classes to a standard `Card`.
- **7. Tables**:
  - ALWAYS use Shadcn Table components (`Table`, `TableHeader`, `TableRow`, `TableCell`, etc.) from `@/components/ui/table`, never `<table>` tags or div-soups.

### Component Architecture
- **UI Library**: `resources/js/components/ui` (Shadcn-like).
  - ✅ DO: Use `Button` from `@/components/ui/button/Button.vue`.
  - ❌ DON'T: Hardcode Tailwind styles for generic UI elements repeatedly.
- **App Components**: `resources/js/components` (Domain specific).
  - ✅ DO: `AppSidebar.vue`, `NavUser.vue`.
- **Pages**: `resources/js/pages`. Use PascalCase matching Controller names.

### Component Data Loading (Self-Managed Data)
- **Pattern**: Each component is responsible for loading its own data
  - ✅ DO: Fetch data in component's `onMounted` lifecycle hook
  - ✅ DO: Show skeleton/loading states while data is being fetched
  - ✅ DO: Use `ref()` for loading state management (`const loading = ref(true)`)
  - ✅ DO: Implement polling if real-time data updates are needed
  - ❌ DON'T: Rely on parent components to pass all data down as props
  - ❌ DON'T: Show empty content while loading (use skeletons instead)

**Example Pattern:**
```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { index } from '@/actions/App/Http/Controllers/DataController'
import { Skeleton } from '@/components/ui/skeleton'

const data = ref<DataType[]>([])
const loading = ref(true)

const loadData = async () => {
  loading.value = true
  try {
    const response = await index.visit()
    data.value = response.props.data
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})

// Optional: Polling for real-time updates
// const startPolling = () => {
//   setInterval(loadData, 5000) // Poll every 5 seconds
// }
</script>

<template>
  <div v-if="loading">
    <Skeleton class="h-10 w-full" />
    <Skeleton class="h-10 w-full mt-2" />
  </div>
  <div v-else>
    <!-- Actual content -->
  </div>
</template>
```

**Benefits:**
- Components are self-contained and reusable
- Parallel data loading (multiple components load independently)
- Better user experience with skeleton states
- Easy to implement polling/real-time updates
- No prop-drilling for deeply nested data

### Data & Actions (Wayfinder)
- **Auto-Generated**: We use Laravel Wayfinder to sync routes/controllers to TS.
- **Imports**: Use explicit imports from `@/actions` or `@/routes`.
  - ✅ DO: `import { store } from '@/actions/App/Http/Controllers/PostController'`
  - ✅ DO: `import { show } from '@/routes/post'`
  - ❌ DON'T: Hardcode URL strings like `'/posts/' + id`.

### Forms (Inertia)
- **Component**: Use the `<Form>` component for automatic error handling/processing.
  - ✅ Example:
    ```vue
    <script setup lang="ts">
    import { Form } from '@/components/ui/form' // or Inertia form wrapper
    import { store } from '@/actions/App/Http/Controllers/ContactController'
    </script>
    <template>
      <Form v-bind="store.form()">
        <input name="email" />
      </Form>
    </template>
    ```

### API Calls (MANDATORY - No Manual CSRF!)
- **NEVER use fetch() with manual CSRF tokens** - Laravel handles CSRF automatically
- **Two Accepted Patterns** - Choose based on context:

**Pattern 1: Inertia Router**
**When to use:** Form submissions that affect **page-level state** or need navigation
- Creating/updating main resources (instructors, students, bookings)
- Actions that redirect to new pages
- When you want Inertia to auto-refresh page props
- Full-page forms in Sheets/Modals

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

// ✅ Example: Creating an instructor (affects page-level state)
router.post('/instructors', formData, {
    preserveScroll: true,
    onSuccess: () => {
        toast.success('Instructor created!')
        // Inertia automatically updates page props
    },
    onError: (errors) => {
        // Handle validation errors
    },
})

router.put(`/instructors/${id}`, formData, { ... })
router.delete(`/instructors/${id}`, { ... })
</script>
```

**Pattern 2: Axios**
**When to use:** Self-loading components with **local state management**
- Tab components that fetch their own data (coverage areas, packages, etc.)
- Nested CRUD operations within tabs (add/delete items in a list)
- API calls that don't need page navigation
- When component manages its own `ref()` state

```vue
<script setup lang="ts">
import axios from 'axios'

// ✅ Example: Self-loading tab managing locations
const locations = ref<Location[]>([])
const loading = ref(true)

onMounted(async () => {
    const response = await axios.get(`/instructors/${id}/locations`)
    locations.value = response.data.locations
    loading.value = false
})

// ✅ Example: Adding location (updates local state)
const response = await axios.post(`/instructors/${id}/locations`, {
    postcode_sector: 'TS7'
})
locations.value.push(response.data.location) // Update local state

// Error handling
try {
    const response = await axios.post('/endpoint', data)
    toast.success('Saved!')
} catch (error: any) {
    const message = error.response?.data?.message || 'Failed'
    toast.error(message)
}
</script>
```

**Quick Decision Guide:**
- 📄 **Page-level form** (Sheet creating main resource)? → Use **Inertia Router**
- 🔄 **Self-loading tab** (manages own data)? → Use **Axios**
- 🚀 **Need to redirect** after action? → Use **Inertia Router**
- 📊 **Updating local state** only? → Use **Axios**

**❌ NEVER DO THIS:**
```vue
// ❌ DON'T: Manual CSRF tokens
fetch(url, {
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')...
    }
})

// ❌ DON'T: Manual headers with fetch
fetch(url, {
    headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }
})
```

**Why?** Laravel automatically configures both Inertia and Axios with CSRF tokens. Manual handling is unnecessary and error-prone.

### Styling (Tailwind v4)
- **Config**: No `tailwind.config.js`. Theme is in CSS variables.
- **Dark Mode**: Use `dark:` variant.
- **Spacing**: Use `gap-*` utilities in flex/grid containers.

## 4. Touch Points
- **Entry**: `resources/js/app.ts`
- **Layouts**: `resources/js/layouts/` (e.g., `AppShell.vue`)
- **Types**: `resources/js/types/`
- **Wayfinder**: `resources/js/wayfinder/` (Do not edit manually)

## 5. JIT Index Hints
- Find Vue Component: `rg -g "*.vue" "defineProps"`
- Find UI Component: `ls resources/js/components/ui`
- Find a Page: `find resources/js/pages -name "*.vue"`
- Find Usage of Route: `rg "from '@/routes"`

## 6. Common Gotchas
- **Links**: Always use `<Link href="...">` or `router.visit()`. Never `<a>`.
- **Reactivity**: Use `ref()` and `computed()`. Avoid `reactive()` unless necessary.
- **Icons**: Use `lucide-vue-next`.