# Generating a tool profile with Claude

Paste the prompt below into Claude (with the BrandFinder connector enabled), filling in the two placeholders. Claude returns one JSON file you import in **Casino Tools → Import JSON**.

Imported profiles go **Live** straight away. Check the warnings on the preview screen before you confirm. To keep a tool hidden while you check it, set `"enabled": false` for that tool in the JSON, or set the profile to Draft afterwards.

---

```
Build a Casinos.cc Tools profile (schema 1) for this casino.

Casino review URL: <https://casinos.cc/…/>
BrandFinder analysis ID: <id>

Rules:
1. Follow docs/schema.md in the devcms6/casinos-cc-tools repository exactly. Output one JSON object and nothing else.
2. Use only facts from BrandFinder (sections: bonuses, bonus_details, terms_clauses, payment_methods, currencies)
   and from the casino's own casinos.cc review pages. If a fact is in neither, leave the field out. Never guess.
3. Translate non-English source text into English for "name", "extras" and "description".
4. Wagering:
   - One offer per casino bonus a player can calculate: welcome steps, reloads, high-roller bonuses, free spins.
     Skip sports offers, cashback and wheel or lottery promotions.
   - Map "35x (D+B)" to wagering_x 35 + wagering_base "deposit_bonus" (see the mapping table in schema.md).
   - Take max_bet, days, activate_hours, excluded_methods and win_cap from terms_clauses. Quote the clause id for
     each in "sources" (internal, never shown on the site).
5. Withdrawal:
   - Add every BrandFinder payment method. supports_withdrawal 1/0/null → "yes"/"no"/"unknown".
   - Convert "€20.00" to 20.
   - VIP limits and processing times usually come from the casinos.cc Withdrawal review. Say so in "sources".
   - withdrawal.note: one short public sentence, e.g. "<Casino> doesn't publish withdrawal fees.", or leave it out.
6. Public text ("contribution_note", withdrawal "note", offer names and descriptions) must not mention
   BrandFinder, casinos.cc research, analysis IDs or dates. The site already adds
   "Based on <casino>'s terms and conditions." under each tool.
7. casino.verified_at = the BrandFinder analysis finish date (YYYY-MM-DD). casino.currency = the main currency
   the review uses.
8. After the JSON, list every field you left empty and why, and every value that came from casinos.cc rather
   than BrandFinder.
```

---

## What to check by hand before going live

- `max_bet`, `excluded_methods` and `win_cap`. BrandFinder stores these as free-text clauses, so Claude has to read them.
- Minimum deposits. BrandFinder often leaves `min_deposit` empty and Claude falls back to the review text.
- VIP limits and processing times. They are not in BrandFinder.
- That the welcome offer matches what the review page says today.
