---
name: airygen-admin-ui
description: Admin UI wrapping and styling conventions for Airygen SEO settings and dashboard pages. Use when the user asks to wrap fields, apply card/toggle/input/select styles, align admin layout, or mentions airygen-setting-card__* classes, grid layouts, or admin settings UI consistency.
---

# Airygen Admin UI Wrapping Skill

## Scope
Apply only to admin React UI (settings/dashboard). Do not change frontend or block editor UI.

## Core rules (wrap + structure, not just class)
- When asked to "wrap", "apply", or "style" admin settings inputs/toggles, update the full wrapper structure, not only the class.
- Use card wrappers that match existing patterns in `AGENTS.md` and current admin tabs.

## Wrapper patterns to follow
Use these exact structures and include the marker class on the outer wrapper.

### Module tabs (AI module detail pages)
Use for module-level tabs between header and sections (e.g., Settings/Records) in AI module pages.

```tsx
<div className="airygen-module-page__tab flex flex-wrap gap-2">
	<button
		type="button"
		className={
			activeTab === 'settings'
				? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
				: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
		}
	>
		Settings
	</button>
	<button
		type="button"
		className={
			activeTab === 'records'
				? 'rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-900'
				: 'rounded-md border border-transparent px-3 py-1.5 text-xs font-semibold text-slate-500'
		}
	>
		Records
	</button>
</div>
```

### Input card (normal)
Use for inputs/selects/textarea in settings grids.

```tsx
<div className="airygen-setting-card__input--normal rounded-lg border border-slate-200 p-4">
	<Input ... />
</div>
```

### Select card (normal)
Use for select inputs.

```tsx
<div className="airygen-setting-card__select--normal rounded-lg border border-slate-200 p-4">
	<Select ... />
</div>
```

### Toggle card (normal, row)
Use for single-row toggles; matches schema Visibility and similar patterns.

```tsx
<div className="airygen-setting-card__toggle--normal flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
	<div>
		<p className="text-sm font-medium text-slate-800">Label</p>
		<p className="text-xs text-slate-500">Helper text.</p>
	</div>
	<Toggle label="Label" hideLabelText />
</div>
```

### Toggle card (column)
Use inside 3- or 4-column grids (compact cards).

```tsx
<div className="airygen-setting-card__toggle--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
	<div className="flex items-center justify-between gap-3">
		<p className="text-sm font-medium text-slate-900">Label</p>
		<Toggle label="Label" hideLabelText />
	</div>
	<p className="text-xs text-slate-500">Helper text.</p>
</div>
```

### Input card (column)
Use when the control is a compact input (80px) inside multi-column grids.

```tsx
<div className="airygen-setting-card__input--column flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
	<div className="flex items-center justify-between gap-3">
		<p className="text-sm font-medium text-gray-800">Label</p>
		<input className="airygen-field airygen-input--compact" />
	</div>
	<p className="text-xs text-slate-500">Helper text.</p>
</div>
```

### Four-direction input group
Use `_airygen_four_inputs_group` for directional spacing/border controls that need 4 compact inputs in one connected group.

- Purpose: top/right/bottom/left values for `margin`, `padding`, or directional border values.
- Layout: `icon + input` repeated 4 times in one combined control.
- Keep this pattern for directional controls only (not generic numeric inputs).

### Status card (normal)
Use for status metric cards (queue/status counters) inside grids.

```tsx
<div className="airygen-setting-card__status--normal rounded-lg border border-slate-200 p-3">
	<p className="text-xs uppercase tracking-wide text-slate-500">Label</p>
	<p className="pb-4 text-center text-2xl font-semibold leading-4 text-slate-900">Value</p>
</div>
```

### Color palette input
Use the shared color palette styling for admin color inputs. Apply `airygen-color-palette` to `input[type="color"]` and pair with a text input for the hex value (matches Deep FAQ, TOC, Breadcrumbs).

```tsx
<label className="flex flex-col gap-2 text-sm font-medium text-gray-800">
	<span>Label</span>
	<div className="flex items-center gap-3">
		<input
			type="color"
			className="airygen-color-palette h-6 w-6 cursor-pointer rounded"
		/>
		<input className="airygen-field w-full" />
	</div>
</label>
```

## Checklist before finishing a UI change
- Wrap admin inputs/toggles with the correct card structure (not just class).
- Ensure wrappers include `rounded-lg border border-slate-200 p-4` (or `px-4 py-3` for row toggles).
- Keep grid alignment consistent with the rest of the section.
- Use `text-sm` for labels and `text-xs text-slate-500` for helper text.
- If a user asks for a wrapper, mirror the exact pattern from existing tabs.
- After large UI/design changes, run these commands in order:
  1) `make phpcbf`
  2) `make lint-fix`
  3) `make tests`
  Capture failures and fix before responding.

## Where to look for references
- `AGENTS.md` (Admin Styling & Tailwind Notes).
- Existing examples: `packages/admin/pages/settings/tabs/OnPageSeoTab.tsx`, `BreadcrumbsTab.tsx`, `SchemaTab.tsx`.
