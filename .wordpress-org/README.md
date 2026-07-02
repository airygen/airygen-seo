# WordPress.org plugin assets

Files in this directory are the marketing assets shown on the plugin's
WordPress.org directory page (icon, banner, screenshots). They are synced to the
plugin's SVN `/assets` area by `.github/workflows/deploy-assets.yml` and are
**not** part of the distributed plugin zip.

Drop the images here with these exact filenames, then push to the `assets`
branch (or run the "Deploy assets to WordPress.org" workflow manually).

## Icon

| File | Size | Notes |
|------|------|-------|
| `icon.svg` | vector | Preferred; scales crisply. |
| `icon-256x256.png` | 256×256 | Retina raster fallback. |
| `icon-128x128.png` | 128×128 | Standard raster fallback. |

## Banner

| File | Size | Notes |
|------|------|-------|
| `banner-772x250.png` | 772×250 | Standard header banner. |
| `banner-1544x500.png` | 1544×500 | Retina (2×) banner. |

## Screenshots

`screenshot-1.png`, `screenshot-2.png`, … in order. Each maps to the matching
line under `== Screenshots ==` in `readme.txt` (screenshot-1 ↔ first caption).

## Format notes

- PNG or JPG for raster; keep files reasonably small.
- WordPress.org only recognizes the filenames above; anything else here (like
  this README) is ignored by the directory but will still be committed to SVN
  `/assets`. Remove files you don't want living in SVN before the first sync.
