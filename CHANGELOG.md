# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.13.0] - 2026-04-05
### Added
- **Location Feature**: Implemented a comprehensive Location module to track assets by physical spaces.
- **RBAC Integration**: Integrated the new Location module into the existing Role-Based Access Control (RBAC) ecosystem, ensuring strict parity with existing permissions.

### Changed
- **UI Optimizations**: Refactored the Role Index and Detail views with a Grouped Badge System for high-density tables and improved permission visibility.

## [0.12.2] - 2026-03-24
### Added
- **Performance Indexes**: Added PostgreSQL GIN indexes for JSONB columns in `asset_histories` for optimized history lookups.
- **Attachment Metadata**: Added `uuid` and `original_name` to `attachments` table for improved public routing and download handling.

### Changed
- **Query Optimization**: Implemented eager loading across all main controllers (`Asset`, `Category`, `Department`, `User`, `Profile`, `Property`) to eliminate N+1 query bottlenecks.
- **Standardized UI/UX**: Overhauled navigation, notifications, and theme switching with unified Alpine.js logic and improved responsive design.
- **Asset History Logic**: Standardized `AssetHistory` to use native JSON casting and removed manual UUID generation in favor of database-driven integrity.
- **Controller Refinement**: Improved `AssetController` to handle non-fillable fields and better separation of concerns during storage/update.

### Fixed
- **QR Code Generation**: Standardized `QrCodeTest` to support both PNG and SVG outputs depending on server-side extensions.
- **Backup/Restore Integrity**: Fixed data type handling for JSON columns in `BackupImportLogicTest`.


## [0.12.1] - 2026-03-10
### Added
- **Standardized Release History**: Retroactively standardized all GitHub release notes (v0.1.0 - v0.12.0) derived from git history.
- **Unified CHANGELOG**: Rebuilt `CHANGELOG.md` to perfectly mirror GitHub release documentation.

### Changed
- **Routing Refactor**: Overhauled `routes/web.php` for Laravel 12 strict best practices, implementing `Route::controller()`, modern resource grouping, and optimized middleware chaining while maintaining Tenancy/i18n integrity.


## [0.12.0] - 2026-03-08
### Added
- **Native Infrastructure Migration**: Transitioned stack to native systemd-based environment on openSUSE Leap 16.0.
- Compiled `imagick` extension from source on the native host.

### Changed
- **Documentation Overhaul**: Modernized README.md and removed all legacy infrastructure references.
- **Permission Standardization**: Standardized ownership and group permissions for native runtime.

### Fixed
- Blade syntax crash in `assets/history.blade.php`.
- Restored QR Code generation logic for the native host environment.

## [0.11.1] - 2026-03-06
### Added
- **Rapid Add Workflow**: Intelligent interception layer for missing Categories/Departments during import.
- **EntityCodeGeneratorService**: Automated collision-resistant shortcode generator.
- Alpine.js Dynamic selection UI for entity mapping.
- Case-Insensitive Entity database matching.

### Fixed
- Dropdown persistence mapping in Bulk Review form.
- Direct Alpine.js binding for UI feedback instead of CSS-dependent states.

## [0.11.0] - 2026-03-06
### Added
- **Native Heuristic Parser**: Introduced `openspout` based stream parser for Smart Import.
- **Dynamic Header Detection**: Bilingual heuristic algorithm mapping for spreadsheet columns.
- **Asynchronous Modal UI**: Alpine.js and AJAX Fetch driven UI for asset addition.
- Comprehensive unit and feature tests validating garbage collection and row logic.

### Changed
- **Architectural Pivot**: Transitioned Smart Import from external Gemini AI API toward native server-side logic.

### Removed
- Gemini API Pipeline and configuration entirely securely eradicated.

## [0.10.1] - 2026-03-05
### Added
- **Nginx Infrastructure Transition**: Migrated from Apache to Nginx + PHP-FPM (Unix sockets).
- **Service Orchestration**: Optimized `nginx-pgsql` script for high-throughput management.
- **Infrastructure Blueprinting**: Production Nginx/PHP-FPM configuration captured into repository.

### Changed
- Transitioned server architecture from process-forked toward event-driven processing.

### Removed
- Entirely purged legacy Apache `.htaccess` routing files and orchestration scripts.

## [0.10.0] - 2026-03-04
### Added
- **PostgreSQL Native Schema**: Replaced MariaDB with high-performance PostgreSQL using native `uuid` and `jsonb`.
- **System Orchestration**: Introduced `apache-pgsql` orchestration script for container-level sync.
- **Data Porting Utility**: `app:port-mariadb-to-pgsql` for reliable 1:1 state transitions.

### Changed
- Explicitly enforced PHP timezone (`UTC`) and rigid PDO attributes statically.
- Replaced SQL `LIKE` with PostgreSQL (`ILIKE`) for case-insensitive search stability.

### Security & Performance
- **Strict Foreign Keys**: Explicit `cascadeOnDelete()` and `nullOnDelete()` system-wide.
- **PropertyScope Compound Indexing**: Compound lookups for `asset_histories` resolving N+1 threats.
- **UPSERT Idempotency**: Upgraded `TenantRestoreService` with `ON CONFLICT` merge constraints.

## [0.9.1] - 2026-03-04
### Added
- **Tenant-Aware Backup Engine**: Export logic rewritten to produce portable, UUID-relative JSON archives.
- **Resilient Data Restoration**: `TenantRestoreService` for seamless payload injection with UUID-re-binding.
- **Robust Cascading Deletion**: Global transaction-bound waterfall deletes for Property destruction.

### Changed
- Converted legacy soft-delete for Property deletion into an authoritative force-delete.
- Redesigned Property deletion security modal with Alpine.js confirmation logic.

### Security
- **Global Transaction Rollbacks**: Global DB rollbacks for `RestoreService` anomalies to ensure data matrix integrity.

## [0.9.0] - 2026-03-03
### Added
- Standard Laravel 12 UUID support (`HasUuids`) across all primary entities.
- Native implicit route model binding for UUID-based endpoints.
- Extensive Multi-Tenancy support via `App\Models\Scopes\PropertyScope`.

### Changed
- Overhauled `BelongsToProperty` trait for secure scope binding.
- Redesigned `PropertyScope` to strictly enforce non-null `property_id`.

### Security
- **IDOR Prevention**: Modernized routing with unbreakable non-sequential UUIDs.
- **Zero-Trust Tenant Isolation**: Global Scopes implemented at the Eloquent layer to guarantee strictly isolated contexts.

## [0.8.1] - 2026-03-01
### Fixed
- Critical asset history eager loading regression.
- Corrected sidebar glassmorphism backdrop z-index.

## [0.8.0] - 2026-03-01
### Added
- Architecture migration to openSUSE Leap 16.0.
- PHP8-imagick extension compiled from source for native QR generation.

### Changed
- Upgraded core codebase to PHP 8.4 and Laravel 12 API constraints.
- Switched Apache MPM architecture to Event-driven using PHP-FPM and proxy_fcgi.
- Refactored Eloquent attribute bindings to modern `casts(): array` syntax.
- Excised deprecated middleware from `bootstrap/app.php`.

### Security
- Enforced strict Read-Only production file environments (`chmod 555` by root).
- Exclusively carved write permissions for storage/cache to the `wwwrun` user.
- Validated all Laravel caches under unprivileged container identity.

## [0.7.1] - 2026-03-01
### Added
- Inverse bidirectional Eloquent relationships across User and Asset models.
- Formalized missing localization keys in `lang/en` and `lang/id`.

### Changed
- Restored 90% opacity backdrop-blur glass classes to responsive layouts.
- Re-structured UI hierarchy for Standalone Floating Cards.

### Fixed
- Resolved validation circumvention anti-patterns.
- Patched N+1 query regressions via explicit eager loading.
- Eradicated trailing hardcoded English strings.

## [0.7.0] - 2026-03-01
### Added
- Comprehensive English/Indonesian (i18n) localization.
- Email Digest Notification System with hourly/daily PDF reporting.
- Database cascading delete security structures.

### Changed
- UI/UX architecture enforcement of 'Floating Glass' system.
- Refactored store logic in Controllers (Categories, Departments, Roles) to replace fragile patterns with strictly validated routines.

### Fixed
- Eradicated N+1 latency loops in Asset and User index tables via eager loading.
- Standardized localized empty state fallbacks for all tables.

## [0.6.0] - 2026-02-25
### Added
- Major system overhaul: String-Based Modular Access (RBAC) and Executive Oversight abstraction.
- PDF exports functionality.
- Enhanced Floating Glass Aesthetic UI refinement.

### Changed
- Refactored Data abstraction and implemented DRY patterns system-wide.
- Synchronized repository documentation.

### Security
- Comprehensive security architecture overhaul for RBAC.

## [0.5.0] - 2026-02-25
### Added
- OCR integration via OCR.space API for smart asset data extraction.
- Implemented Intervention Image v3 for automatic image compression.
- Rapid-action Teleport Modals for mobile interactions.
- Dynamic property logos and Asset Tags integrated into QR generator.

### Changed
- Massive system refactoring (Phase 5).
- Replaced boolean permission flags with a string-based permission matrix.
- Linked Eloquent model observers to handle physical file deletions.

### Fixed
- Fixed mobile background CSS vh-stretch defect.

## [0.4.0] - 2026-02-22
### Added
- Modern Floating Glass UI for guest and authenticated layouts.
- Dynamic property branding: custom logos, backgrounds, and brand-based CSS variable mapping per tenant.
- New context-switching menu for Super Admins.
- Guest layout with full-screen dynamic backgrounds and backdrop-tuned cards.

### Changed
- Converted navigation from edge-to-edge bars to floating translucent pills.
- All primary UI cards transitioned to glassmorphism (translucency + blur).

### Fixed
- Constrained layout width to `max-w-7xl` to prevent full-width spanning.
- Hierarchical z-index layering fixes for dropdowns and navigation.
- Fixed layout clipping by removing `overflow-hidden` from main containers.

## [0.3.0] - 2026-02-22
### Added
- Property-based database separation and isolation logic.

### Changed
- Complete project sync (code cleanup, database, and documentation).

## [0.2.0] - 2026-02-19
### Added
- GitHub Semantic-Release CI workflow.

### Fixed
- Fixed Excel export functionality.

### Changed
- Standard codebase cleanup.

## [0.1.1] - 2025-10-18
### Added
- Initial generic Changelog integration.

## [0.1.0] - 2025-10-17
### Added
- Initial project generation and commit structure.
- MIT License added.
- Added LICENSE file.
- Revised README for NIHAM project overview and setup.

### Changed
- Database changes to make email nullable.

### Removed
- Removed asset value metric in the dashboard.
