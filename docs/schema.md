# Tool profile JSON (schema 1)

One file describes one casino. The import screen also accepts an array of profiles, or `{ "profiles": [ … ] }`.
A machine-readable version is in [`schema.json`](schema.json). A full example is in [`../examples/golazzo-casino.json`](../examples/golazzo-casino.json).

Rules that apply everywhere:

- Amounts are plain numbers in the casino's currency: `20`, not `"€20"`.
- Leave a field out (or set it to `null`) when the fact isn't known. The tool hides the row or warning instead of guessing. Never invent a value.
- Text fields are plain text. HTML is stripped.
- Under every tool the site shows “Based on [casino name]’s terms and conditions.”, followed by the tool’s public note. Public notes must not mention research tools, data sources or dates.
- `brandfinder_analysis_id`, `verified_at` and `sources` are internal. They are never sent to the page.

## `casino`

| Field | Required | Meaning |
|---|---|---|
| `name` | yes | Display name, e.g. `Golazzo Casino` |
| `slug` | yes | Unique key. Imports with the same slug update the existing profile. Use the review URL slug. |
| `review_url` or `review_post_id` | recommended | The main review page. Shortcodes on this page and its child pages find the profile automatically. |
| `currency` | yes | 3-letter code, e.g. `EUR`, `CAD` |
| `brandfinder_analysis_id` | no | BrandFinder analysis the facts came from |
| `verified_at` | recommended | `YYYY-MM-DD` date the facts were checked. Internal: shown in the admin only. |

## `wagering` (Wagering calculator)

| Field | Meaning |
|---|---|
| `enabled` | `true` to allow `[cc_wagering_calculator]` |
| `max_bet` | Max bet while a bonus is active. Omit if not stated. |
| `days` | Default days to complete wagering |
| `activate_hours` | Hours to activate the bonus after depositing |
| `excluded_methods` | Deposit methods that don't qualify for bonuses, e.g. `["Skrill","Neteller"]` |
| `win_cap.multiplier` / `win_cap.countries` | Max win as a multiple of the bonus, and the countries it applies to |
| `contribution_note` | Public note shown under the tool, after “Based on [casino]’s terms and conditions.” |
| `sources` | Where the facts came from. **Internal**: shown in the admin only, never on the site. |
| `offers` | List of offers, below |

### Offer

| Field | Meaning |
|---|---|
| `id` | Short key, unique within the casino |
| `name` | Name in the dropdown |
| `kind` | `deposit` (match bonus) or `spins` (free spins; player enters the spin winnings) |
| `match_pct` | e.g. `100` for 100%. Required for `deposit`. |
| `max_bonus` | Bonus cap. Required for `deposit`. |
| `min_deposit` | Minimum qualifying deposit |
| `wagering_x` | Multiplier, e.g. `35`. Use `0` for no wagering. Required. |
| `wagering_base` | What the multiplier applies to: `deposit_bonus`, `bonus`, `deposit` or `winnings` |
| `extras` | Text such as `50 FS + €5 Free Bet` |
| `extras_min_deposit` | Deposit needed to get the extras, if higher than `min_deposit` |
| `days` | Days to wager, if different from the default |
| `description` | Optional help line under the dropdown. Generated from the numbers when empty. |

BrandFinder wagering strings map like this:

| BrandFinder | `wagering_x` | `wagering_base` |
|---|---|---|
| `35x (D+B)` | 35 | `deposit_bonus` |
| `40x (B)` or `40x bonus` | 40 | `bonus` |
| `40x` on a free-spins offer | 40 | `winnings` |
| `No wagering` | 0 | `bonus` |

## `withdrawal` (Withdrawal checker)

| Field | Meaning |
|---|---|
| `enabled` | `true` to allow `[cc_withdrawal_checker]` |
| `methods` | List of payment methods, below |
| `default_time.other` / `default_time.bank` | Processing time used when a method has none of its own |
| `limits` | Withdrawal limits per VIP level: `label`, `daily`, `weekly`, `monthly` (any can be omitted). Leave the list empty for no limits; the VIP field is then hidden. |
| `note` | Public note shown under the tool, after “Based on [casino]’s terms and conditions.” |
| `sources` | Where the facts came from. **Internal**: never shown on the site. |

### Method

| Field | Meaning |
|---|---|
| `name` | e.g. `Bitcoin` |
| `type` | `crypto`, `ewallet`, `bank`, `card`, `voucher` or `other` |
| `withdrawals` | `yes`, `no` (deposit only) or `unknown` |
| `min_withdrawal` | Minimum withdrawal, when `withdrawals` is `yes` |
| `time` | Processing time, e.g. `Up to 24 hours` |
| `fee` | Fee text, if published |

BrandFinder `payment_methods` map as: `supports_withdrawal` 1 → `yes`, 0 → `no`, null → `unknown`; `method_category` → `type`; `min_withdrawal` "€20.00" → `20`; `withdrawal_time` → `time`; `withdrawal_fee` → `fee`.
