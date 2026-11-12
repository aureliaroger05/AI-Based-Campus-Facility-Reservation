# TODO

- [x] Inspect repo files related to adding users and editing profile (admin/add_user.php, admin/edit_user.php, profile.php, auth.php, db_init.sql)
- [ ] Update database schema (`db_init.sql`) to add birthdate, gender, first_name, middle_name, last_name, suffix (keep `full_name`)
- [ ] Update `auth.php` addUser/updateUser to insert/update new fields and recompute full_name from name parts
- [ ] Update `admin/add_user.php` form fields and POST payload to include new fields
- [ ] Update `admin/edit_user.php` form fields and POST payload to include new fields
- [ ] Update `profile.php` form fields and SQL update to include new fields and recompute full_name
- [ ] Verify no other parts break (login unaffected; reservations display unaffected)
- [ ] (After applying schema) Run `db_init.sql` and test add/edit/profile flows

