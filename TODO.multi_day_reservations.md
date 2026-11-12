# Multi-day reservation (checkbox days)

- [ ] Update `index.php` reservation form: replace single `reserve_date` with checkbox group (next 7 days) named `reserve_dates[]`.
- [ ] Update `reservations.php` POST handler: read `reserve_dates[]` and loop create reservation per date.
- [ ] Adjust success/failure redirect logic: success if at least one date reservation created.
- [ ] Smoke test: reserve multiple days for same facility/time; verify multiple DB rows + UI shows them.

