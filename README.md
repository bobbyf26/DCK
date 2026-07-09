# DCK — Decorative Concrete Kingdom directory

This repo contains two things:

## 1. `dck-directory/` — the WordPress plugin

A self-contained directory plugin: searchable landing page, contractor
profiles, free front-end signup, and paid premium listings. This is the main
deliverable. See [`dck-directory/README.md`](dck-directory/README.md) for full
install and usage instructions.

### ⬇️ Install (do NOT use GitHub's "Download ZIP")

GitHub's green **Code → Download ZIP** wraps everything in a `DCK-main/`
folder, which WordPress rejects with *"No valid plugins were found."* Instead
download the ready-made plugin ZIP:

**[Download `dck-directory.zip`](../../raw/main/dck-directory.zip)**
&nbsp;(open the file in the repo and click **Download raw file**)

Then in WordPress: **Plugins → Add New → Upload Plugin** → choose
`dck-directory.zip` → **Install Now** → **Activate**.

After activating, create three pages and add one shortcode to each —
`[dck_directory]`, `[dck_signup]`, `[dck_dashboard]` — then go to
**Settings → Permalinks** and click **Save** once.

> The `dck-directory.zip` in this repo is a rebuilt copy of the `dck-directory/`
> source folder. If you edit the source, re-zip that folder (top-level entry
> must be `dck-directory/`) before uploading.

## 2. `contractor-template.html` — the design reference

The standalone, approved contractor-profile design. The plugin reproduces this
exact look (blue/white, clean cards, live open/closed status, sticky quote
form) from live listing data. Keep it as the visual source of truth.
