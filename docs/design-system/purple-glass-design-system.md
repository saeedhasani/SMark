# SMark Purple Glass Design System

This document records the current SMark dashboard design system source of truth.
It is based on the light purple glass design reference provided for the dashboard sidebar.

## Foundations

```css
:root {
  --font-fa: "Vazirmatn", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --font-en: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;

  --bg: #f8f5ff;
  --bg-2: #ffffff;
  --surface: rgba(255, 255, 255, .62);
  --surface-strong: rgba(255, 255, 255, .86);
  --surface-soft: rgba(255, 255, 255, .42);
  --line: rgba(97, 62, 161, .14);
  --line-strong: rgba(97, 62, 161, .25);

  --purple-50: #f6f0ff;
  --purple-100: #eadcff;
  --purple-200: #d8c0ff;
  --purple-300: #bc91ff;
  --purple-400: #9b5cff;
  --purple-500: #7b2cff;
  --purple-600: #681ce3;
  --purple-700: #5316b9;
  --purple-800: #3d1188;
  --purple-900: #24074f;

  --ink: #171128;
  --muted: #6f6681;
  --subtle: #948aa7;
  --white: #ffffff;

  --shadow-sm: 0 10px 30px rgba(82, 37, 157, .08);
  --shadow-md: 0 22px 70px rgba(82, 37, 157, .13);
  --shadow-glow: 0 0 0 1px rgba(123, 44, 255, .12), 0 22px 70px rgba(123, 44, 255, .2);

  --radius-sm: 12px;
  --radius-md: 18px;
  --radius-lg: 28px;
  --radius-xl: 36px;
  --blur: blur(22px) saturate(140%);
}
```

## Page Background

Use the light purple background as a full-page fixed layer:

```css
background:
  radial-gradient(circle at 12% 8%, rgba(155, 92, 255, .22), transparent 28%),
  radial-gradient(circle at 88% 12%, rgba(216, 192, 255, .45), transparent 26%),
  radial-gradient(circle at 54% 92%, rgba(123, 44, 255, .12), transparent 32%),
  linear-gradient(180deg, #ffffff 0%, var(--bg) 44%, #fbf9ff 100%);
```

The background grid should use:

```css
background-image:
  linear-gradient(rgba(97, 62, 161, .075) 1px, transparent 1px),
  linear-gradient(90deg, rgba(97, 62, 161, .075) 1px, transparent 1px);
background-size: 34px 34px;
mask-image: linear-gradient(to bottom, black 0%, black 70%, transparent 100%);
```

## Glass Navigation

The glass navigation style is derived from the reference header:

```css
.glass {
  background: var(--surface);
  border: 1px solid rgba(255, 255, 255, .72);
  box-shadow: var(--shadow-sm);
  backdrop-filter: var(--blur);
  -webkit-backdrop-filter: var(--blur);
}
```

The dashboard sidebar navigation should use `position: sticky` so it remains available while the page scrolls, with a fixed top offset and right-side spacing inside the WordPress admin content area.

## Sidebar Icon States

- Default icon color: `--muted` / `#6F6681`
- Selected icon color: `--purple-900` / `#24074F`
- Selected hover color: `--purple-900` / `#24074F`
- Default and selected states should not use a purple circular fill around icons.
- The SMark logo inside navigation should behave like every other icon: no background tile, no special badge, and color inherited from the same icon state rules.
- The SMark logo is the default selected/home item in the dashboard sidebar.
- Clicking a sidebar item should move the selected state to that item so every icon can be reviewed in its active state.
- Sidebar icon hover should not move the icon; only the icon color and tooltip should change.

## Text Color

Default readable text should use `--ink` / `#171128`.

## Current Usage

The implementation lives in the main SMark dashboard page:

`wp-admin/admin.php?page=smark-dashboard`

The page intentionally scopes these variables under `.smark-dashboard-app-page` so the dashboard design system does not leak into other WordPress admin screens.
