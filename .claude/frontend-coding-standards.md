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
- **2. Button Styling**:
  - ✅ DO: Use `variant` prop (default, secondary, destructive, outline, ghost, link).
  - ❌ DON'T: Override colors with utility classes like `bg-blue-500` or `text-red-600`.
- **3. Button Preloaders**:
  - All buttons triggering async actions MUST show a loading state.
  - Pattern: `<Button :disabled="form.processing"><Loader2 v-if="form.processing" class="animate-spin mr-2" /> Save</Button>`
  - Always give buttons icons
- **4. API Feedback (Toasts)**:
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

- **5. Custom Components**:
  - Check for specialized variations before styling generic ones.
  - Example: Use `CardGradient` (if available) instead of adding gradient classes to a standard `Card`.
- **6. Tables**:
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