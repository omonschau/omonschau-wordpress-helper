# WordPress Helper

WordPress Helper is a small utility plugin for WordPress sites you manage (your own site and client projects). Each feature can be turned on or off independently from the admin. **Plugin code and identifiers are in English; the settings screen labels are in German.**

## Requirements

- WordPress 5.8 or newer (recommended: latest supported release).
- PHP 7.4 or newer (PHP 8.x recommended). UTM cookies use `SameSite=Lax` when PHP 7.3+ array-style `setcookie` is available.

## Installation

1. Copy the plugin folder into your WordPress installation:
   - Either clone this repository into `wp-content/plugins/wordpress-helper/`  
   - Or upload a ZIP of the project so that `wordpress-helper.php` lives directly inside `wp-content/plugins/wordpress-helper/wordpress-helper.php`.

2. In the WordPress admin, go to **Plugins**, find **WordPress Helper**, and click **Activate**.

There is no separate build step.

## Configuration

Open **Tools → WordPress Helper** (`Werkzeuge → WordPress Helper`). Users need the **Administrator** capability `manage_options`.

You can enable any combination of:

| Option (German UI) | Internal key | What it does |
|-------------------|--------------|----------------|
| Kommentare, Pings und Trackbacks deaktivieren | `disable_comments` | Closes comments and pings, hides comment-related admin UI, blocks new submissions (including REST/XML-RPC ping flows where applicable). Existing comments in the database are not deleted. |
| Admin-Ansicht reduzieren | `reduce_admin` | Removes selected dashboard widgets and some admin bar items for a calmer backend. |
| UTM-Parameter auf interne Links übernehmen (Frontend) | `utm_persist` | Persists campaign parameters on first visit and appends them to internal links on the public site only (not in `wp-admin`). |
| KI-Kennzeichnung für Medien (EU AI Act) | `ai_disclosure` | Adds an AI usage field in the media library and shows a frontend badge on images marked as AI-generated or AI-modified. |

## Features in detail

### Disable comments, pings, and trackbacks

Aims to stop new comment/ping/tr trackback traffic and reduce spam vectors: filters, post type support removal, admin menu adjustments, REST comment routes, and related hardening. For extension, post types are filterable (see **Developer hooks** below).

### Reduced admin experience

Removes the welcome panel and specific dashboard metaboxes (e.g. activity and WordPress events/news) and trims the admin bar (e.g. comments and “New” shortcuts). Only runs in the admin area.

### UTM persistence (frontend only)

- Whitelisted query keys: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`.
- **First-touch:** values are stored in a cookie only if the cookie is not already set (default lifetime: 30 days).
- Internal links are enriched server-side (`the_content`, widget text content, excerpt) and via a small footer script so navigation menus and similar markup also stay consistent.
- Mailto, `tel:`, `javascript:`, and pure hash links are skipped.

### AI media disclosure (EU AI Act)

- **Media library field** on every attachment: *Kein KI-Einsatz* (default), *KI-generiert*, *KI-modifiziert*.
- Existing media without saved meta is treated as *Kein KI-Einsatz* — no bulk migration on install.
- **Frontend badge** (bottom-right, German full labels) on images only when status is *KI-generiert* or *KI-modifiziert*.
- Covers standard WordPress image output (`wp_get_attachment_image`, featured images, block content) and **Beaver Builder** (photo module, background images, gallery/slider modules where attachment IDs are available).
- Badges appear on the public site only, not in `wp-admin` or the page builder editor.
- This feature supports transparency under the EU AI Act; it does not constitute legal advice.

## Privacy

If **UTM persistence** is enabled, the plugin sets a first-party cookie on the site’s domain to remember campaign parameters. Describe this in your privacy policy and cookie banner where required (e.g. GDPR).

**AI disclosure** does not set cookies or track users; it reads attachment metadata and renders labels on the frontend.

## Developer hooks

Optional filters (all passed through WordPress’s `apply_filters`):

- `omonschau_wh_disable_comments_post_types` — array of post type names (default includes `post` and `page`).
- `omonschau_wh_remove_dashboard_metaboxes` — list of dashboard metabox definitions to remove when **Admin-Ansicht reduzieren** is on.
- `omonschau_wh_remove_admin_bar_nodes` — admin bar node IDs to remove (defaults are conservative).
- `omonschau_wh_utm_cookie_lifetime` — cookie lifetime in seconds (default: 30 days).
- `omonschau_wh_ai_disclosure_meta_key` — attachment meta key (default: `_omonschau_wh_ai_usage`).
- `omonschau_wh_ai_disclosure_labels` — label map for `none`, `generated`, `modified`.
- `omonschau_wh_ai_disclosure_show_badge` — `(bool $show, int $attachment_id, string $status)` to suppress a badge per image.

The main bootstrap function is `omonschau_wh()` (returns the `Omonschau_WH_Plugin` singleton).

## Changelog

### 1.1.0

- Add optional **AI media disclosure** feature (EU AI Act): media library field and frontend badges, including Beaver Builder support.

### 1.0.0

- Initial release: disable comments, reduce admin, UTM persistence.

## Author

Oliver Monschau — [https://omonschau.de](https://omonschau.de)
