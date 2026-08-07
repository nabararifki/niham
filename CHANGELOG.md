# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.14.7] - 2026-08-07
### Fixed
- **Department Was Not Locked On The Review Page**: Every other place an asset can be filed — the single-asset create form, the Bulk Add Manual grid, the import mapping page, and `ProcessImportJob`, which stamps the importing user's own department onto every staged row — restricts staff without executive oversight to their own department. The Smart Import review page then reopened it: both the per-row Department dropdown and the bulk-edit header dropdown listed every department in the property, so a user could undo the assignment the job had just made for them. Both widgets now render as a disabled single-option select showing the user's own department, and the department list is no longer published to the page's Alpine state at all when it is locked. This was never a regression from the quick-add or multi-select work — `git log -S` shows the check has never existed on this page in any commit.
- **The Department Lock Was UI-Only Everywhere It Existed**: The lock described above was, in every place it already existed, a rendering decision with nothing behind it. `updateSingleRow()`, `bulkUpdateRows()` and `BulkAssetEntryController@store` all validated `department_id` against the active property alone, so a hand-built POST could file assets under any department in the tenant regardless of the sender's oversight. The two review endpoints now reject a foreign department with 422 *before* any write, so a rejected value cannot land on part of a bulk selection; the manual grid coerces instead, matching how that loop already treats a value the caller may not use — a native form post has nowhere useful to show a field error for a control the user was never given. Clearing the field is refused too: "only their own department" includes not blanking it. A user with no department at all keeps a free choice, exactly as the manual grid already behaved. `storeBatch()` additionally coerces at commit, so a department staged before this release cannot import a value the locked select never offered.
- **A Bulk-Edited Value Followed The Row Number Across A Delete**: After bulk-editing several rows and then deleting some of them, the row that moved up into a deleted row's position showed the deleted row's edited value. The cause was neither the staging table nor the endpoints — it was the browser. Every editable control was named `assets[<page position>][field]`, and browsers restore *dirty* control values across `location.reload()` by matching form, name and index; both typing and the bulk edit's own `field.value` assignment mark a control dirty. So the post-delete reload re-applied pre-delete values onto the controls that now belonged to different rows. Control names are keyed by staging row id now — the same identity-not-position move `e887bc3` made for the endpoints, finished on the DOM side — and the form carries `autocomplete="off"` behind it.

### Changed
- **Test Coverage**: 214 → 226 (`SmartImportTest` 139 → 149, `BulkManualEntryTest` 29 → 31). The new tests exercise the lock through the HTTP endpoints with a tampered payload rather than only asserting on markup, and pin that every remaining row's rendered selection agrees with its own staging row after a delete.

### Known Issues
- **The row-number artifact has no automated test.** The repository has no browser-driven test layer (no Dusk, no Playwright), and PHPUnit renders HTML rather than running a browser, so nothing in the suite can demonstrate value restoration happening or not happening. The new tests prove the server is identity-correct and that the position→value binding is gone from the markup; the artifact itself was verified by hand.
- The `is_invalid` and `invalidCount` inconsistencies noted in v0.14.6 are unchanged.

### Upgrade Notes
- **No migration.** No schema change in this release.
- **`npm run build` not strictly required.** The locked selects reuse utility classes the Bulk Add Manual grid already ships, and both were verified present in the current bundle. Rebuilding is still the safer default if the deploy procedure already includes it.
- **No queue drain.** `ProcessImportJob` is untouched.
- **No route changes.** A review page left open across the deploy keeps working; it will simply still show an editable Department dropdown until refreshed, and the server will now refuse any foreign department it sends.
- **Existing staging rows are unaffected.** A row staged with another department is coerced to the user's own at commit rather than blocking the import.

## [0.14.6] - 2026-08-02
### Added
- **Quick Add Category / Department From The Review Page**: A "+" beside the Category and Department column headers opens a modal that creates the entity in place. Previously a row could only point at something that already existed, so an import naming a category nobody had created yet meant abandoning the review — losing pagination, selection and unsaved edits — to go make it elsewhere. The same policy gates it that gates the create forms (`perm_categories` / `perm_departments`), and the trigger is absent from the DOM entirely without that permission rather than merely disabled. Code may be left blank, in which case `EntityCodeGeneratorService` generates it exactly as Rapid-Add already does; everything else mirrors `CategoryController::store`, including the uppercase transform. The new entity becomes selectable immediately in every row dropdown and in the bulk-edit header, with no reload.
- **Multi-Select And Bulk Editing On The Review Page**: Review rows can now be selected — a header checkbox toggling the page (indeterminate when partial), a click on any row's number cell, or a right-click that also opens a localized context menu. While rows are selected each column header shrinks and reveals a bulk-edit widget *of the same type that column uses per row* (a dropdown for category/department/status, a date picker for purchase date), so correcting a mis-mapped column across a page is one request instead of fifty. Selection is deliberately page-scoped and resets on pagination. A selected row swaps its number for a ticked box and takes its own background; Escape closes the menu, then clears the selection.
- **Bulk Delete**: The rightmost Action column is gone — it contained nothing but a per-row delete button. Deletion now acts on the selection, from either the context menu or a "Delete Selected (N)" button in the selection toolbar. The toolbar is the touch-accessible path, since right-click does not exist there.
- **Manual Header Row Selection**: Added a "Header Row" selector beside the sheet selector on the mapping page — `Auto` (unchanged heuristic) plus rows 1–15, matching the row sample the peek already reads. Auto-detection picks the row with the most non-empty cells, which loses to title banners and legend blocks with no way to correct it. The sheet selector hides entirely for single-sheet files and the label narrows accordingly.
- **Retry After A Failed Parse**: The progress modal's failure state gained a Retry button that re-dispatches the same mapping against the same already-uploaded file, plus a "possible solutions" guidance block. The modal was already an overlay on the mapping page, so neither button navigates — Close still returns to the intact column mapping.

### Fixed
- **A Date Cell Killed The Entire Import**: Fixed one date-formatted cell anywhere in an XLSX ending the import with zero rows written. `countDataRows()` cast every cell with a bare `(string)` and has none of `processFile()`'s guards; it runs first, purely to size the progress bar, so a `DateTimeImmutable` met that cast before anything else and threw. `classifyFailure()` has no branch for a bare `Error`, so it surfaced as the generic "something went wrong reading this file" with a Retry that re-ran the identical crash. Two more sites had the same shape — `processFile()` and `peek()` both guarded `DateTimeInterface` but not `DateInterval`, which is what a duration-formatted cell returns, and `peek()`'s copy takes the mapping page down rather than the job. All three now route through one shared coercion, so no cell shape can throw.
- **Dates Silently Became Integers**: A date cell with **no** date number format reads back as its raw Excel serial — `45366` rather than `2024-03-15`. That never threw, so it was never noticed; it simply turned dates into meaningless numbers. `sanitizeDate()` now takes that shape, and `DateTimeInterface`, which used to fall through its `!is_string()` guard and come back null.
- **A Bare Year Became Today's Date**: `strtotime()` reads short digit strings as a *clock time*, so `2024` in a date column returned the import date, and a cost column mis-mapped to Purchase Date would have stamped every row with it. Bare numbers are now decided before `strtotime()` sees them.
- **Columns After A Blank Or Merged Header Read The Wrong Data**: `peek()` compacted the header row while pairing it against data rows that kept their original indices, so every column after a gap displayed its neighbour's cell and the last column's data appeared nowhere. Column identity is now the original spreadsheet position throughout. A merged header forward-fills across the columns it spans (OpenSpout does expose the ranges, so no guessing), a headerless column with data under it is named after its letter instead of dropped, and duplicate names are suffixed — two columns sharing a name genuinely lost one.
- **A Gapped Header Lost To Its Own Data Row**: Auto-detection scored rows by count of filled cells, and a header with a gap in it — or merged across columns — has fewer filled cells than the data beneath it. The data row won and the real column names never reached the mapping page at all. Detection now scores by span, which gaps do not move, while still preferring the widest row so the title-banner case it was written for behaves as before.
- **Quick-Add Options Never Reached The Bulk Header Select**: A newly created category appeared in every row dropdown but not the bulk-edit header one. That select lives inside `x-if`, which Alpine rebuilds from its pristine template every time a selection starts — and when nothing is selected it is not in the document to append to. Its options now come from component state.
- **Review Rows Identified By Page Position**: Fixed `updateSingleRow()` and `deleteRow()` locating their target with `->orderBy('id')->skip($absoluteIndex)->first()` — by position on the rendered page rather than identity. A delete shifts every later row down one slot, so a debounced auto-save still holding its pre-delete index wrote to whichever row moved into that slot. Reproduced against the old code: deleting row A and then editing "index 1" (still row B to the frontend) renamed **row C** instead, silently — HTTP 200, `success: true`, and the on-screen row updated while the database took the edit elsewhere. Delete masked this with a full page reload; auto-save had no such protection. Both endpoints now look rows up by primary key.
- **Bulk Path Could Have Bypassed FK Validation**: The new bulk endpoint runs the identical category/department ownership check `updateSingleRow()` enforces, and runs it *before* any write, so a rejected value cannot apply to part of a selection. Row ids now arrive from the browser, so `user_id` + `property_id` predicates carry the whole weight — every bulk operation funnels through one `resolveOwnedRowIds()` helper, and ids the caller does not own are dropped silently rather than failing the request, since a mixed list must keep working for the legitimate ids.
- **Failed Parse Deleted The Uploaded File**: Fixed a failed import forcing a full re-upload. `ProcessImportJob` already recorded a `failed` status, but its `finally` block deleted the temp file anyway — `isCurrentAttempt()` excluded only missing, superseded and cancelled attempts, so a genuine failure passed the guard. Retrying then hit `processMapping()`'s existence check and dead-ended in the "session expired" modal. Failures now keep their file for the same reason cancellations do, and the terminal status is settled *before* the deletion decision rather than after it.
- **Raw Exception Text Shown To Users**: Fixed `setProgress()` storing `$e->getMessage()` verbatim, which surfaced library internals and absolute server paths in the browser. The job now records a locale-independent error code — necessary because the queue worker has no HTTP session, so `__()` inside the job would silently resolve to the default locale — and `status()` translates it in the user's own request.
- **Silent Success On A Headerless File**: Fixed a file whose rows never matched the expected header "succeeding" with zero rows, leaving the user on an empty review page with no explanation. It now fails with its own error code and guidance.
- **Manual Header Row Ignored By The Import Job**: `ProcessImportJob` re-finds the header by content matching, so a legend row naming two real columns would be treated as the header and the real header ingested as data. Both `processFile()` and `countDataRows()` now honour an explicit header index when one is set — they must agree, or the progress bar counts against a different offset than the one imported.
- **Wrong Sheet On A Header-Only Reload**: Fixed `columnMapping`'s `selectedSheet` defaulting to `0` from the query string, which would have dispatched the import against the wrong sheet after a header-row-only page reload.
- **Unlocalized Import Timeout**: The frontend's five-minute polling timeout message was hardcoded English; it now follows the active locale like the rest of the flow.
- **Model Field Was Never Stored**: Fixed the "Model" value collected by both the Bulk Add Manual grid and the Smart Importer being dropped at the final `INSERT INTO assets` — the `assets` table had no `model` column at all. `temporary_asset_imports.model` already existed and `ProcessImportJob` populated it correctly, so the value survived staging and review only to vanish on commit. Added via a new migration, mirroring `serial_number` (nullable `varchar(255)`, no index).
- **Model Smuggled Into Remarks**: Removed the workaround both commit paths used to keep the value from being lost entirely — writing `'Imported. Model: X'` into `remarks`, but only when `remarks` was blank, and truncated to 120 characters. `remarks` now stays clean.
- **Repeat Restore Never Refreshed Model**: Fixed `TenantRestoreService`'s asset upsert omitting `model` from its explicit `update:` column list, which would have inserted the column once and then silently never refreshed it on a repeat restore — the same silent-drop class of bug.
- **Unvalidated Bulk Entry Fields**: Added validation rules for `model`, `serial_number`, `purchase_date`, `purchase_cost` and `remarks` in `BulkAssetEntryController@store`. These reach `DB::table()->insert()` unfiltered, so an over-long value previously surfaced as a raw SQL error instead of a validation message.

### Changed
- **Unreadable Values Are Now Visible Instead Of Silently Dropped**: A cell that cannot be converted to its column's type keeps its raw text and records why, and the review page marks the row in amber — deliberately not the red invalid styling, because the row is still valid and still imports. `is_invalid` continues to mean exactly what it always meant (no name, no category); blocking these rows would turn one messy column into a page the user has to clear by hand. `storeBatch()` already dropped an unusable date or cost at save; what is new is that the drop is no longer silent. The note clears when the cell is fixed, on both the single and bulk edit paths.
- **Technical Failure Detail For Super-Admins**: The import failure modal gained a "Show Details" disclosure, released to super-admins only and omitted from the response entirely for everyone else. That is where this app already draws the line for diagnostics, and it is the useful split: the localized cause and its hint are what a tenant user can act on, while a class name only helps whoever escalates. The string is stripped of absolute paths and cut at the first stack-frame marker, so the leak the previous release closed stays closed.
- **No More Help Cursors On Tooltips**: Every tooltip trigger across the Smart Import flow — the upload help icon, the Status help icon, the locked-department badge — dropped `cursor-help`. A question-mark cursor reads as a broken page more often than as "hover me"; the icon changing colour is now the only affordance used, consistently.
- **Model Field On Single-Asset Forms**: `AssetController@store`/`@update` now accept `model`, and the create/edit forms, the show page, the add-asset modal, the PDF export and the Excel export all include it. Previously no single-asset view offered the field at all.
- **Status No Longer Marked Required**: The Smart Import mapping page and the review grid no longer flag Status as required. It is enforced nowhere and falls back to `in_service` in four independent places.
- **Smart Import Help Tooltips**: Added localized help tooltips on the mapping page's Status field and on the upload prompt. The upload tooltip recommends a Department column only to users with executive oversight — `ProcessImportJob` discards the file's department for everyone else and stamps the importer's own, so suggesting it otherwise would be misleading.

### Known Issues
- Assets imported before this change have permanently lost their model value; the source file is deleted after import, so there is nothing to backfill from.
- Two definitions of `is_invalid` coexist in `AssetImportController`. The post-mapping recalculation marks a row with neither name nor tag as invalid; `updateSingleRow()` and the new bulk path treat it as a blank placeholder and leave it valid. The bulk path deliberately mirrors the single-row rule so that editing one row and editing fifty agree; unifying all three is a separate change.
- The global `invalidCount` is not a sum of the `is_invalid` flags — `validCount` requires a non-empty name and `invalidCount` is the remainder, so a blank row reads valid per-row yet still counts against the total. Pre-existing; documented here because the bulk endpoint now surfaces both numbers in one response.
- Header detection scores by span, which is more permissive than counting filled cells: a *sparse* row inside the 15-row scan window — a title in A1 plus a page number in H1 — can outscore a real header. The previous rule had the same class of failure, which is why the manual Header Row selector exists; the shape of the files it gets wrong has simply changed.
- Merged cells in the **data** body are left exactly as the file has them, not forward-filled. "One asset spanning three rows" and "three assets sharing a department" are byte-identical, and guessing wrong would silently fabricate asset records. Only merged *headers* are resolved.
- Merge ranges cannot be read from a CSV — the format has no such concept — so a CSV with the visual shape of a merged header falls back to naming the spanned columns positionally.

### Upgrade Notes
- Requires `php artisan migrate` (two new columns: `assets.model` and `temporary_asset_imports._coercion_notes`) and `npm run build` (the tooltips, the header-row control, the failure guidance block, the selection toolbar / context menu / bulk header widgets, the quick-add trigger and the coercion warning all use newly generated Tailwind utility classes).
- **No queue drain required.** The manual header row reaches `ProcessImportJob` through the existing `import_state` cache entry rather than a new constructor argument, deliberately avoiding the serialised-job breakage that made v0.14.5 require one.
- **Two review-page routes change shape.** `POST /assets/import/delete-row` becomes `/import/delete-rows` (now taking `row_ids[]`), and `POST /assets/import/bulk-update-rows` and `/import/quick-add-entity` are new. A review page left open across the deploy will 422 on delete or auto-save until refreshed; it self-heals on reload.
- **In-flight import sessions resolve columns the old way.** The resolved column map travels through the existing `import_state` cache entry, and a session written before this release simply has no map — the job falls back to searching the raw header row, exactly as it did before. Nothing to drain, nothing to clear; running `php artisan cache:clear` after deploy just makes users restart from upload rather than finishing on the old path.

## [0.14.5] - 2026-07-28
### Fixed
- **Aborted Import Leaking Into The Next Upload**: Fixed a cancelled Smart Import bleeding state into the following upload — the progress modal showed the previous file's row count while the review page showed the latest file's data. Every cache key in the flow was scoped by user id alone, and `cancel()` stored the cancellation as a status inside `import_progress_<uid>` that the next `processMapping()` overwrote, erasing the signal while the abandoned job was still alive. `processMapping()` now stamps each dispatch with a UUID `import_id` and `ProcessImportJob` gates every side effect behind `isCurrentAttempt()`.
- **Zombie Job Wiping Live Staging Rows**: Fixed `ProcessImportJob` deleting all staging rows for the user+property as its first action with no cancellation check in front of it, letting a superseded job wipe the rows of the import the user was actually waiting on and re-insert its own.
- **Cancelling Small Imports Did Nothing**: Fixed the cancellation check existing only inside the 500-row chunk block, so any file smaller than one chunk never reached it and imported anyway.
- **Superseded Job Deleting A Shared Temp File**: Fixed cleanup deleting a temp file the live import was still reading from — cancel → reload → re-submit reuses the same `temp_file_path`. Only the current attempt may clean up; leftovers are swept by `app:clean-abandoned-imports`.
- **Partial Progress Record**: Fixed `cancel()` writing a status-only record that `status()` returned raw, leaving consumers without `percentage`/`processed`/`total`/`error`.
- **Reader Handle Leak**: Fixed the OpenSpout reader not being closed on the chunk-boundary early return.

### Changed
- **Heatmap Page Calculation**: Replaced three copies of the pagination-heatmap calculation — each pulling every staging `id` into PHP and `array_flip()`ing it, repeated on every single-cell auto-save — with a single `invalidPageNumbers()` helper backed by one PostgreSQL `ROW_NUMBER()` aggregate. Memory drops from O(rows) to O(pages containing invalid rows).
- **Import Lifecycle Test Coverage**: Added 6 tests to `SmartImportTest` (29 → 35) covering `processMapping()`, `status()`, `cancel()` and `ProcessImportJob`, which previously had none. Full suite 91 → 97.

### Upgrade Notes
- **Drain the queue before deploying.** `ProcessImportJob` takes a new required constructor argument, so jobs already serialised in the `jobs` table cannot be unserialized. Run `php artisan queue:work --stop-when-empty`, confirm the `jobs` table is empty, then deploy and restart the worker.

## [0.14.4] - 2026-07-26
### Changed
- **Staging-Based Import Architecture**: Replaced the Cache-based Smart Import staging approach with a `temporary_asset_imports` database table (`TemporaryAssetImport` model), enabling row-level `review()`/`storeBatch()`/`updateSingleRow()`/`deleteRow()` operations instead of a single serialized cache array.
- **Bulk Add Manual Separation**: Moved manual bulk asset entry out of the Smart Import review page into its own `BulkAssetEntryController`, dedicated route (`assets.bulk-manual`), and standalone view — no longer sharing Smart Import's staging endpoints.
- **Shared Date Sanitization**: Extracted `sanitizeDate()` into a `SanitizesImportDates` trait shared by `BulkAssetEntryController` and `ProcessImportJob`.
- **Import View Organization**: Moved `pagination.blade.php` under `resources/views/assets/import/partials/`.

### Fixed
- **Manual Bulk Entry False Success**: Fixed Bulk Add Manual reporting success without saving any data — its confirm button previously called the Smart Import staging endpoint (`storeBatch()`), which found zero rows because manual entries never populated the staging table.
- **Grid Fields Permanently Disabled**: Fixed a `:disabled="isEmptyRow(row)"` binding on the manual entry grid that disabled every field from the very first render (all rows start empty), making the form impossible to fill in.
- **Property Detection**: Fixed the manual entry page reporting "no active property selected" for a super-admin who had already switched to a property that simply had no categories yet.
- **Alpine Initialization**: Fixed `@json` inside `x-data` HTML attributes breaking Alpine's parser whenever an interpolated value contains a string (switched to `@js`).
- **Localized Confirmations**: Replaced native `confirm()`/`alert()` dialogs on the Smart Import review page — hardcoded in Indonesian regardless of locale — with a localized, styled modal.
- **Modal Backdrop Blur**: Fixed `backdrop-blur-*` on `<dialog>` elements not covering the full viewport by also blurring the native `::backdrop` layer.
- **Dead Routes Removed**: Removed unreachable `/select-property` routes (controller methods were never implemented) and an unbuilt "jobs" resource/route group.

## [0.14.3] - 2026-07-14
### Changed
- **Database Persistence Optimization**: Migrated the store method in `AssetImportController` to perform chunked bulk inserts (`DB::table('assets')->insert`) rather than looping individual Eloquent model creates, reducing memory consumption and improving database persistence speed.
- **Import Directory Reorganization**: Moved asset import views into a nested folder layout under `resources/views/assets/import/` (relocated `pagination.blade.php`, `rapid-add.blade.php`, and `review.blade.php`) and updated corresponding controller rendering paths.
- **Target Sheet Reader Enhancement**: Updated `AssetImportService::readRows` to accept a specific sheet index, preventing unnecessary sheet parsing.
- **Field Mapping Expansion**: Included `purchase_cost` mapping to support importing asset costs from source files.

### Fixed
- **Cleaned File Handling**: Streamlined temp file handling and removed redundant local unlinking operations inside the controller parser.

## [0.14.2] - 2026-06-27
### Fixed
- **Storage Leak Resolution**: Implemented `CleanAbandonedImports` Artisan command (`app:clean-abandoned-imports`) to prune abandoned temporary Excel/CSV import files older than 60 minutes and automatically clear expired import cache records from the database.
- **Console Scheduling**: Scheduled the cleanup command to run hourly in `routes/console.php`.
- **Feature Verification**: Added comprehensive feature test coverage in `SmartImportTest` to ensure cleanup commands execute correctly.

## [0.14.1] - 2026-06-19
### Fixed
- Resolved severe esbuild minifier compilation warnings (▲ [WARNING] Unexpected "{" [css-syntax-error]) by downgrading DaisyUI from v5 (^5.5.20) to v4 (^4.12.24), successfully restoring structural CSS syntax compatibility with the project's Tailwind CSS v3 (v3.4.17) build pipeline.

## [0.14.0] - 2026-06-16
### Added
- **Enterprise Smart Importer**: Added a low-memory, asynchronous Excel/CSV streaming import pipeline supporting 100K+ rows via `OpenSpout`.
- **Hybrid Matching Pipeline**: Added a smart matching system using Exact Match, Dictionary config, and Jaro-Winkler distance to dynamically propose column mapping.
- **Asynchronous Processing Queue**: Added `ProcessImportJob` to execute data streaming and mapping as a background task.
- **AJAX Auto-Save reviews**: Added dynamic row/cell editing and syncing back to cache in real-time during bulk review.
- **Batch Processing Modal**: Added chunked saving (500-row batches) with an active progress bar to prevent PHP timeout and memory issues.
- **Pre-flight Check**: Added calculation and display of valid vs. invalid rows.
- **Interactive Sheet Selector**: Added multi-sheet selection support for Excel files.
- **Custom Mapping Separators**: Added support for multi-column merges with custom separators.
- **Dynamic Indicators**: Added page invalidation pings/badges on the paginator dynamically synced via Alpine.js.
- **Notifications**: Added `BulkImportSuccessfulNotification` upon successful asynchronous import completion.

### Changed
- **Tailwind Config & Styling**: Expanded theme mapping and DaisyUI styles for brand harmonization.
- **Paginator Overhaul**: Replaced default pagination with a memory-optimized and error-highlighting paginator view.

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
