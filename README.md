# AI-Based Campus Facility Reservation System with QR & Forecasting

## Features
- Welcome page: Opening the project starts on a welcome screen with a sign-in button; signing in continues to the reservation calendar.
- Secure login (admin-only user creation)
- Home page: Reserve facilities, view availability, AI usage forecast charts
- Reservation times: On the current date, passed start times are removed from the available-time list and cannot be submitted. The server rechecks the current time using Asia/Manila time.
- Admin dashboard: Add users/facilities, stats
- Profile: View/edit, my reservations with QR codes
- AI: Predicts usage based on historical logs (simple avg + variance)
- QR: Generated locally for each reservation
- QR check-in: Scan a reservation QR code using a phone browser signed in to an administrator account. If the phone is not signed in as an administrator, sign in when prompted and the scan will continue automatically. A confirmed reservation is checked in and a large check mark with its details is shown. Each QR code also carries a unique check-in token. Apply `migrations/20261020_add_reservation_checkin_tokens.sql` to existing databases; old printed QR codes must be refreshed from the reservation page before use.

## Setup (XAMPP)
1. Start Apache & MySQL in XAMPP Control Panel
2. Import DB: `mysql -u root -p < db_init.sql` (creates `reservation_sys`)
3. Run `composer install` in the project directory to install the local QR generator.
4. For existing databases, run `migrations/20261020_add_reservation_checkin_tokens.sql` and `migrations/20261015_add_completed_reservation_status.sql`.
5. Access the app using the same host name or IP that reservation QR codes should open.
6. Login: admin / password
7. Admin: Add facilities/users, dashboard at /admin/
8. Reserve: Select facility/date/time, view QR/profile

## Deploying on Render
- Render runs this app as a Docker web service. Push the project to GitHub with the `Dockerfile` and `.dockerignore` included, then create a new Render Web Service from that repository and select the Docker runtime.
- Create a MySQL database with an external provider that permits connections from Render. Render does not provide a managed MySQL database. Import `db_init.sql` into that database, then apply the required migrations.
- In the Render service's Environment settings, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` to the values from the MySQL provider. The container listens on Render's `PORT` environment variable.
- The app writes profile photos and QR codes to `uploads/`. Render's container filesystem is temporary, so configure persistent storage mounted at `/var/www/html/uploads` if these files must survive redeployments.
- The AI chat expects Ollama on the same machine and will not work on Render without a separately hosted Ollama service and a configurable endpoint.
- Change the seeded admin password immediately after importing the database.

## AI system assistant
- The AI chat widget is available across pages and can explain system features and workflows. It does not access private user records or perform reservation/admin actions.
- Install [Ollama for Windows](https://ollama.com/download), then open PowerShell or Command Prompt and run `ollama pull qwen2.5:3b` to download the default model.
- Keep Ollama running while using the chat. The PHP endpoint connects to Ollama at `http://127.0.0.1:11434`; no API key or paid provider account is required.
- Optionally set the `OLLAMA_MODEL` environment variable to another model already downloaded in Ollama. The default is `qwen2.5:3b`.
- Model downloads use disk space, and responses use your PC's memory and processing power. If Ollama or the model is unavailable, the chat shows a setup message.

## Automatic reservation completion
- Apply `migrations/20261015_add_completed_reservation_status.sql` to the `reservation_sys` database to add the `completed` status.
- The app uses the `Asia/Manila` timezone for reservation dates and times. The CLI-only task `tasks/complete_reservations.php` compares reservation end times against this same local clock and changes expired confirmed reservations to `completed`. Reservations already scanned in as `used` keep that status.
- A Windows Task Scheduler task named `Reservation Auto Completion` runs the checker every minute, so an expired reservation is completed within one minute even if the website is closed. Keep the computer on and MySQL running for automatic updates.

## Tech
- PHP8+, MySQL, PDO, Bootstrap5, Chart.js
- AI: PHP stats (extend ai_forecast.php for ML)
- QR: Locally generated SVG QR codes using `chillerlan/php-qrcode` (no external QR service)

## Usage
- Admin adds facility w/ slots (comma sep).
- Users reserve avail slots.
- Forecast shows next 7 days predicted usage.
- QR links to `view_res.php?id=RES_ID` (scan to view the reservation while signed in as its owner).

Enjoy! Extend AI with real ML (e.g., PHP-ML lib) or forecasting algos.
