<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bulk Add Manual — a standalone feature, deliberately independent of the
 * Smart Import pipeline (upload → mapping → staging table → review → storeBatch).
 *
 * These tests exist because the manual flow used to be wired into Smart Import's
 * review page, whose confirm button always called storeBatch(). Since the manual
 * flow never populates temporary_asset_imports, storeBatch() found zero valid rows
 * and returned success without inserting anything — a silent false success.
 * The assertions below pin the two properties that prevent a regression:
 * data is really persisted, and the staging table is never involved.
 */
class BulkManualEntryTest extends TestCase
{
    use RefreshDatabase;

    protected Property $propertyA;

    protected Property $propertyB;

    protected User $userA;

    protected User $userB;

    protected Category $categoryA;

    protected Category $categoryB;

    protected Department $departmentA;

    protected Department $departmentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyA = Property::factory()->create(['name' => 'Hotel Alpha', 'code' => 'HA']);
        $roleA = Role::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'Admin A',
            'perm_assets' => 'full access',
        ]);
        $this->departmentA = Department::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'IT Alpha',
        ]);
        $this->categoryA = Category::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'Electronics A',
        ]);
        $this->userA = User::factory()->create([
            'property_id' => $this->propertyA->id,
            'role_id' => $roleA->id,
            'department_id' => $this->departmentA->id,
        ]);

        $this->propertyB = Property::factory()->create(['name' => 'Hotel Beta', 'code' => 'HB']);
        $roleB = Role::factory()->create([
            'property_id' => $this->propertyB->id,
            'name' => 'Admin B',
            'perm_assets' => 'full access',
        ]);
        $this->departmentB = Department::factory()->create([
            'property_id' => $this->propertyB->id,
            'name' => 'IT Beta',
        ]);
        $this->categoryB = Category::factory()->create([
            'property_id' => $this->propertyB->id,
            'name' => 'Electronics B',
        ]);
        $this->userB = User::factory()->create([
            'property_id' => $this->propertyB->id,
            'role_id' => $roleB->id,
            'department_id' => $this->departmentB->id,
        ]);
    }

    /**
     * Build a single valid asset row for the manual grid payload.
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'tag' => 'MAN-001',
            'name' => 'Manual Asset',
            'category_id' => $this->categoryA->id,
            'department_id' => $this->departmentA->id,
            'status' => 'in_service',
        ], $overrides);
    }

    // ══════════════════════════════════════════════════════════════
    // 1. PAGE RENDERING
    // ══════════════════════════════════════════════════════════════

    public function test_page_loads_for_authorized_user(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee(__('assets.bulk_add_manual'));
    }

    public function test_page_requires_authentication(): void
    {
        $response = $this->get(route('assets.bulk-manual'));

        $response->assertRedirect(route('login'));
    }

    /**
     * The standalone page must not drag any part of the Smart Import wizard
     * along with it — no stepper, and above all no reference to the staging
     * endpoints that caused the original false-success bug.
     */
    public function test_page_does_not_render_smart_import_wizard(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertDontSee(__('assets.step_mapping'));
        $response->assertDontSee('import/store-batch');
        $response->assertDontSee('import/update-row');
        $response->assertDontSee('import/delete-row');
        $response->assertDontSee('import-calculate-validation');
    }

    public function test_page_posts_to_its_own_standalone_route(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee(route('assets.bulk-manual.store'), false);
    }

    public function test_page_scopes_entities_to_active_property(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee('Electronics A');
        $response->assertDontSee('Electronics B');
    }

    public function test_non_executive_user_gets_locked_department(): void
    {
        $this->departmentA->update(['is_executive_oversight' => false]);
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee('lockedDepartmentId', false);
        $response->assertSee((string) $this->departmentA->id, false);
    }

    /**
     * The option labels append the property name for super-admins only, so this
     * branch is invisible to a regular-user test — `isSuperAdmin() && $cat->property`
     * short-circuits before the relation is ever touched. Under
     * Model::shouldBeStrict() that unguarded relation access is a hard error,
     * which is exactly how this page first broke in the browser.
     *
     * Two extra rows per table are load-bearing, not padding: Builder::hydrate()
     * only copies the lazy-loading guard onto hydrated models when the query
     * returned more than one row (`if (count($items) > 1)`). With a single
     * category the flag stays false and the violation never fires, so a
     * one-row fixture cannot reproduce this.
     */
    public function test_super_admin_page_loads_without_lazy_loading_violation(): void
    {
        Category::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'Furniture A',
        ]);
        Department::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'Housekeeping Alpha',
        ]);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_id' => $this->propertyA->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['active_property_id' => $this->propertyA->id])
            ->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        // Super-admins see the owning property appended to each option label.
        $response->assertSee('Electronics A - Hotel Alpha');
        $response->assertSee('Furniture A - Hotel Alpha');
        // Super-admins have executive oversight, so the full department select
        // renders too — pinning the second unguarded relation access.
        $response->assertSee('IT Alpha - Hotel Alpha');
        $response->assertSee('Housekeeping Alpha - Hotel Alpha');
    }

    /**
     * The grid seed data is interpolated into the `x-data` attribute. `@json`
     * emits literal double quotes, which terminate the attribute at the first
     * one and hand Alpine a SyntaxError — killing the whole component: dead
     * buttons, no rows rendered, and every x-cloak element stuck hidden.
     * `@js` escapes them as ". This asserts the rendered bytes.
     */
    public function test_grid_seed_data_is_attribute_safe(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $html = $response->getContent();

        $start = strpos($html, 'x-data="bulkManualGrid');
        $this->assertNotFalse($start, 'The bulkManualGrid component root is missing.');

        // Slice out the attribute value and prove it closes where we intend it to.
        $valueStart = strpos($html, '"', $start + strlen('x-data=')) + 1;
        $valueEnd = strpos($html, '"', $valueStart);
        $attribute = substr($html, $valueStart, $valueEnd - $valueStart);

        $this->assertStringContainsString('JSON.parse(', $attribute,
            'Seed data must be injected with @js so it survives inside the attribute.');
        $this->assertStringContainsString('lockedDepartmentId', $attribute,
            'The attribute was truncated early — a raw quote broke out of it.');
        $this->assertStringNotContainsString('rows: [{"', $html,
            'Raw JSON quotes in the x-data attribute break Alpine (regression of the @json bug).');
    }

    /**
     * Grid fields must never be disabled based on the row's own current
     * emptiness. `:disabled="isEmptyRow(row)"` looks reasonable (skip
     * untouched starter rows) but is a chicken-and-egg bug: every row starts
     * empty, so every field would be disabled from first render — and a
     * disabled field can never receive the keystroke that would make it
     * non-empty. The grid would be permanently unusable, with no error
     * anywhere to reveal why. Untouched rows must instead be excluded from
     * the payload at submit time (see the onSubmit handler), never by
     * disabling inputs during editing.
     */
    public function test_grid_fields_are_never_disabled_based_on_row_emptiness(): void
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertDontSee(':disabled="isEmptyRow(row)"', false);

        // The replacement mechanism must be present: a stable per-row marker
        // for onSubmit() to locate rows in the DOM, and the actual stripping
        // of `name` (not `disabled`) on untouched rows before the native POST.
        $response->assertSee(':data-row-index="index"', false);
        $response->assertSee("removeAttribute('name')", false);
    }

    public function test_executive_user_gets_full_department_select(): void
    {
        $this->departmentA->update(['is_executive_oversight' => true]);
        $this->actingAs($this->userA->fresh());

        $response = $this->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee('IT Alpha');
    }

    /**
     * A super-admin who has switched to a property via the normal switcher must
     * get the grid, not the "no active property" banner.
     */
    public function test_super_admin_with_active_property_sees_the_grid(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_id' => $this->propertyA->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['active_property_id' => $this->propertyA->id])
            ->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee(route('assets.bulk-manual.store'), false);
        $response->assertDontSee(__('assets.no_active_property'));
    }

    /**
     * An empty $categories collection has two very different causes. A property
     * that is correctly selected but simply has no categories yet must NOT be
     * reported as "you haven't selected a property" — that sent users back to
     * the switcher to fix something that was never wrong.
     */
    public function test_property_without_categories_shows_distinct_message(): void
    {
        $emptyProperty = Property::factory()->create(['name' => 'Hotel Gamma', 'code' => 'HG']);
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_id' => $this->propertyA->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['active_property_id' => $emptyProperty->id])
            ->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee(__('assets.no_categories_yet'));
        $response->assertDontSee(__('assets.no_active_property'));
    }

    /**
     * The opposite guard: genuinely having no active property must still warn.
     */
    public function test_no_active_property_still_shows_banner(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_id' => $this->propertyA->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['active_property_id' => null])
            ->get(route('assets.bulk-manual'));

        $response->assertStatus(200);
        $response->assertSee(__('assets.no_active_property'));
        $response->assertDontSee(__('assets.no_categories_yet'));
    }

    // ══════════════════════════════════════════════════════════════
    // 2. PERSISTENCE — the core of the bug fix
    // ══════════════════════════════════════════════════════════════

    /**
     * Direct regression for the false success: a successful response is not
     * enough, the Asset must actually exist in the database afterwards.
     */
    public function test_manual_entry_actually_persists_assets(): void
    {
        $this->actingAs($this->userA);

        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['name' => 'Manual Entry Asset', 'tag' => 'MAN-100'])],
        ]);

        $response->assertRedirect(route('assets.index'));
        $response->assertSessionHas('ok');

        $asset = Asset::withoutGlobalScopes()->where('tag', 'MAN-100')->first();
        $this->assertNotNull($asset, 'Manual bulk entry must persist the submitted asset.');
        $this->assertEquals('Manual Entry Asset', $asset->name);
        $this->assertEquals($this->propertyA->id, $asset->property_id);
        $this->assertEquals($this->categoryA->id, $asset->category_id);
    }

    public function test_store_inserts_multiple_rows(): void
    {
        $this->actingAs($this->userA);

        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [
                $this->row(['tag' => 'MAN-201', 'name' => 'Row One']),
                $this->row(['tag' => 'MAN-202', 'name' => 'Row Two', 'status' => 'out_of_service']),
                $this->row(['tag' => 'MAN-203', 'name' => 'Row Three', 'status' => 'disposed']),
            ],
        ]);

        $response->assertRedirect(route('assets.index'));
        $count = Asset::withoutGlobalScopes()
            ->whereIn('tag', ['MAN-201', 'MAN-202', 'MAN-203'])
            ->count();
        $this->assertEquals(3, $count, 'All three manual rows should have been inserted.');
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row()],
        ]);

        $response->assertRedirect(route('login'));
        $this->assertEquals(0, Asset::withoutGlobalScopes()->count());
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->userA);

        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [[
                'category_id' => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status' => 'in_service',
            ]],
        ]);

        $response->assertSessionHasErrors(['assets.0.name', 'assets.0.tag']);
        $this->assertEquals(0, Asset::withoutGlobalScopes()->count());
    }

    public function test_store_assigns_correct_property_id_per_tenant(): void
    {
        $this->actingAs($this->userB);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [[
                'tag' => 'TB-500', 'name' => 'Tenant B Asset',
                'category_id' => $this->categoryB->id,
                'department_id' => $this->departmentB->id,
                'status' => 'in_service',
            ]],
        ]);

        $asset = Asset::withoutGlobalScopes()->where('tag', 'TB-500')->first();
        $this->assertNotNull($asset);
        $this->assertEquals($this->propertyB->id, $asset->property_id);
    }

    /**
     * A category belonging to another tenant must not produce an asset (and
     * must not blow up on the FK either) — the row is skipped.
     */
    public function test_store_skips_cross_tenant_category(): void
    {
        $this->actingAs($this->userA);

        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row([
                'tag' => 'XT-001',
                'category_id' => $this->categoryB->id,
                'department_id' => null,
            ])],
        ]);

        $response->assertRedirect(route('assets.index'));
        $this->assertEquals(0, Asset::withoutGlobalScopes()->where('tag', 'XT-001')->count());
    }

    /**
     * Run as an executive user on purpose: a non-executive's department is now
     * coerced to their own before this check is reached, which would prove the
     * lock rather than the cross-tenant drop this test exists for.
     */
    public function test_store_nulls_cross_tenant_department(): void
    {
        $this->departmentA->update(['is_executive_oversight' => true]);
        $this->userA->refresh()->unsetRelation('department');
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row([
                'tag' => 'XT-002',
                'department_id' => $this->departmentB->id,
            ])],
        ]);

        $asset = Asset::withoutGlobalScopes()->where('tag', 'XT-002')->first();
        $this->assertNotNull($asset, 'Row should still be created with the bad department dropped.');
        $this->assertNull($asset->department_id);
    }

    // ══════════════════════════════════════════════════════════════
    // 3. DOUBLE-SUBMIT PROTECTION
    // ══════════════════════════════════════════════════════════════

    /**
     * assets.tag carries no unique constraint and uuid is regenerated per
     * insert, so nothing at the database level stops a duplicate POST. The
     * _form_id token is the guard.
     */
    public function test_duplicate_form_id_submission_inserts_only_once(): void
    {
        $this->actingAs($this->userA);
        $formId = (string) Str::uuid();

        $payload = [
            '_form_id' => $formId,
            'assets' => [$this->row(['tag' => 'DUP-001', 'name' => 'Double Click'])],
        ];

        $first = $this->post(route('assets.bulk-manual.store'), $payload);
        $first->assertRedirect(route('assets.index'));

        $second = $this->post(route('assets.bulk-manual.store'), $payload);
        $second->assertRedirect(route('assets.index'));
        $second->assertSessionHas('warning');

        $this->assertEquals(
            1,
            Asset::withoutGlobalScopes()->where('tag', 'DUP-001')->count(),
            'Re-submitting the same form token must not insert the asset twice.'
        );
    }

    public function test_distinct_form_ids_are_both_accepted(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            '_form_id' => (string) Str::uuid(),
            'assets' => [$this->row(['tag' => 'SEQ-001', 'name' => 'First Batch'])],
        ]);
        $this->post(route('assets.bulk-manual.store'), [
            '_form_id' => (string) Str::uuid(),
            'assets' => [$this->row(['tag' => 'SEQ-002', 'name' => 'Second Batch'])],
        ]);

        $this->assertEquals(2, Asset::withoutGlobalScopes()->whereIn('tag', ['SEQ-001', 'SEQ-002'])->count());
    }

    /**
     * An absent token must not block the insert — keeps the endpoint usable
     * by callers that do not render the form (and by the existing suite).
     */
    public function test_submission_without_form_id_still_works(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['tag' => 'NOTOK-001', 'name' => 'No Token'])],
        ]);

        $this->assertEquals(1, Asset::withoutGlobalScopes()->where('tag', 'NOTOK-001')->count());
    }

    // ══════════════════════════════════════════════════════════════
    // 4. INDEPENDENCE FROM SMART IMPORT
    // ══════════════════════════════════════════════════════════════

    public function test_store_never_touches_the_staging_table(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['tag' => 'IND-001', 'name' => 'Independent'])],
        ]);

        $this->assertEquals(1, Asset::withoutGlobalScopes()->where('tag', 'IND-001')->count());
        $this->assertEquals(
            0,
            DB::table('temporary_asset_imports')->count(),
            'The manual path must never write to the Smart Import staging table.'
        );
    }

    /**
     * A manual save must not behave like storeBatch()'s final chunk, which
     * purges the staging rows for the user+property.
     */
    public function test_store_does_not_purge_an_in_progress_smart_import(): void
    {
        $this->actingAs($this->userA);

        DB::table('temporary_asset_imports')->insert([
            'user_id' => $this->userA->id,
            'property_id' => $this->propertyA->id,
            'tag' => 'STAGED-001',
            'name' => 'Staged Row',
            'category_id' => $this->categoryA->id,
            'department_id' => $this->departmentA->id,
            'status' => 'in_service',
            'is_invalid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['tag' => 'IND-002', 'name' => 'Parallel Manual'])],
        ]);

        $this->assertEquals(1, Asset::withoutGlobalScopes()->where('tag', 'IND-002')->count());
        $this->assertEquals(
            1,
            DB::table('temporary_asset_imports')->where('tag', 'STAGED-001')->count(),
            'An in-progress Smart Import session must survive a manual bulk save.'
        );
    }

    // ══════════════════════════════════════════════════════════════
    // 5. MODEL FIELD (the grid always collected it; nothing stored it)
    // ══════════════════════════════════════════════════════════════

    /**
     * The grid has always rendered a Model/Brand input posting assets[i][model],
     * but store()'s insert array had no 'model' key and assets had no such column,
     * so the value was discarded on every save.
     */
    public function test_model_typed_into_the_grid_is_persisted(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row([
                'tag' => 'MOD-001',
                'name' => 'Projector',
                'model' => 'EB-2250U',
            ])],
        ])->assertRedirect();

        $this->assertSame(
            'EB-2250U',
            Asset::withoutGlobalScopes()->where('tag', 'MOD-001')->firstOrFail()->model,
            'The Model value typed into the grid never reached assets.model.'
        );
    }

    public function test_blank_model_is_stored_as_null_not_empty_string(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['tag' => 'MOD-002', 'name' => 'No Model', 'model' => ''])],
        ])->assertRedirect();

        $this->assertNull(Asset::withoutGlobalScopes()->where('tag', 'MOD-002')->firstOrFail()->model);
    }

    /**
     * remarks used to be the model's hiding place ('Imported. Model: X') because
     * there was nowhere else to put it. That workaround is gone.
     */
    public function test_remarks_no_longer_smuggle_the_model_value(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row([
                'tag' => 'MOD-003',
                'name' => 'Router',
                'model' => 'RB5009UG',
            ])],
        ])->assertRedirect();

        $asset = Asset::withoutGlobalScopes()->where('tag', 'MOD-003')->firstOrFail();

        $this->assertSame('RB5009UG', $asset->model);
        $this->assertSame('Imported.', $asset->remarks);
    }

    /**
     * model, serial_number, purchase_date, purchase_cost and remarks all reach
     * DB::table()->insert() directly. Without a rule an over-long value produced a
     * raw SQL error (500) rather than a validation response.
     */
    public function test_over_long_optional_fields_fail_validation_instead_of_erroring(): void
    {
        $this->actingAs($this->userA);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row([
                'tag' => 'MOD-004',
                'name' => 'Too Long',
                'model' => str_repeat('x', 256),
                'serial_number' => str_repeat('y', 256),
            ])],
        ])->assertSessionHasErrors(['assets.0.model', 'assets.0.serial_number']);

        $this->assertEquals(0, Asset::withoutGlobalScopes()->where('tag', 'MOD-004')->count());
    }

    /**
     * The grid renders a disabled single-option select for a user without
     * executive oversight, but that lock lived only in the view: the insert loop
     * checked department_id against the property and nothing else, so a POST
     * built by hand could file assets under any department in the property.
     *
     * Coerced rather than rejected, matching how this method already handles a
     * value the caller may not use — a cross-tenant FK is quietly dropped here
     * too, because a native form post has nowhere useful to show a field error
     * for a control the user was never given.
     */
    public function test_a_non_executive_post_is_filed_under_the_users_own_department(): void
    {
        $this->actingAs($this->userA);
        $this->assertFalse($this->userA->hasExecutiveOversight());

        $other = Department::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'Housekeeping Alpha',
        ]);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['tag' => 'DEP-001', 'department_id' => $other->id])],
        ])->assertRedirect();

        $asset = Asset::withoutGlobalScopes()->where('tag', 'DEP-001')->firstOrFail();

        $this->assertSame($this->departmentA->id, $asset->department_id);
    }

    public function test_an_executive_post_keeps_the_department_it_names(): void
    {
        $this->departmentA->update(['is_executive_oversight' => true]);
        $this->userA->refresh()->unsetRelation('department');
        $this->actingAs($this->userA);

        $other = Department::factory()->create([
            'property_id' => $this->propertyA->id,
            'name' => 'Housekeeping Alpha',
        ]);

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [$this->row(['tag' => 'DEP-002', 'department_id' => $other->id])],
        ])->assertRedirect();

        $this->assertSame(
            $other->id,
            Asset::withoutGlobalScopes()->where('tag', 'DEP-002')->firstOrFail()->department_id
        );
    }
}
