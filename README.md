# Account Analyzer

A lightweight PHP web application designed to parse and analyze exported bank account CSV files (specifically supporting ING format) and render a dashboard showing totals, monthly breakdowns, and transaction list views.

## Installation

You can install this package via Composer:
```bash
composer require stefanfroemken/account-analyzer
```

## Requirements & Local Development

* **PHP Version**: PHP 8.3 (configured via DDEV).
* **Database**: No database required.
* **Environment**: Local development is powered by **DDEV**.
* **Webserver**: Nginx (`nginx-fpm`).

## Usage

1. Start your local environment:
   ```bash
   ddev start
   ```
2. Open your browser at `https://konto.ddev.site` (or open `index.php`).
3. Select and upload your ING bank account CSV file.
4. Uploaded files are processed in temporary memory and immediately deleted after parsing. No uploaded files are stored on disk.

## Views

* **Month View**: Displays transactions grouped by month with monthly income, expenses, and net differences.
* **Year View**: Provides a full year summary of monthly totals.

## Testing

Run the PHPUnit test suite inside the DDEV environment:
```bash
ddev exec ./vendor/bin/phpunit Tests
```
