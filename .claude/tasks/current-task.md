# Task: Main Application Navigation & Role-Based Access

**Created:** 2026-02-09
**Last Updated:** 2026-02-09 - Task Created
**Status:** Phase 1 - Planning

---

## 📋 Overview

### Goal
Create the main application navigation structure with role-based visibility. Add navigation links in the sidebar and create template pages for each section. Implement a simple and reusable way to access user role throughout Vue components.

### Success Criteria
- [ ] All navigation links added to sidebar (Instructors, Pupils, Teams, Reports, Resources, Settings, Apps)
- [ ] "Instructors" link only visible to users with `owner` role
- [ ] Template pages created for all navigation items
- [ ] User role accessible in all Vue components via composable
- [ ] TypeScript types updated to include role
- [ ] Backend properly passes role data to frontend
- [ ] Navigation properly highlights active routes
- [ ] All routes registered in Laravel
- [ ] Middleware documented (not implemented yet, but documented for future use)

### Context
The database is set up with users that have roles (owner, instructor, student). There's existing middleware from a previous project for role-based restrictions. This task focuses on:
1. Creating the navigation structure
2. Making role easily accessible in Vue components
3. Setting up template pages
4. Implementing role-based visibility for navigation items

**Current Navigation Structure:**
- AppSidebar.vue contains navigation items
- NavMain.vue renders the navigation
- AppSidebarLayout.vue provides the layout
- User data passed via Inertia's shared data in HandleInertiaRequests middleware

**Required Links:**
- Instructors (owner only)
- Pupils
- Teams
- Reports
- Resources
- Settings
- Apps

---

## 🎯 PHASE 1: PLANNING & ANALYSIS

**Status:** ⏸️ Not Started

### Tasks
- [ ] Review current authentication and role structure
- [ ] Identify all files that need modification
- [ ] Design role access pattern for Vue components
- [ ] Plan navigation structure and icons
- [ ] Identify routes needed for each page
- [ ] Document existing middleware for future reference
- [ ] Create TypeScript types for role enum
- [ ] Plan template page structure

### Current Architecture Analysis

**Backend:**
- User model has `role` field (enum: owner, instructor, student)
- UserRole enum: `OWNER`, `INSTRUCTOR`, `STUDENT`
- User model helper methods: `isOwner()`, `isInstructor()`, `isStudent()`
- HandleInertiaRequests shares user data: `'auth' => ['user' => $request->user()]`

**Frontend:**
- AppSidebar.vue: Contains `mainNavItems` array
- NavMain.vue: Renders navigation from items prop
- User type: Currently missing `role` field
- Auth type: Contains `user` object
- Page props: `AppPageProps` includes `auth` with user data

**Existing Middleware (for documentation):**
- Located: `app/Http/Middleware/`
- Files found:
  - HandleAppearance.php
  - HandleInertiaRequests.php
  - ValidateEnquiryUuid.php (onboarding specific)
  - ValidateStepAccess.php (onboarding specific)
- Note: Role-based middleware mentioned by user but needs to be implemented later

### Files to Modify/Create

**Backend:**
1. Create route file or add to existing routes
2. Create controllers for each section:
   - InstructorController (index method)
   - PupilController (index method)
   - TeamController (index method)
   - ReportController (index method)
   - ResourceController (index method)
   - AppController (index method)
   - Settings already exists
3. Document middleware pattern for future use

**Frontend Types:**
1. `resources/js/types/auth.ts` - Add role to User type
2. `resources/js/types/navigation.ts` - Add role visibility to NavItem type (optional)
3. Create `resources/js/types/roles.ts` - TypeScript enum for roles

**Frontend Components:**
1. `resources/js/components/AppSidebar.vue` - Add new navigation items
2. Create `resources/js/composables/useRole.ts` - Role access composable

**Frontend Pages:**
1. `resources/js/pages/Instructors/Index.vue`
2. `resources/js/pages/Pupils/Index.vue`
3. `resources/js/pages/Teams/Index.vue`
4. `resources/js/pages/Reports/Index.vue`
5. `resources/js/pages/Resources/Index.vue`
6. `resources/js/pages/Apps/Index.vue`
7. Settings page already exists

### Navigation Design

**Proposed Navigation Structure:**
```typescript
const mainNavItems = [
  { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
  { title: 'Instructors', href: instructorsIndex(), icon: GraduationCap, roles: ['owner'] },
  { title: 'Pupils', href: pupilsIndex(), icon: Users },
  { title: 'Teams', href: teamsIndex(), icon: UsersRound },
  { title: 'Reports', href: reportsIndex(), icon: FileText },
  { title: 'Resources', href: resourcesIndex(), icon: BookOpen },
  { title: 'Settings', href: settingsProfile(), icon: Settings },
  { title: 'Apps', href: appsIndex(), icon: Grid3x3 },
]
```

**Icon Mapping:**
- Instructors: `GraduationCap`
- Pupils: `Users`
- Teams: `UsersRound`
- Reports: `FileText`
- Resources: `BookOpen`
- Settings: `Settings`
- Apps: `Grid3x3`
- Dashboard: `LayoutGrid` (existing)

### Role Access Pattern Options

**Option 1: Composable (Recommended)**
```typescript
// composables/useRole.ts
export function useRole() {
  const page = usePage<AppPageProps>();
  const user = computed(() => page.props.auth.user);
  const role = computed(() => user.value?.role);

  const isOwner = computed(() => role.value === 'owner');
  const isInstructor = computed(() => role.value === 'instructor');
  const isStudent = computed(() => role.value === 'student');

  const hasRole = (roles: string[]) => roles.includes(role.value);

  return { role, isOwner, isInstructor, isStudent, hasRole };
}
```

**Option 2: Direct Access via usePage**
Components can access role via: `usePage().props.auth.user.role`

**Decision:** Use composable for better reusability and cleaner component code

### Routes Structure

**Laravel Routes (web.php):**
```php
Route::middleware(['auth'])->group(function () {
    Route::get('/instructors', [InstructorController::class, 'index'])->name('instructors.index');
    Route::get('/pupils', [PupilController::class, 'index'])->name('pupils.index');
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
    Route::get('/apps', [AppController::class, 'index'])->name('apps.index');
});
```

**Wayfinder Integration:**
- Routes will auto-generate TypeScript functions in `@/routes`
- Import example: `import { instructorsIndex, pupilsIndex } from '@/routes'`

### Template Page Structure

**Basic Template (all pages):**
```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Page Name' }]">
    <div class="flex flex-col gap-4 p-6">
      <Card>
        <CardHeader>
          <CardTitle>Page Title</CardTitle>
        </CardHeader>
        <CardContent>
          <p>Page content goes here...</p>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
```

### Middleware Documentation (For Future)

**Purpose:**
Document existing middleware patterns for when role-based restrictions need to be implemented.

**Location:** `.claude/middleware-documentation.md`

**Content to include:**
- List of existing middleware
- Pattern for creating role-based middleware
- Example usage in routes
- Example middleware implementation

### Complexity Assessment
- [x] Medium (4-6 hours)
  - Multiple pages to create
  - Type updates needed
  - Composable creation
  - Route registration
  - Navigation updates
  - Wayfinder integration

### Decisions Made
1. **Use composable for role access** - Cleaner, more reusable than direct prop access
2. **Add roles array to navigation items** - Simple array check for visibility
3. **Create template pages with Card components** - Follow ShadCN patterns
4. **Use Lucide icons** - Consistent with existing codebase
5. **Document middleware but don't implement** - Focus on UI/UX first, security later
6. **Use Wayfinder for routes** - Leverage existing route generation

### Notes
- Middleware mentioned by user exists but role-based restrictions not implemented yet
- Will document middleware for future implementation
- Focus is on UI structure and making role accessible
- All navigation items visible to all roles except "Instructors" (owner only)

### Blockers
None identified

### Reflection
**What went well:**
- Clear requirements from user
- Existing infrastructure is well-structured
- Role system already in database
- User data already passed to frontend

**What could be improved:**
- Need to ensure role is actually serialized to JSON by Laravel
- Need to check if Wayfinder is properly configured

**Risks identified:**
- Role might not serialize properly (enum to string conversion)
- Middleware documentation might be incomplete
- Future middleware implementation might require route changes

**⚠️ STOP - Awaiting approval to proceed to Phase 2**

---

## 🔨 PHASE 2A: BACKEND SETUP

**Status:** ⏸️ Not Started

### Tasks
- [ ] Verify role serialization in HandleInertiaRequests
- [ ] Create controllers:
  - [ ] InstructorController with index method
  - [ ] PupilController with index method
  - [ ] TeamController with index method
  - [ ] ReportController with index method
  - [ ] ResourceController with index method
  - [ ] AppController with index method
- [ ] Register routes in web.php
- [ ] Run Wayfinder generation to create TypeScript route functions
- [ ] Test routes return 200 status

### Currently Working On
Not started

**⚠️ STOP - Awaiting approval to proceed to Phase 2B**

---

## 🔨 PHASE 2B: FRONTEND TYPES & COMPOSABLE

**Status:** ⏸️ Not Started

### Tasks
- [ ] Create TypeScript role enum in `resources/js/types/roles.ts`
- [ ] Update User type to include role field in `resources/js/types/auth.ts`
- [ ] Create useRole composable in `resources/js/composables/useRole.ts`
- [ ] Add optional roles field to NavItem type in `resources/js/types/navigation.ts`
- [ ] Test type safety in IDE

### Currently Working On
Not started

**⚠️ STOP - Awaiting approval to proceed to Phase 2C**

---

## 🔨 PHASE 2C: NAVIGATION UPDATE

**Status:** ⏸️ Not Started

### Tasks
- [ ] Import new route functions from Wayfinder in AppSidebar.vue
- [ ] Import Lucide icons for navigation items
- [ ] Add new navigation items to mainNavItems array
- [ ] Implement role-based filtering in AppSidebar.vue using useRole composable
- [ ] Test navigation in browser (all roles if possible, or just verify structure)

### Currently Working On
Not started

**⚠️ STOP - Awaiting approval to proceed to Phase 2D**

---

## 🔨 PHASE 2D: TEMPLATE PAGES CREATION

**Status:** ⏸️ Not Started

### Tasks
- [ ] Create Instructors/Index.vue page
- [ ] Create Pupils/Index.vue page
- [ ] Create Teams/Index.vue page
- [ ] Create Reports/Index.vue page
- [ ] Create Resources/Index.vue page
- [ ] Create Apps/Index.vue page
- [ ] Test all pages load correctly
- [ ] Verify breadcrumbs display correctly
- [ ] Verify page titles display correctly

### Currently Working On
Not started

**⚠️ STOP - Awaiting approval to proceed to Phase 3**

---

## 🔨 PHASE 3: MIDDLEWARE DOCUMENTATION

**Status:** ⏸️ Not Started

### Tasks
- [ ] Create `.claude/middleware-documentation.md`
- [ ] Document existing middleware files
- [ ] Document UserRole enum usage
- [ ] Provide example role-based middleware implementation
- [ ] Document route protection patterns
- [ ] Add notes about when to implement role restrictions

### Currently Working On
Not started

**⚠️ STOP - Awaiting approval to proceed to Phase 4**

---

## 🧪 PHASE 4: TESTING & VERIFICATION

**Status:** ⏸️ Not Started

### Tasks
- [ ] Test navigation displays all items
- [ ] Test "Instructors" link visibility with owner role
- [ ] Test "Instructors" link hidden for non-owner roles (if possible to test)
- [ ] Test all pages load without errors
- [ ] Test navigation highlighting works
- [ ] Test breadcrumbs display correctly
- [ ] Verify role composable works in components
- [ ] Test responsive design (mobile/tablet/desktop)
- [ ] Verify no console errors
- [ ] Test TypeScript compilation

### Currently Working On
Not started

**⚠️ STOP - Awaiting approval to proceed to Phase 5**

---

## 💭 PHASE 5: FINAL REFLECTION & CLEANUP

**Status:** ⏸️ Not Started

### Tasks
- [ ] Review all changes
- [ ] Remove any debug code
- [ ] Verify all files follow coding standards
- [ ] Document any technical debt
- [ ] Update this task file with final notes
- [ ] Archive task to completed folder

---

## 📝 Quick Reference

### Key Files to Create

**Backend Controllers:**
- `app/Http/Controllers/InstructorController.php`
- `app/Http/Controllers/PupilController.php`
- `app/Http/Controllers/TeamController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/ResourceController.php`
- `app/Http/Controllers/AppController.php`

**Frontend Types:**
- `resources/js/types/roles.ts` (new)
- `resources/js/types/auth.ts` (update)
- `resources/js/types/navigation.ts` (update)

**Frontend Composable:**
- `resources/js/composables/useRole.ts` (new)

**Frontend Components:**
- `resources/js/components/AppSidebar.vue` (update)

**Frontend Pages:**
- `resources/js/pages/Instructors/Index.vue`
- `resources/js/pages/Pupils/Index.vue`
- `resources/js/pages/Teams/Index.vue`
- `resources/js/pages/Reports/Index.vue`
- `resources/js/pages/Resources/Index.vue`
- `resources/js/pages/Apps/Index.vue`

**Documentation:**
- `.claude/middleware-documentation.md` (new)

**Routes:**
- `routes/web.php` (update)

---

## 📞 Questions & Clarifications Log

### Assumptions Made
- **Assumption:** Role field serializes properly from enum to string in JSON
  - **Reasoning:** Laravel typically handles enum serialization automatically
  - **Verified:** Pending (Phase 2A)

- **Assumption:** All navigation items should be visible to all roles except "Instructors"
  - **Reasoning:** User specifically mentioned only "Instructors" should be owner-only
  - **Verified:** From user requirements

- **Assumption:** Middleware implementation is future work, not current task
  - **Reasoning:** User said "These are not important yet"
  - **Verified:** From user requirements

- **Assumption:** Use existing AppLayout for all new pages
  - **Reasoning:** Consistent with existing codebase
  - **Verified:** Pending user approval

### Questions for User
None at this time - requirements are clear

---

## 🎯 Success Metrics

**Definition of Done:**
1. ✅ All 7 navigation items display in sidebar
2. ✅ "Instructors" link only shows for owner role
3. ✅ All 6 new pages created and accessible
4. ✅ Role accessible via useRole() composable in any component
5. ✅ TypeScript types include role
6. ✅ No TypeScript errors
7. ✅ No console errors
8. ✅ Navigation highlighting works
9. ✅ Breadcrumbs work on all pages
10. ✅ Middleware documented for future implementation

**Out of Scope:**
- ❌ Role-based middleware implementation
- ❌ Actual functionality in template pages (just templates)
- ❌ Data fetching or API calls
- ❌ Role management UI
- ❌ Permission system beyond navigation visibility
