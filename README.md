# Casinos.cc Tools

WordPress plugin with interactive tools for casino review pages:

- **Wagering calculator** – `[cc_wagering_calculator]`
- **Withdrawal checker** – `[cc_withdrawal_checker]`

Each casino has a **tool profile** with its own offers, terms, payment methods and limits. The tools' code is the same for every casino; only the profile changes.

## Install

1. Download `casinos-cc-tools.zip` from the latest [release](../../releases).
2. WordPress → Plugins → Add New → Upload Plugin → activate.
3. Private repository only: Casino Tools → Settings → paste a GitHub token (fine-grained, this repository, **Contents: Read-only**). Or add `define( 'CCT_GITHUB_TOKEN', '…' );` to `wp-config.php`.

That's the only manual upload. After this, updates come from GitHub.

## Updates

- WordPress checks GitHub for a newer release with its normal update check (twice a day).
- **Check for updates** forces a check right away. It's on the Plugins screen under the plugin name, and in Casino Tools → Settings.
- A newer release shows as a normal "Update available". Update with one click, or switch on auto-updates for the plugin.

### Publishing a new version

1. Bump the version in **both** places in `casinos-cc-tools.php`: the `Version:` header and `CCT_VERSION`.
2. Commit and push to `main`.
3. The **Release** workflow sees a version with no release yet. It checks both version numbers match, lints the PHP, builds `casinos-cc-tools.zip` and publishes release `vX.Y.Z`. Pushes that don't change the version are skipped.

## Adding a casino

**By hand:** Casino Tools → Add casino. Fill in the casino, switch on the tools you want, add offers, methods and limits. Save as Draft until the facts are checked.

**From JSON:** Casino Tools → Import JSON. Upload or paste one profile or many. The preview shows what will be created or updated, with errors and warnings, before anything is saved. Profiles are matched by `casino.slug`, so re-importing updates instead of duplicating. Imported profiles are always saved as Live; tools switched off in the JSON stay off.

- JSON format: [docs/schema.md](docs/schema.md) and [docs/schema.json](docs/schema.json)
- Example: [examples/golazzo-casino.json](examples/golazzo-casino.json)
- Prompt for generating a profile from BrandFinder with Claude: [docs/claude-prompt.md](docs/claude-prompt.md)

Every save and import keeps a revision, so you can roll a casino back from its edit screen.

## Bonus finder

`[cc_bonus_finder]` compares the deposit bonuses of every published casino profile for the visitor's deposit. Put it on any page, for example the main Bonuses page.

- **Deposit slider** from 10 to 5,000 (growing steps), plus deposit method, maximum wagering (10x–40x), sort (best value, biggest bonus, least to wager, lowest minimum deposit) and offer type (welcome, reloads, high roller).
- Each result shows the bonus, the total to wager, days and max bet, and the expected value at 96% RTP, with **Calculate** (the casino's Bonuses page) and **Review** links.
- The first results are in the page HTML, so they show before JavaScript loads and search engines can read them.
- Data is cached and refreshed automatically whenever a profile is saved, imported, published, unpublished or deleted. Casinos need a linked, published review page to appear.

| Attribute | Default | Notes |
|---|---|---|
| `deposit` | `100` | Starting deposit, snapped to the nearest slider step |
| `type` | all | `welcome`, `reload` or `highroller` |
| `wagering` | any | `10`, `20`, `25`, `30`, `35` or `40` |
| `sort` | `value` | `value`, `bonus`, `wager` or `min` |
| `per_casino` | `yes` | `no` lists every offer, not just each casino's best |
| `limit` | `10` | Results before "Show more" |
| `currency` | `EUR` | Which profiles to include |
| `title`, `intro` | – | Optional heading and intro text |

Example: `[cc_bonus_finder type="welcome" wagering="35" limit="15"]`

## The Casinos list

Casino Tools → Casinos is a standard WordPress list:

- **Pagination**: 20 per page by default; change it under **Screen Options** (top right).
- **Search** by casino name or slug.
- **Views**: All / Live / Draft, with counts.
- **Filters**: Wagering calculator on/off, Withdrawal checker on/off, and On the site (no tool on a page / any tool in preview / any tool live).
- **Sorting** by casino, facts-checked date or last update.
- **Bulk actions** on the ticked casinos: Publish (set to Live), Set to Draft, Insert tools & preview, Put tools live on pages, Remove tools from pages, Delete. After a bulk action you return to the same search, filters and page.

## Putting the tools on the pages

Casino Tools → Casinos shows each tool's state on the site:

1. **Insert & preview** puts the tool on the casino's `/bonuses/` (wagering calculator) or `/withdrawal/` (withdrawal checker) page, right after the first heading that follows the page title, and opens the page. Only logged-in editors see it, with an orange "Preview" bar.
2. **Go live** shows it to everyone. **Make all previews live** does this for every tool in preview at once.
3. **Remove** takes it off the page.

The page content is never edited: the tool is added to the finished page HTML, so it works with ACF flexible-content modules. A page that already contains a tool (for example a shortcode) is left alone. Going live or removing clears the page cache for WP Rocket, W3 Total Cache, WP Super Cache and LiteSpeed; other caches can hook `cct_purge_page_cache`.

## Shortcodes

| Shortcode | Notes |
|---|---|
| `[cc_wagering_calculator]` | Casino found from the page or its parent review |
| `[cc_withdrawal_checker]` | Same |
| `[cc_tool type="wagering"]` | Generic form |
| `casino="golazzo-casino"` | Optional on all: use a specific profile |
| `title="…"` `intro="…"` | Optional heading and intro text above the tool |
| `label="…"` | Replace the small label above the tool ("Bonus wagering calculator" / "Withdrawal estimation tool"), or `label="no"` to hide it |
| `boxed="yes"` | Grey panel behind the tool, for pages where it isn't already inside a grey content box |

If a profile is missing, a draft, or has the tool switched off, the shortcode prints nothing for visitors and a short notice for logged-in editors.

The CSS and JS load only on pages that use a tool. Styles are scoped to `.cct-widget` and inherit the theme font.

## Structure

```
casinos-cc-tools.php           plugin header, constants, bootstrap
includes/
  class-cct-schema.php         validation of profile data
  class-cct-profiles.php       storage (private post type cct_profile, JSON in post_content)
  class-cct-shortcodes.php     shortcodes and front-end strings
  class-cct-admin.php          Casino Tools screens
  class-cct-import.php         JSON import with preview
  class-cct-updater.php        updates from GitHub Releases
public/views/                  widget markup
public/css, public/js          widget styles and script
admin/css, admin/js            admin screens
docs/, examples/               JSON format, Claude prompt, example profile
```
