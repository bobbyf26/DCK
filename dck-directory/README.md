# DCK Directory

A self-contained WordPress plugin that turns any WordPress site into a
decorative-concrete contractor directory — searchable landing page,
clean contractor profiles, free front-end signup, and paid premium listings.

Built for [decorativeconcretekingdom.com](https://decorativeconcretekingdom.com).
The profile design matches the approved `contractor-template.html` in the repo root.

---

## What it does

- **Contractor listings** — a `Contractor` custom post type with two taxonomies:
  **Service Categories** (Stamped, Stained, Garage Floors, …) and **Locations**
  (State → City, hierarchical for drill-down).
- **Searchable landing page** — AJAX "What / Where / keyword" search, clickable
  service tiles, and a browse-by-state grid. No page reloads.
- **Profiles** — each contractor gets a clean profile: photo mosaic, rating,
  services, reviews, service area, credentials, FAQ, a sticky quote form, and a
  Google-style live "Open / Closed" status computed from business hours.
- **Free vs Premium**
  - **Free:** business name, address, phone, one or more categories, logo.
  - **Premium:** photo gallery, website + social links, reviews, business hours,
    services list, FAQ, credentials, and **featured** placement at the top of
    search results.
- **Front-end signup** (`[dck_signup]`) — visitors create an account and a free
  listing in one step. New listings arrive as **Pending** for you to approve.
- **Owner dashboard** (`[dck_dashboard]`) — owners log in and edit their listing.
  Premium fields are visible but locked with an upgrade prompt until you switch
  the plan to Premium.
- **Leads** — quote-form submissions are stored under **DCK Directory → Leads**
  and emailed to the contractor (premium) or the site admin.

## Install

1. Copy the `dck-directory` folder into `wp-content/plugins/` (or zip it and
   upload via **Plugins → Add New → Upload**).
2. Activate **DCK Directory**. On activation it registers the content types,
   seeds the 9 service categories, and creates a lightweight *Contractor* role.
3. Create three WordPress pages and drop one shortcode on each:
   - **Directory / Search:** `[dck_directory]`
   - **Add Your Listing:** `[dck_signup]`
   - **My Listing (dashboard):** `[dck_dashboard]`
4. Go to **Settings → Permalinks** and click **Save** once (flushes rewrite
   rules so `/contractor/...` URLs resolve).

The plugin auto-detects which page holds each shortcode, so the internal links
(“Add your free listing”, “Manage this listing”, etc.) just work.

## Managing plans (billing deferred)

Premium tiers are fully built; payment collection is intentionally **manual for
now**. When a contractor pays you:

1. Open the listing in **DCK Directory → Contractors**.
2. In the **Listing Plan** box, set **Plan → Premium** (and tick **Featured** to
   pin them to the top of search). Save.

That instantly unlocks all premium fields on their dashboard and profile.
Owners can also click **Request upgrade** from their dashboard, which emails you.

> To automate billing later, wire a gateway (e.g. Stripe Checkout) to set
> `_dck_tier` = `premium` on successful payment — that single meta value is the
> switch the whole plugin reads.

## Approving new listings

Front-end signups create **Pending** listings. Review them under
**DCK Directory → Contractors** (filter by *Pending*) and hit **Publish**.

## File map

```
dck-directory/
├── dck-directory.php              Main bootstrap, assets, activation
├── includes/
│   ├── class-dck-post-types.php   CPT + taxonomies + lead store
│   ├── class-dck-fields.php       Field schema, tier gating, save/sanitize
│   ├── class-dck-admin.php        Meta boxes, plan controls, columns, leads
│   ├── class-dck-ajax.php         Search + lead-capture endpoints
│   ├── class-dck-shortcodes.php   Landing, signup, dashboard + form handlers
│   ├── class-dck-templates.php    Single profile + archive routing
│   └── template-functions.php     Profile & card renderers (approved design)
├── templates/
│   └── archive-contractor.php     Category / state / city result grids
├── assets/
│   ├── css/dck-directory.css      Front-end styles (blue/white design system)
│   ├── css/dck-admin.css          Admin meta-box styles
│   ├── js/dck-directory.js        AJAX search, lead form, open/closed, sidebar
│   └── js/dck-admin.js            Media gallery picker + repeatable rows
└── uninstall.php                  Conservative cleanup on delete
```

## Notes & next steps

- The live open/closed status uses the visitor's local clock (fine for a
  locally-served trade; both parties are almost always in the same timezone).
- Reviews are entered by the owner/admin for v1. A public review-submission flow
  is a natural follow-up.
- Payment gateway integration is the main deferred piece — the tier switch is
  already in place to make it a small addition.
