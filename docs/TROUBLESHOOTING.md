# Troubleshooting

## Installation
| Problem | Fix |
|---|---|
| `composer: command not found` | Install Composer from https://getcomposer.org and reopen the terminal. |
| `php: command not found` | Install PHP 8.2+ (XAMPP, Laragon or php.net) and add it to your PATH. Check with `php -v`. |
| Composer says *"Your PHP version (8.1…) does not satisfy requirement php ^8.2"* | Upgrade PHP to 8.2 or newer. XAMPP: download the version with PHP 8.2+. |
| *"the requested PHP extension xxx is missing"* | Open `php.ini` (XAMPP: Config → PHP (php.ini)) and remove the `;` in front of `extension=xxx` (needed: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `gd`). Restart Apache/terminal. |
| *"No application encryption key has been specified"* | Run `php artisan key:generate`. |
| `.env` file missing | `cp .env.example .env` (Windows: `copy .env.example .env`). |

## MySQL
| Problem | Fix |
|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is not running. Start it in XAMPP/Laragon. Check `DB_HOST=127.0.0.1` and `DB_PORT` (XAMPP: 3306). |
| `SQLSTATE[HY000] [1045] Access denied for user` | Wrong `DB_USERNAME` / `DB_PASSWORD` in `.env`. XAMPP default: user `root`, empty password. After editing `.env` run `php artisan config:clear`. |
| `SQLSTATE[HY000] [1049] Unknown database` | Create the database first (see README step 3). |
| `could not find driver` | Enable `extension=pdo_mysql` in `php.ini` (and `pdo_sqlite` for tests). |
| `Specified key was too long` | Use MySQL 5.7+/MariaDB 10.4+ (this project uses `utf8mb4` with short keys, so it should not appear). |
| Migration half-way failed | `php artisan migrate:fresh --seed` (**deletes all tables and data**). |

## Running the API
| Problem | Fix |
|---|---|
| Browser console: *"blocked by CORS policy"* | Put your frontend address in `CORS_ALLOWED_ORIGINS` in `.env` (exact scheme + host + port, e.g. `http://localhost:5173`), then `php artisan config:clear`. Also check that the backend is running. |
| `401 Unauthenticated` | Missing/expired token. Log in again. Header must be exactly `Authorization: Bearer <token>`. |
| `403 You do not have permission…` | Wrong role: admins use `/api/admin/*`; cart/orders are for customers only. |
| `422` with `errors` | Validation message – read `errors` in the JSON; show it next to the form field. |
| `429 Too Many Attempts` | Rate limit (10 login tries/minute). Wait a minute. |
| `404` for a URL that should exist | Run `php artisan route:list` to see all URLs. All API URLs start with `/api`. |
| `500 Server Error` | Open `storage/logs/laravel.log` (the last lines show the real error). With `APP_DEBUG=true` the error is also in the response. |
| Changes in `.env` or config are ignored | `php artisan config:clear` (and `php artisan route:clear`). |
| `Class "…" not found` after adding a file | `composer dump-autoload` |
| `The stream or file storage/logs/laravel.log could not be opened` (Linux/Mac) | `chmod -R 775 storage bootstrap/cache` |
| Port 8000 already in use | `php artisan serve --port=8001` and update `VITE_API_URL` in the frontend. |

## Images
| Problem | Fix |
|---|---|
| Uploaded image URL gives 404 | Run `php artisan storage:link` once. Make sure `APP_URL` matches the address you use (e.g. `http://localhost:8000`). |
| Upload fails with 422 "image" | Allowed: jpg, jpeg, png, webp up to 2 MB. |
| Upload fails silently / 413 | Increase `upload_max_filesize` and `post_max_size` in `php.ini`. |
| Update with image does not save | Send the update as **POST** `/api/admin/products/{id}` (PHP cannot read files sent with PUT). |

## Tests
| Problem | Fix |
|---|---|
| `could not find driver (Connection: sqlite…)` | Enable `extension=pdo_sqlite` and `extension=sqlite3` in `php.ini`. |
| Image upload tests are *skipped* | Enable `extension=gd` in `php.ini`. |
| Tests touch my MySQL data? | No. `phpunit.xml` forces an in-memory SQLite database. |
