# AI Agent developer guide (AGENTS.md)

This file contains technical details, development environment configurations, and implementation specifics of the Account Analyzer project. It serves as context for AI coding agents and human developers.

---

## Technical Overview
* **Application Type**: PHP 8.3 web application with PSR-4 autoloading.
* **Core Goal**: Parse exported bank account CSV files (specifically supporting ING format) and render a dashboard showing totals, monthly breakdowns, and list views.
* **Templating**: Powered by `typo3fluid/fluid` to render views.
* **Testing**: PHPUnit tests in the `Tests/` directory.

---

## Environment Configuration
* **Local Development**: Built and run using **DDEV**.
  * Project Name: `konto`
  * Project TLD: `konto.ddev.site`
  * PHP Version: `8.3` (specifically PHP `8.3.31`)
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
* **Reality**: Requires **PHP 8.3** (configured in DDEV). Uses modern PHP 8.3 features such as typed class constants, match expressions, and Intl localization.
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

## Testing & Privacy Policy
* **Fictional Data Only**: All test files under `Tests/Unit/CsvParserTest.php` must strictly use anonymized, fictional test data and names (e.g., *Max Mustermann*).
* **Safe Values**: Transaction amounts in unit tests must be kept strictly under `1,400 EUR` to avoid any realistic representations of private salary details.

---

## Coding Guidelines & Style Preferences

### 1. Class & Method Boundaries
* **Class Length**: Any PHP class must not exceed **1,000 lines of code**.
* **Method Length**: Any PHP method must not exceed **20 lines of code** (excluding comments, annotations, and empty lines).
* **Line Length**: Maximum line length is **~130 characters**. Wrap long lines.
* **Decomposition**: Extract complex `if` condition expressions into self-documenting `is...()` or `has...()` boolean helper methods.

### 2. Class Modifiers & Design
* **`final` Classes**: Standalone PHP classes must be declared `final` since inheritance is not intended.
* **`readonly class`**: ONLY use `readonly class` on pure Value Objects / DTOs (e.g., `Transaction`). NEVER declare Services, Controllers, or Parsers as `readonly class`.
* **Constructor Property Promotion**: Do NOT duplicate `readonly` keywords on constructor properties if the class itself is already declared `readonly class`.
* **Stateless Architecture**: Services, Parsers, and Helper classes must be strictly **stateless** (0 instance properties). Controllers may hold injected dependencies (e.g., `TemplateView`, Repositories). State is reserved for Models, DTOs, and Enums.
* **No Pass-By-Reference**: Do NOT use pass-by-reference (`&`) arguments to mutate state in methods. Methods must return calculated values directly.

### 3. Performance & PHP 8.3 Practices
* **Array Empty Checks**: NEVER use `count($arr) > 0` or `count($arr) === 0` to check for empty/non-empty arrays. Use strict type comparisons `$arr !== []` or `$arr === []` for micro-second parsing performance.
* **File Checking**: Do NOT use `file_exists()`. Use `is_file()` (or `is_dir()`) specifically.
* **Typed Class Constants**: Unchanged array/scalar values must be declared as typed class constants (e.g., `private const array DEFAULT_COLUMN_INDICES = [...]`).
* **Native PHP Features & Localization**: Use native PHP extensions (e.g., `IntlDateFormatter` via `ext-intl`) for standard calendar date formatting instead of custom enums or translation arrays.

### 4. Git Commits & AI Attribution
* **Sign-off**: All commits must use `-s` (`Signed-off-by`).
* **AI Co-Author**: Always attribute AI assistance in commit messages using the trailer:
  ```text
  Co-authored-by: Antigravity <antigravity@google.com>
  ```
