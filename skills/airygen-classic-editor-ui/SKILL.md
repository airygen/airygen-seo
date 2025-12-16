---
name: airygen-classic-editor-ui
description: Update Airygen SEO Classic Editor metabox UI, layouts, and tabs. Use when adjusting Classic Editor React UI (packages/classic-editor) or its styles to match Gutenberg panel patterns while keeping block editor unchanged.
---

# Airygen Classic Editor UI

## Overview
Use this skill when changing the Classic Editor Airygen SEO metabox layout, tabs, or styles. Keep the Classic Editor UI consistent with Gutenberg panel patterns while avoiding any edits to block-editor bundles.

## Workflow
1. **Edit the Classic Editor React UI**
   - File: `packages/classic-editor/components/App.tsx`
   - The main metabox is a left vertical tab list with content on the right.
   - Each module page can have its own **inner tabs**. These must use the **panel tab** look.

2. **Style the Classic Editor UI**
   - File: `packages/classic-editor/style.scss`
   - Sidebar tabs should be a vertical list with a light background.
   - Inner tabs should match Gutenberg’s `airygen-panel-tabs` look.

## UI Rules
- **Do not touch block editor** files. Only `packages/classic-editor/*`.
- **Left sidebar tabs**:
  - Container: `.airygen-classic-tabs`
  - Background uses a light slate tone.
  - Active item is white.
- **Inner tabs (per module)**:
  - Use `.airygen-panel-tabs` container.
  - Buttons use `.airygen-component-button` with `is-primary/is-secondary`.
  - Text-only tabs (no icons).
- **Panel layout container**:
  - Place a `.airygen-panel-container` div directly under `.airygen-panel-tabs`.
  - All `.airygen-classic-field` elements should live inside `.airygen-panel-container`.
  - Spacing is controlled by `.airygen-panel-container` (gap), not `.airygen-classic-field`.

## Common Patterns
- Add a new sub-tab:
  1. Add a `useState` for the sub-tab.
  2. Render a `.airygen-panel-tabs` nav with buttons.
  3. Render content conditionally per tab value.

## References
- `packages/classic-editor/components/App.tsx`
- `packages/classic-editor/style.scss`
