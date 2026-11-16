# AI-Based Campus Facility Reservation System with QR & Forecasting

## Features
- Welcome page: Opening the project starts on a welcome screen with a sign-in button; signing in continues to the reservation calendar.
- Secure login (admin-only user creation)
- Home page: Reserve facilities, view availability, AI usage forecast charts
- Reservation times: On the current date, passed start times are removed from the available-time list and cannot be submitted. The server rechecks the current time using Asia/Manila time.
- Admin dashboard: Add users/facilities, stats
- Class schedules: Admins can import dated facility class blocks from a CSV template. Class times are excluded from reservation availability and checked again by the server when a reservation is submitted.
- Profile: View/edit, my reservations with QR codes
- AI: Predicts usage based on historical logs (simple avg + variance)
- QR: Generated locally for each reservation
- QR check-in: Scan a reservation QR code using a phone browser signed in to an administrator account. If the phone is not signed in as an administrator, sign in when prompted and the scan will continue automatically. A confirmed reservation is checked in and a large check mark with its details is shown. Each QR code also carries a unique check-in token. Apply `migrations/20261020_add_reservation_checkin_tokens.sql` to existing databases; old printed QR codes must be refreshed from the reservation page before use.

## Setup (XAMPP)
1. Start Apache & MySQL in XAMPP Control Panel
2. Import DB: `mysql -u root -p < db_init.sql` (creates `reservation_sys`)
3. Run `composer install` in the project directory to install the local QR generator.
4. For existing databases, run `migrations/20261112_create_class_schedules.sql`, `migrations/20261020_add_reservation_checkin_tokens.sql`, and `migrations/20261015_add_completed_reservation_status.sql`.
5. Access the app using the same host name or IP that reservation QR codes should open.
6. Login: admin / password
7. Admin: Add facilities/users, dashboard at /admin/
8. Reserve: Select facility/date/time, view QR/profile

## Importing class schedules
- Apply `migrations/20261112_create_class_schedules.sql` to an existing database before deploying this feature. Fresh databases get the table from `db_init.sql`.
- In the admin dashboard, open **Import Class Schedule** and download the CSV template. Fill one row per class with the existing facility name, subject, specific date, start time, and end time.
- Required CSV headers are `facility,subject,date,start_time,end_time`. Dates may use `YYYY-MM-DD` or `M/D/YYYY`; times may use 24-hour `HH:MM`/`HH:MM:SS` or 12-hour AM/PM. Excel files should be saved as CSV before upload.
- The import is all-or-nothing. Unknown facilities, invalid values, and overlapping class rows are reported without importing any of the file. Re-uploading already imported time blocks does not create duplicates.
- Reservations are blocked for class schedule overlaps in both the calendar and server-side validation. This initial importer expects specific calendar dates, not recurring weekday schedules.

## Deploying on Render
- Render runs this app as a Docker web service. Push the project to GitHub with the `Dockerfile` and `.dockerignore` included, then create a new Render Web Service from that repository and select the Docker runtime.
- Create a MySQL database with an external provider that permits connections from Render. Render does not provide a managed MySQL database. Import `db_init.sql` into that database, then apply the required migrations.
- In the Render service's Environment settings, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` to the values from the MySQL provider. The container listens on Render's `PORT` environment variable.
- The app writes profile photos and QR codes to `uploads/`. Render's container filesystem is temporary, so configure persistent storage mounted at `/var/www/html/uploads` if these files must survive redeployments.
- The AI chat uses Gemini from the PHP server. Set `GEMINI_API_KEY` in the Render service Environment settings; do not put the key in frontend code or commit it.
- Change the seeded admin password immediately after importing the database.

## AI system assistant
- The AI chat widget is available across pages and can explain system features and workflows. It does not access private user records or perform reservation/admin actions.
- Create a Gemini API key in [Google AI Studio](https://aistudio.google.com/app/apikey) and configure it as the server environment variable `GEMINI_API_KEY`. The key stays on the server and is never sent to the browser.
- For XAMPP, add `SetEnv GEMINI_API_KEY "your-key"` to Apache's `httpd.conf`, then restart Apache. Do not commit the key. For Render, add `GEMINI_API_KEY` in the service Environment settings.
- The default model is `gemini-3.5-flash-lite`. Set the optional `GEMINI_MODEL` server environment variable to use another supported Gemini model.
- Because Gemini is called by the PHP server, client devices do not need Ollama or a local AI model. They do need to open the site using a host/IP reachable from that device; `localhost` only refers to the device itself.
- Gemini requires internet access from the server and an active API key/quota. Provider outages and rate limits can still cause temporary interruptions; the chat reports these clearly.

## Automatic reservation completion
- Apply `migrations/20261015_add_completed_reservation_status.sql` to the `reservation_sys` database to add the `completed` status.
- The app uses the `Asia/Manila` timezone for reservation dates and times. The CLI-only task `tasks/complete_reservations.php` compares reservation end times against this same local clock and changes expired confirmed reservations to `completed`. Reservations already scanned in as `used` keep that status.
- A Windows Task Scheduler task named `Reservation Auto Completion` runs the checker every minute, so an expired reservation is completed within one minute even if the website is closed. Keep the computer on and MySQL running for automatic updates.

## Tech
- PHP8+, MySQL, PDO, Bootstrap5, Chart.js
- AI chat: Google Gemini API; usage forecast: historical usage estimates
- QR: Locally generated SVG QR codes using `chillerlan/php-qrcode` (no external QR service)

## Usage
- Admin adds facility w/ slots (comma sep).
- Users reserve avail slots.
- Forecast shows next 7 days predicted usage.
- QR links to `view_res.php?id=RES_ID` (scan to view the reservation while signed in as its owner).

Enjoy! Extend AI with real ML (e.g., PHP-ML lib) or forecasting algos.
