# APL Connect

APL Connect is an internal enterprise administration and procurement-planning application built for APL Apollo. It provides role-based access to organization data, material consumption planning, supplier purchase orders, stock projections, API synchronization, and operational reporting.

## Main capabilities

### Administration

- Company and plant management
- User, user-type, department, designation, and reporting-hierarchy management
- Role and permission management with company-level data access
- Module activation and deactivation
- Theme and interface configuration
- Action logs and monitoring
- Configurable API integrations and synchronization history

### Procurement planning

- Material and stock synchronization
- Monthly and date-wise consumption planning
- Current stock, projected closing stock, cover days, and shortage calculations
- Manual purchase-order planning with unique PO references
- API-synchronized and manually created supplier receipts
- MOU lifting plans
- Delivery-stage tracking
- Material balance views by company, plant, material, and month
- Excel and PDF exports

### Security

- Authentication and session protection
- Role-based permissions using Spatie Laravel Permission
- Company-level filtering for dashboards, forms, dropdowns, and reports
- Security headers and audit logging
- Encrypted and secure session-cookie configuration

## Technology stack

- PHP 8.2+
- Laravel 12
- MySQL or MariaDB
- React 19
- Vite 7
- Tailwind CSS 4
- DataTables, Select2, and jQuery
- PhpSpreadsheet for Excel exports
- DOMPDF for PDF exports
- Tesseract.js for document OCR

## Requirements

- PHP 8.2 or newer
- Composer 2
- Node.js 20 or newer with npm
- MySQL 8 or MariaDB 10.4+
- Required PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, and `xml`

For local Windows development, XAMPP can provide Apache, PHP, and MariaDB.

## Installation

Clone the repository and enter the project directory:

```bash
git clone https://github.com/avnishkumar1986/Aplconnect.git
cd Aplconnect
```

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

### Windows Command Prompt

```cmd
copy .env.example .env
php artisan key:generate
```

### Linux or macOS

```bash
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apl_connect
DB_USERNAME=apl_connect_user
DB_PASSWORD=your_password
```

Create the database and database user before running migrations. Do not commit the completed `.env` file.

Run migrations and seed the initial application data:

```bash
php artisan migrate --seed
php artisan storage:link
```

Build the frontend assets:

```bash
npm run build
```

## Local development

Start the full development environment:

```bash
composer run dev
```

This starts the Laravel server, queue listener, application log viewer, and Vite development server.

Alternatively, run the services separately:

```bash
php artisan serve
php artisan queue:work --queue=api-sync,default
npm run dev
```

When using XAMPP, place the project under the Apache document root and configure the virtual host or document root to point to the project's `public` directory.

## Initial administrator

Set the initial administrator credentials in `.env` before seeding:

```dotenv
ADMIN_USERNAME=superadmin
ADMIN_PASSWORD=use-a-strong-password
```

Then run:

```bash
php artisan db:seed
```

Change the initial password after the first login.

## API integrations

APL Connect supports quality and production API environments. Configure only the credentials required for the selected environment:

```dotenv
API_ENV=quality
QUALITY_API_BASE_URL=https://quality-api.example.com
PRODUCTION_API_BASE_URL=https://production-api.example.com
API_AUTH_USERNAME=
API_AUTH_PASSWORD=
QUALITY_API_TOKEN=
PRODUCTION_API_TOKEN=
PROCUREMENT_STOCK_API_URL=
PROCUREMENT_STOCK_API_TOKEN=
API_SYNC_TIMEOUT=30
```

API credentials must remain in `.env` or the deployment secret store. Never commit production credentials.

Queue the material, stock, and vendor synchronization chain manually:

```bash
php artisan procurement:sync-apis
```

Run a worker for synchronization jobs:

```bash
php artisan queue:work --queue=api-sync,default --tries=3
```

## Scheduler

The procurement synchronization is scheduled twice daily at 06:00 and 18:00 in the `Asia/Kolkata` timezone. Expired synchronization records are cleaned hourly.

For development:

```bash
php artisan schedule:work
```

For production, execute the scheduler every minute:

```bash
php artisan schedule:run
```

On Windows, `run-scheduler.cmd` can be registered in Task Scheduler. Update its project and PHP paths if the application is installed somewhere other than `D:\xampp\htdocs\aplconnect_react`.

## Testing

Run the automated test suite:

```bash
php artisan test
```

Or use the Composer test script:

```bash
composer test
```

Run code formatting with Laravel Pint:

```bash
vendor/bin/pint
```

## Production deployment

Use production-safe environment settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

Typical deployment commands are:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Ensure that:

- The web server document root points to `public`.
- `storage` and `bootstrap/cache` are writable by the web-server user.
- A queue worker runs continuously.
- The Laravel scheduler runs every minute.
- HTTPS is enabled before secure cookies are enforced.
- Database and uploaded-document backups are configured.

## Useful commands

```bash
php artisan optimize:clear
php artisan route:list
php artisan queue:restart
php artisan schedule:list
php artisan migrate:status
```

## Repository hygiene

Do not commit generated or sensitive files, including:

- `.env`
- `.npm-cache/`
- `node_modules/`
- `vendor/`
- database exports or backups
- runtime logs, sessions, and cached views
- private certificates, tokens, or API credentials

## License

This repository contains an internal APL Connect application. Usage and distribution are subject to the organization's policies and authorization.
