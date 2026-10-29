---
name: VertexPL
description: A precise NCR operations workspace for tracking quality decisions and corrective actions.
colors:
  action-indigo: "#4f46e5"
  action-indigo-hover: "#4338ca"
  canvas-light: "#f1f5f9"
  canvas-dark: "#0f172a"
  surface-light: "#ffffff"
  surface-dark: "#1e293b"
  text-light: "#0f172a"
  text-dark: "#f1f5f9"
  text-secondary-light: "#334155"
  text-secondary-dark: "#e2e8f0"
  muted-light: "#64748b"
  muted-dark: "#94a3b8"
  border-light: "#e2e8f0"
  border-dark: "#334155"
typography:
  body:
    fontFamily: "Nunito, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
  headline:
    fontFamily: "Nunito, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.5rem, 1.2rem + 1vw, 2.15rem)"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.025em"
  label:
    fontFamily: "Nunito, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.7rem"
    fontWeight: 700
    letterSpacing: "0.04em"
rounded:
  sm: "0.5rem"
  md: "0.75rem"
  lg: "1rem"
  pill: "9999px"
spacing:
  xxs: "4px"
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.action-indigo}"
    textColor: "#ffffff"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "8px 20px"
    height: "42px"
  button-secondary:
    backgroundColor: "{colors.surface-light}"
    textColor: "{colors.text-secondary-light}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
    height: "42px"
  input-default:
    backgroundColor: "{colors.surface-light}"
    textColor: "{colors.text-light}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
    height: "42px"
  card-default:
    backgroundColor: "{colors.surface-light}"
    textColor: "{colors.text-light}"
    rounded: "{rounded.md}"
    padding: "12px"
  chip-neutral:
    backgroundColor: "{colors.canvas-light}"
    textColor: "{colors.text-secondary-light}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "4px 10px"
  navigation-active:
    backgroundColor: "{colors.action-indigo}"
    textColor: "#ffffff"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
    height: "40px"
---

# Design System: VertexPL

## Overview

**Creative North Star: "Quality Operations Desk"**

VertexPL uses a Modern Industrial Minimalism voice: credible, composed, current, and precise. Indigo and slate establish a restrained working environment where workflow, status, data, and the next permitted action carry the visual emphasis. The interface is a working tool for NCR decisions, so repeated patterns favor quick scanning and clear states over decoration.

Keep the layout compact and readable across desktop, tablet, and mobile. The shared theme tokens support Light and Dark Mode; use them for surfaces, text, borders, fields, and states instead of adding page-specific palettes. Color display names in this document describe the existing Tailwind colors and are not separate brand tokens.

NCR form selection uses the active form-scope master. When an existing report references an inactive or legacy scope, keep that stored value available during editing until the user selects an active replacement.

**Key Characteristics:**
- Compact, task-oriented hierarchy with clear labels and actions.
- Indigo actions on neutral slate surfaces.
- Light and dark themes use the same semantic color roles.
- Focus and status remain readable without relying on color alone.

## Colors

The palette pairs a confident indigo action color with cool slate neutrals; semantic colors identify workflow outcomes and validation states. Exact light and dark values are in the frontmatter and originate from `resources/theme/palette.js`.

### Primary
- **Measured Indigo** (`action-indigo`): primary actions, selected navigation, focus, and workflow emphasis. Its hover shade is `action-indigo-hover`.

### Neutral
- **Graphite Slate** (`canvas-*`): page background and quiet grouping surfaces.
- **Slate Surface** (`surface-*`): cards, dialogs, and form controls; the dark theme maps the same surface role to a deep slate.
- **Slate Ink** (`text-*`): primary information and headings.
- **Slate Muted** (`muted-*`): supporting labels, helper text, and secondary metadata.
- **Slate Border** (`border-*`): separation between sections, controls, and table regions.

### Semantic status
- Use the existing red, amber, emerald, and indigo Tailwind roles for error, warning, successful, and in-progress states. Pair each color with a visible label or icon.

**The Role-Based Color Rule.** Use the existing theme variables for both modes; do not hardcode a new page palette.

## Typography

**Display Font:** Nunito (with the system sans-serif stack as fallback)
**Body Font:** Nunito (with the system sans-serif stack as fallback)
**Label/Mono Font:** No distinct label or mono family is defined.

**Character:** Rounded, approachable letterforms keep dense operational content readable while retaining a professional tone. The Latin font is followed by system sans-serif fallbacks for glyph coverage, including Thai text.

### Hierarchy
- **Display** (800, responsive `clamp(1.5rem, 1.2rem + 1vw, 2.15rem)`, line-height 1.15): primary page title on NCR detail screens.
- **Headline** (700–800, typically 1.25–1.5rem): page and major section headings.
- **Title** (600–700, typically 1rem): card and workflow section headings.
- **Body** (400–600, 1rem base with 1.5 line-height): form values, explanations, and report details.
- **Label** (600–800, commonly 0.7–0.875rem): field labels, metadata, and compact section cues; use letter spacing only for short eyebrow labels.

**The Meaningful Weight Rule.** Use weight and size to separate task, section, and supporting information; do not make every label bold or uppercase.

## Layout

The authenticated application uses a 16rem navigation rail on large screens and a collapsible menu on smaller screens. The content area fills the available width; the shared application wrapper uses compact 8px outer padding and an 8px baseline between sibling sections. Form and filter grids collapse to one column on narrow screens and add columns at Tailwind's `sm` (640px), `md` (768px), and `lg` (1024px) breakpoints as content permits.

Keep page titles, filters, tables, and task actions close to the information they control. Lists may become labeled cards on mobile when that is the existing page pattern; preserve readable values and reachable actions without forcing wide tables into the viewport. The NCR create/edit form groups product data into repeatable line items; item editing uses a native dialog with a scrollable body and reachable footer actions.

## Elevation & Depth

The system is mostly flat: borders and tonal surface changes establish grouping. Existing low-opacity shadows appear on selected cards, with stronger shadows limited to dialogs or focused workflow panels. Keep new surfaces mostly flat and use elevation only when it clarifies an overlay or active surface.

### Shadow Vocabulary
- **Quiet surface** (`0 1px 2px rgb(15 23 42 / 0.04)`): subtle separation on selected cards.
- **Dialog** (`0 24px 70px rgb(15 23 42 / 0.28)`): distinguish a native dialog from the page behind it.
- **Current workflow panel** (`0 16px 36px rgb(var(--theme-border-indigo-600) / 0.09)`): a limited emphasis treatment already used for a key NCR action panel.

**The Mostly Flat Rule.** Prefer borders and tonal layers at rest; reserve visible elevation for a clear interaction or overlay purpose.

## Shapes

Controls and cards use gently rounded corners: the common Tailwind `rounded-lg` shape is 8px, section cards commonly use 12px, and dialogs or prominent forms may use 16px. Status chips use a pill shape. Borders are thin and low contrast in the default state; focus and validation states change the border and show a visible ring.

## Components

### Buttons
- **Character:** Precise and restrained; action priority is communicated by fill, label, and placement.
- **Shape:** Gently rounded corners (8px); common controls keep a 42px minimum height.
- **Primary:** Indigo fill with white text and approximately 20px horizontal padding.
- **Secondary:** Neutral surface with a slate border and explicit text label.
- **Hover / Focus:** Darken the primary fill on hover; keep a visible indigo focus outline and disabled state.

### Chips
- **Style:** Compact rounded pills on a quiet slate surface, with readable status text.
- **State:** Existing semantic colors may supplement the text; never use color as the only status cue.

### Cards / Containers
- **Corner Style:** 12px is the common section-card radius; prominent form containers may use 16px.
- **Background:** The themed surface token over the page canvas.
- **Shadow Strategy:** Borders and surface changes first; see Elevation & Depth for the limited exceptions.
- **Border:** Thin slate border separates adjacent surfaces.
- **Internal Padding:** Common compact cards use 12–16px, with denser dashboard cards using less where established.

### Inputs / Fields
- **Style:** Themed surface, thin slate border, 8px corners, and a 42px minimum height for common controls.
- **Focus:** Indigo border/ring with visible keyboard focus.
- **Error / Disabled:** Red border for invalid fields; disabled controls use muted text and surface colors and remain visibly inactive.
- **Form scope:** Use a single-select control backed by active scope records; preserve the currently stored inactive/legacy value until an active replacement is selected.

### Native dialogs
- Use the browser's `<dialog>` for focused product-item editing. Keep it labeled, its content scrollable, and its cancel/save controls reachable. Preserve the existing form submission names and values.
- Retain visible focus styles and themed surfaces in both modes.

### Navigation
- **Style:** Persistent dark slate navigation on desktop, compact menu trigger on smaller screens, and a high-contrast indigo active item.
- **Typography:** Clear labels paired with the existing Phosphor icon set.
- **States:** Active, hover, expanded, and keyboard-focus states must remain distinguishable in both themes.

### Workflow panels
- Group current stage, responsible action, due information, and supporting history with clear headings and status labels. Preserve the distinctions among Action Verification, QA Follow-up, and workflow history.
- Show the resolved form-scope name in NCR history summaries when available; fall back to the stored legacy code so older reports remain understandable.

## Do's and Don'ts

### Do:
- **Do** reuse the theme variables and shared NCR patterns for surfaces, borders, fields, and focus states.
- **Do** make the current workflow stage, status, responsible work, and next action easy to scan.
- **Do** keep the same semantic roles in Light and Dark Mode and verify contrast in both.
- **Do** respect reduced-motion preferences and keep interactions short and purposeful.

### Don't:
- **Don't** use flashy gradients, unnecessary glassmorphism, or decorative elements without a task purpose.
- **Don't** introduce decorative card-within-card layouts or heavy shadows that dilute the information hierarchy.
- **Don't** add animation that distracts from NCR work or ignores reduced-motion preferences.
- **Don't** communicate status through color alone.
