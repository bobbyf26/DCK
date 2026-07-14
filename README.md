# DCK Directory — WordPress plugin

A self-contained WordPress plugin that turns any site into a decorative-concrete
contractor directory: a searchable AJAX landing page, clean contractor profiles,
free front-end signup, and paid premium listings. The profile design matches
`contractor-template.html` (kept in this repo as the visual reference).

---

## ⬇️ Install

**Easiest — use the green button:**

1. On this repo's main page click **`< > Code` → Download ZIP**.
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the ZIP you
   just downloaded, click **Install Now**, then **Activate**.

Because the plugin files live at the **root** of this repo, that downloaded ZIP
is a valid plugin — WordPress will install it (as a plugin named *DCK
Directory*). If you prefer a clean folder name, use the prebuilt
[`dck-directory.zip`](dck-directory.zip) instead (open it and **Download raw
file**), which installs into a `dck-directory/` folder.

> ⚠️ If you ever see *"No valid plugins were found"*, it means the ZIP had an
> extra wrapping folder. Re-download using one of the two options above.

**After activating:**

3. Create three WordPress pages and put one shortcode on each:
   - **Find a Contractor** → `[dck_directory]`
   - **Add Your Listing** → `[dck_signup]`
   - **My Listing** → `[dck_dashboard]`
4. Visit **Settings → Permalinks** and click **Save Changes** once (this flushes
   rewrite rules so `/contractor/...` profile URLs work).

The plugin remembers which page holds each shortcode, so all internal links
("Add your free listing", "Manage this listing", etc.) resolve automatically.

---

## What it does

- **Contractor listings** — a `Contractor` post type with two taxonomies:
  **Service Categories** (your 9 categories, auto-seeded on activation) and
  **Locations** (State → City, hierarchical for drill-down).
- **Searchable landing page** — AJAX "What / Where / keyword" search, clickable
  service tiles, and a browse-by-state grid. No page reloads.
- **Profiles** — photo mosaic, rating, services, reviews, service area,
  credentials, FAQ, a sticky quote form, and a Google-style live "Open / Closed"
  status computed from business hours. Inherits your theme's header/footer.
- **Free vs Premium**
  - **Free:** business name, address, phone, categories, logo.
  - **Premium:** photo gallery, website + social links, reviews, business hours,
    services list, FAQ, credentials, and **featured** placement at the top of
    search results.
- **Front-end signup** (`[dck_signup]`) — visitors create an account and a free
  listing in one step. New listings arrive as **Pending** for you to approve.
- **Owner dashboard** (`[dck_dashboard]`) — owners log in and edit their listing;
  premium fields are visible but locked with an upgrade prompt until you switch
  their plan to Premium.
- **Leads** — quote-form submissions are stored under **DCK Directory → Leads**
  and emailed to the contractor (premium) or the site admin.

## Editing the pages (no code, no re-upload)

Go to **DCK Directory → Settings** to change, right from wp-admin:

- **Branding** — the brand color (buttons, links, chips, accents); deep/soft
  shades are derived automatically.
- **Directory / search page** — hero heading + subtext, search button, the
  "Browse by…" and results headings, and the "Are you a contractor?" CTA.
- **Signup form** — page heading, intro, every field label, the two category
  section labels, the consent text, the submit button, and toggles to show/hide
  the optional Street address and ZIP fields.
- **Owner dashboard** — heading, upgrade banner text, save button.
- **Contractor profile** — every section heading and the quote form heading/button.

The category options themselves are term lists you manage under **Coating
Systems** and **Service Areas**. Together this means day-to-day wording, labels,
fields, colors, and categories are all editable in the dashboard — no need to
edit code or re-upload the plugin. (Structural layout still lives in code.)

## Managing plans (billing deferred)

Premium tiers are fully built; payment collection is **manual for now**. When a
contractor pays you:

1. Open the listing under **DCK Directory → Contractors**.
2. In the **Listing Plan** box, set **Plan → Premium** (tick **Featured** to pin
   them to the top of search) and save.

That instantly unlocks all premium fields on their dashboard and profile. Owners
can also click **Request upgrade** in their dashboard, which emails you. To
automate billing later, a payment gateway just needs to set the `_dck_tier` meta
to `premium` on success — that single value is the switch the whole plugin reads.

## Approving new listings

Front-end signups create **Pending** listings. Review them under **DCK Directory
→ Contractors** (filter by *Pending*) and click **Publish**.

## File map

```
dck-directory.php              Main bootstrap, assets, activation
includes/
  class-dck-post-types.php     CPT + taxonomies + lead store
  class-dck-fields.php         Field schema, tier gating, save/sanitize
  class-dck-admin.php          Meta boxes, plan controls, columns, leads
  class-dck-ajax.php           Search + lead-capture endpoints
  class-dck-shortcodes.php     Landing, signup, dashboard + form handlers
  class-dck-templates.php      Single profile + archive routing
  template-functions.php       Profile & card renderers (approved design)
templates/
  archive-contractor.php       Category / state / city result grids
assets/
  css/dck-directory.css        Front-end styles (blue/white design system)
  css/dck-admin.css            Admin meta-box styles
  js/dck-directory.js          AJAX search, lead form, open/closed, sidebar
  js/dck-admin.js              Media gallery picker + repeatable rows
uninstall.php                  Conservative cleanup on delete
contractor-template.html       Standalone design reference (not loaded by WP)
dck-directory.zip              Prebuilt installable copy (clean folder name)
```

## Notes & next steps

- The live open/closed status uses the visitor's local clock (fine for a
  locally-served trade — both parties are almost always in the same timezone).
- Reviews are entered by the owner/admin for v1; a public review-submission flow
  is a natural follow-up.
- Payment-gateway integration is the main deferred piece; the tier switch is
  already in place to make it a small addition.
