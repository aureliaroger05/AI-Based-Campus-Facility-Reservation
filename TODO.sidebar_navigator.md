# TODO: Sidebar navigator (Reserved / Available / In use Facilities)

- [x] Update `index.php` to add a left sidebar with tabs: Reserved, Available, In use Facilities.

- [x] Implement tab logic in `index.php` using URL param `?tab=reserved|available|inuse`.


- [x] Add date/time selector for Available and In use tabs.

- [ ] Reserved tab:
  - [ ] For user: show current user's reservations.
  - [ ] For admin: show all reservations (read-only).
- [ ] Available tab: show facilities filtered by `isAvailable()` for selected datetime.
- [ ] In use tab: show facilities that have overlapping reservations for selected datetime.
- [x] Add sidebar CSS to `assets/css/style.css`.

- [x] Verify reservation duplication prevention remains intact (POST still handled by `reservations.php` + `isAvailable()`).

- [ ] Manual browser test for both admin and user.

