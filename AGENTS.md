# AI Agent developer guide (AGENTS.md)

This file contains technical details, development environment configurations, and implementation specifics of the Account Analyzer project. It serves as context for AI coding agents and human developers.

---

## Technical Overview
* **Application Type**: PHP-based web application with PSR-4 autoloading.
* **Core Goal**: Parse exported bank account CSV files (specifically supporting ING format) and render a dashboard showing totals, monthly breakdowns, and list views.
* **Templating**: Powered by `typo3fluid/fluid` to render views.
* **Testing**: PHPUnit tests in the `Tests/` directory.

---

## Environment Configuration
* **Local Development**: Built and run using **DDEV**.
  * Project Name: `konto`
  * Project TLD: `konto.ddev.site`
  * PHP Version: `8.3`
  * Webserver: Nginx (`nginx-fpm`)
  * Database: MariaDB `10.11` (configured in DDEV, but the codebase does not use any database).
* **Running Tests**: Tests must be executed inside the DDEV container:
  ```bash
  ddev exec ./vendor/bin/phpunit Tests
  ```

---

## Code Architecture
* **Entry Point**: `index.php` routes request handling to `StefanFroemken\AccountAnalyzer\Controller\WebController`.
* **Classes**:
  * `Controller\WebController`: Handles uploading of CSV files and routes data to views.
  * `Model\Transaction`: Readonly value object representing a single CSV transaction.
  * `Service\CsvParser`: Reads and parses CSV files.
  * `Service\ReportService`: Aggregates transactions into monthly summaries.

---

## Key Implementation Details & Core Discrepancies with `README.md`
Please note the following differences between the actual code behavior and the legacy statements in `README.md`:

### 1. PHP Version Requirement
* **Reality**: Strictly requires **PHP 8.1+** (currently running PHP `8.3` in DDEV). The code uses modern PHP features such as constructor property promotion and `readonly` properties.
* **README Discrepancy**: Legacy README states PHP 7.1-7.4 works, which is outdated and incorrect for the current codebase.

### 2. File Upload & Processing
* **Reality**: Files are processed directly from the system's temporary directory (`$_FILES['csv_file']['tmp_name']`) and immediately deleted after parsing using `@unlink()`.
* **README Discrepancy**: Legacy README mentions storing files in an `Uploads/` directory and clearing cache. No `Uploads/` directory or cache clearing logic exists in the project.

### 3. Application Configuration
* **Reality**: Template paths are hardcoded directly within `WebController.php`.
* **README Discrepancy**: Legacy README mentions a `Configuration/Main.yaml` file; no such directory or configuration file exists in the repository.

### 4. Robust CSV Parsing
* **Reality**: The `CsvParser` uses dynamic column mapping by scanning the CSV header row. It maps columns based on keywords (resilient to different casing and German umlauts like `Währung`/`Whrung`). It does not require a fixed number of columns, making it compatible with CSV variations (such as with or without a `Notiz` column).

---

## Testing Policy
* **Fictional Data Only**: All test files under `Tests/Unit/CsvParserTest.php` must strictly use anonymized, fictional test data and names (e.g., *Max Mustermann*).
* **Safe Values**: Transaction amounts in unit tests must be kept under `1,400 EUR` to avoid any realistic representations of private salary details.
