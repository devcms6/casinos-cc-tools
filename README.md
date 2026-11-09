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
2. Commit, then tag and push:
   ```bash
   git tag v0.2.0
   git push origin main v0.2.0
   ```
3. The **Release** workflow checks the versions match, lints the PHP, builds `casinos-cc-tools.zip` and publishes the release.

## Adding a casino

**By hand:** Casino Tools → Add casino. Fill in the casino, switch on the tools you want, add offers, methods and limits. Save as Draft until the facts are checked.

**From JSON:** Casino Tools → Import JSON. Upload or paste one profile or many. The preview shows what will be created or updated, with errors and warnings, before anything is saved. Profiles are matched by `casino.slug`, so re-importing updates instead of duplicating.

- JSON format: [docs/schema.md](docs/schema.md) and [docs/schema.json](docs/schema.json)
- Example: [examples/golazzo-casino.json](examples/golazzo-casino.json)
- Prompt for generating a profile from BrandFinder with Claude: [docs/claude-prompt.md](docs/claude-prompt.md)

Every save and import keeps a revision, so you can roll a casino back from its edit screen.

## Shortcodes

| Shortcode | Notes |
|---|---|
| `[cc_wagering_calculator]` | Casino found from the page or its parent review |
| `[cc_withdrawal_checker]` | Same |
| `[cc_tool type="wagering"]` | Generic form |
| `casino="golazzo-casino"` | Optional on all: use a specific profile |
| `title="…"` `intro="…"` | Optional heading and intro text above the tool |
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
