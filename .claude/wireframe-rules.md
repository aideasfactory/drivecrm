# Wireframe Implementation Rules

## 🚨 CRITICAL WIREFRAME RULES

### Layout vs Styling
When a wireframe is provided:

✅ **DO USE from wireframe:**
- Component placement and positioning
- Page structure and hierarchy  
- Spacing and layout relationships
- Grid/flex arrangements
- Responsive breakpoints
- Component organization

❌ **DO NOT USE from wireframe:**
- Colors (use ShadCN defaults)
- Typography styles (use ShadCN defaults)
- Button styles (use ShadCN Button component)
- Card styles (use ShadCN Card component)
- Form input styles (use ShadCN Form components)
- Custom CSS for component appearance
- Border styles, shadows, or other decorative elements

---

## 🎯 Component Selection Rules

**ALWAYS use ShadCN components with default styling:**

### ✅ CORRECT Examples

```tsx
// Buttons - use ShadCN Button component
import { Button } from "@/components/ui/button"

<Button>Click Me</Button>
<Button variant="outline">Secondary Action</Button>
<Button variant="ghost">Tertiary Action</Button>

// Cards - use ShadCN Card component
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"

<Card>
  <CardHeader>
    <CardTitle>Title</CardTitle>
  </CardHeader>
  <CardContent>Content here</CardContent>
</Card>

// Forms - use ShadCN Form components
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

<div>
  <Label htmlFor="email">Email</Label>
  <Input id="email" type="email" />
</div>

// Layout - only add structural classes
<div className="grid grid-cols-3 gap-4">
  <Card>Item 1</Card>
  <Card>Item 2</Card>
  <Card>Item 3</Card>
</div>
```

### ❌ WRONG Examples

```tsx
// ❌ DON'T create custom styled buttons to match wireframe colors
<button className="bg-blue-500 text-white rounded-lg px-4 py-2 hover:bg-blue-600">
  Click Me
</button>

// ❌ DON'T add custom card styling to match wireframe
<div className="bg-white border-2 border-gray-200 rounded-xl shadow-lg p-6">
  Content
</div>

// ❌ DON'T style inputs to match wireframe appearance
<input 
  type="text" 
  className="border-b-2 border-blue-400 focus:border-blue-600 px-2 py-1"
/>

// ❌ DON'T add decorative classes that change appearance
<div className="bg-gradient-to-r from-purple-500 to-pink-500 text-white">
  Content
</div>
```

---

## 📐 What to Extract from Wireframes

### ✅ Layout Information

Extract and implement:
- **Grid structures:** "3-column layout", "sidebar + main content"
- **Spacing:** gaps between elements, padding inside containers
- **Positioning:** which elements are left/right/center aligned
- **Hierarchy:** what's nested inside what
- **Responsive breakpoints:** when layout changes on mobile/tablet

Example:
```tsx
// Wireframe shows: 3-column grid with large gaps, card in each column
<div className="grid grid-cols-1 md:grid-cols-3 gap-6">
  <Card>Column 1</Card>
  <Card>Column 2</Card>
  <Card>Column 3</Card>
</div>
```

### ❌ Styling Information

**IGNORE these from wireframes:**
- Specific colors shown
- Font sizes and families
- Border radius values
- Shadow effects
- Hover states
- Background gradients

Let ShadCN's theme system handle all of these automatically.

---

## 🎨 When in Doubt

If a wireframe shows custom styling:

1. **Identify the layout pattern** being demonstrated
2. **Implement that layout** with appropriate HTML structure
3. **Use ShadCN components** for all UI elements
4. **Add ONLY layout classes** (grid, flex, spacing, positioning)
5. **Let ShadCN handle** all visual styling

### Decision Tree

```
Does the wireframe show custom styling?
├─ Is it about LAYOUT? (positioning, spacing, grid)
│  └─ ✅ Implement the layout structure
└─ Is it about APPEARANCE? (colors, borders, shadows)
   └─ ❌ Use ShadCN default styling instead
```

---

## 📋 Common Scenarios

### Scenario 1: Wireframe Shows Blue Button
**Wireframe:** Blue button with rounded corners and shadow
**Implementation:** `<Button>Text</Button>`
**Reasoning:** ShadCN Button has default styling - use it as-is

### Scenario 2: Wireframe Shows Custom Card
**Wireframe:** Card with thick border, gradient background, custom shadow
**Implementation:** `<Card>...</Card>`
**Reasoning:** ShadCN Card has professional default styling - use it

### Scenario 3: Wireframe Shows 3-Column Layout
**Wireframe:** Three equal columns with content cards
**Implementation:** 
```tsx
<div className="grid grid-cols-3 gap-4">
  <Card>...</Card>
  <Card>...</Card>
  <Card>...</Card>
</div>
```
**Reasoning:** Layout structure is the key takeaway, not card styling

### Scenario 4: Wireframe Shows Colorful Form
**Wireframe:** Form with purple inputs and pink submit button
**Implementation:**
```tsx
<form>
  <Label>Name</Label>
  <Input type="text" />
  <Button type="submit">Submit</Button>
</form>
```
**Reasoning:** Use ShadCN Form components with defaults, ignore colors

### Scenario 5: Wireframe Shows Sidebar Layout
**Wireframe:** Left sidebar (20% width) with main content area (80% width)
**Implementation:**
```tsx
<div className="flex gap-4">
  <aside className="w-1/5">
    {/* Sidebar content */}
  </aside>
  <main className="flex-1">
    {/* Main content */}
  </main>
</div>
```
**Reasoning:** Layout proportions matter, component styling doesn't

---

## 🔍 Self-Check Questions

Before adding any styling, ask:

1. **Is this class for LAYOUT?**
   - `grid`, `flex`, `gap-4`, `w-1/2`, `p-4`, `mt-8`
   - ✅ These are fine - they control structure

2. **Is this class for APPEARANCE?**
   - `bg-blue-500`, `text-white`, `border-2`, `shadow-lg`, `rounded-xl`
   - ❌ Don't use these - ShadCN components handle appearance

3. **Could I use a ShadCN component instead?**
   - If yes → Use the ShadCN component
   - If no → Use only layout classes on a semantic HTML element

---

## 🚀 Implementation Workflow

### Step 1: Analyze Wireframe
- Identify the layout structure
- Note spacing and positioning
- List required components
- **Ignore all visual styling**

### Step 2: Choose Components
- Select appropriate ShadCN components
- Use `Button`, `Card`, `Input`, etc. with defaults
- Don't add styling variants unless for semantic meaning

### Step 3: Build Structure
- Create HTML structure that matches layout
- Add layout classes only (grid, flex, spacing)
- Use ShadCN components in their default state

### Step 4: Verify
- Does the layout match the wireframe structure? ✅
- Are you using ShadCN components? ✅
- Did you avoid custom styling? ✅
- Do colors match the wireframe? ❌ (This is correct - they shouldn't!)

---

## ⚠️ Common Mistakes to Avoid

### Mistake 1: Matching Wireframe Colors
```tsx
// ❌ WRONG - trying to match wireframe's blue theme
<Button className="bg-blue-600 hover:bg-blue-700">
  Click Me
</Button>

// ✅ CORRECT - using ShadCN default
<Button>Click Me</Button>
```

### Mistake 2: Custom Card Styling
```tsx
// ❌ WRONG - custom styling to match wireframe
<div className="bg-white rounded-lg shadow-md border border-gray-200 p-6">
  Content
</div>

// ✅ CORRECT - using ShadCN Card
<Card>
  <CardContent>Content</CardContent>
</Card>
```

### Mistake 3: Styled Inputs
```tsx
// ❌ WRONG - custom input styling
<input className="w-full px-4 py-2 border-2 border-blue-400 rounded-md" />

// ✅ CORRECT - ShadCN Input
<Input />
```

### Mistake 4: Layout with Custom Styling
```tsx
// ❌ WRONG - mixing layout with custom appearance
<div className="grid grid-cols-3 gap-4 bg-gradient-to-r from-blue-500 to-purple-500 p-8 rounded-xl">
  Content
</div>

// ✅ CORRECT - layout classes only
<div className="grid grid-cols-3 gap-4">
  Content
</div>
```

---

## 📝 Quick Reference

### ✅ Classes You CAN Use (Layout Only)

**Spacing:**
- `p-4`, `px-6`, `py-2`, `m-4`, `mx-auto`, `my-8`
- `gap-4`, `space-x-4`, `space-y-2`

**Layout:**
- `grid`, `grid-cols-3`, `flex`, `flex-col`, `flex-row`
- `w-full`, `w-1/2`, `h-screen`, `min-h-screen`
- `max-w-4xl`, `container`

**Positioning:**
- `relative`, `absolute`, `fixed`, `sticky`
- `top-0`, `left-0`, `right-0`, `bottom-0`
- `justify-center`, `items-center`, `justify-between`

**Responsive:**
- `md:grid-cols-2`, `lg:flex-row`, `sm:hidden`

### ❌ Classes You CANNOT Use (Styling)

**Colors:**
- `bg-blue-500`, `text-white`, `border-gray-200`

**Borders:**
- `border-2`, `rounded-lg`, `border-solid`

**Effects:**
- `shadow-lg`, `shadow-md`, `hover:shadow-xl`

**Typography:**
- `font-bold`, `text-xl`, `leading-tight` (use ShadCN Typography)

---

## 🎯 Remember

> **The wireframe shows WHAT goes WHERE, not HOW it looks.**
> 
> **ShadCN components define HOW things look.**
> 
> **Your job is to connect the layout structure to the right components.**

When implementing wireframes:
1. Extract structure, not style
2. Use ShadCN components in their default state
3. Add only layout classes for positioning
4. Trust the design system for appearance
5. When in doubt, choose the ShadCN component over custom styling
