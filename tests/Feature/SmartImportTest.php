<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportJob;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\AssetImportService;
use App\Services\EntityCodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SmartImportTest extends TestCase
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

        \Illuminate\Support\Facades\Storage::fake('local');

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

    // ══════════════════════════════════════════════════════════════
    // 1. LOCALIZATION (i18n) TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_english_translation_keys_return_real_strings(): void
    {
        app()->setLocale('en');
        $keys = [
            'assets.add_asset_options', 'assets.single_add', 'assets.bulk_add_manual',
            'assets.smart_import', 'assets.upload_prompt', 'assets.scanning_data',
            'assets.large_file_warning', 'assets.review_data', 'assets.import_success',
            'assets.import_parse_error', 'assets.no_data_found',
            'assets.bulk_add_title', 'assets.bulk_add_desc',
            'assets.status_default_hint', 'assets.smart_import_help',
            'assets.smart_import_help_department', 'messages.model',
        ];
        foreach ($keys as $key) {
            $translated = __($key, ['message' => 'test']);
            $this->assertNotEquals($key, $translated, "Translation key [{$key}] missing for 'en'.");
        }
    }

    public function test_indonesian_translation_keys_return_real_strings(): void
    {
        app()->setLocale('id');
        $keys = [
            'assets.add_asset_options', 'assets.single_add', 'assets.bulk_add_manual',
            'assets.smart_import', 'assets.upload_prompt', 'assets.scanning_data',
            'assets.large_file_warning', 'assets.review_data', 'assets.import_success',
            'assets.import_parse_error', 'assets.no_data_found',
            'assets.bulk_add_title', 'assets.bulk_add_desc',
            'assets.status_default_hint', 'assets.smart_import_help',
            'assets.smart_import_help_department', 'messages.model',
        ];
        foreach ($keys as $key) {
            $translated = __($key, ['message' => 'test']);
            $this->assertNotEquals($key, $translated, "Translation key [{$key}] missing for 'id'.");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // 2. NATIVE HEURISTIC PARSER TESTS (AssetImportService)
    // ══════════════════════════════════════════════════════════════

    public function test_parses_csv_with_english_headers(): void
    {
        $csv = "Name,Tag,Serial Number,Status,Brand,Model\n";
        $csv .= "Laptop Dell,AST-001,SN123,In Service,Dell,Latitude 5520\n";
        $csv .= "Monitor LG,AST-002,SN456,Out of Service,LG,27UK850\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_en_');
        file_put_contents($tmpFile, $csv);

        $service = new AssetImportService;
        $result = $service->parseFile($tmpFile, 'csv');

        @unlink($tmpFile);

        $this->assertCount(2, $result);
        $this->assertEquals('Laptop Dell', $result[0]['name']);
        $this->assertEquals('AST-001', $result[0]['tag']);
        $this->assertEquals('SN123', $result[0]['serial_number']);
        $this->assertEquals('in_service', $result[0]['status']);
        $this->assertStringContainsString('Dell', $result[0]['model']);
        $this->assertEquals('out_of_service', $result[1]['status']);
    }

    public function test_parses_csv_with_indonesian_headers(): void
    {
        $csv = "Nama Aset,Kode Aset,No Seri,Kondisi,Merk\n";
        $csv .= "Kursi Kerja,KRS-001,SER001,Aktif,IKEA\n";
        $csv .= "Meja Kantor,MJA-002,,Rusak,\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_id_');
        file_put_contents($tmpFile, $csv);

        $service = new AssetImportService;
        $result = $service->parseFile($tmpFile, 'csv');

        @unlink($tmpFile);

        $this->assertCount(2, $result);
        $this->assertEquals('Kursi Kerja', $result[0]['name']);
        $this->assertEquals('KRS-001', $result[0]['tag']);
        $this->assertEquals('in_service', $result[0]['status']);
        $this->assertEquals('out_of_service', $result[1]['status']);
    }

    public function test_skips_completely_empty_rows(): void
    {
        $csv = "Name,Tag,Serial Number\n";
        $csv .= "Asset One,A-001,SN1\n";
        $csv .= ",,\n";  // Empty row
        $csv .= ",,\n";  // Empty row
        $csv .= "Asset Two,A-002,SN2\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_empty_');
        file_put_contents($tmpFile, $csv);

        $service = new AssetImportService;
        $result = $service->parseFile($tmpFile, 'csv');

        @unlink($tmpFile);

        $this->assertCount(2, $result);
        $this->assertEquals('Asset One', $result[0]['name']);
        $this->assertEquals('Asset Two', $result[1]['name']);
    }

    public function test_keeps_partially_filled_rows(): void
    {
        $csv = "Name,Tag,Serial Number\n";
        $csv .= "Partial Asset,,\n"; // Only name filled

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_partial_');
        file_put_contents($tmpFile, $csv);

        $service = new AssetImportService;
        $result = $service->parseFile($tmpFile, 'csv');

        @unlink($tmpFile);

        $this->assertCount(1, $result);
        $this->assertEquals('Partial Asset', $result[0]['name']);
    }

    public function test_header_not_in_first_row(): void
    {
        $csv = "Some Company Report\n";
        $csv .= "Generated: 2024-01-01\n";
        $csv .= "\n";
        $csv .= "Name,Tag,Serial Number,Status\n";
        $csv .= "Server HP,SRV-001,SN789,Active\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_offset_');
        file_put_contents($tmpFile, $csv);

        $service = new AssetImportService;
        $result = $service->parseFile($tmpFile, 'csv');

        @unlink($tmpFile);

        $this->assertCount(1, $result);
        $this->assertEquals('Server HP', $result[0]['name']);
    }

    public function test_throws_when_no_header_detected(): void
    {
        $csv = "foo,bar,baz\n1,2,3\n4,5,6\n";
        // Pad to > 15 rows to exceed scan limit
        for ($i = 0; $i < 15; $i++) {
            $csv .= "$i,$i,$i\n";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'csv_noheader_');
        file_put_contents($tmpFile, $csv);

        $this->expectException(\Exception::class);

        $service = new AssetImportService;
        $service->parseFile($tmpFile, 'csv');

        @unlink($tmpFile);
    }

    // ══════════════════════════════════════════════════════════════
    // 3. GARBAGE COLLECTION TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_uploaded_file_is_deleted_after_successful_parse(): void
    {
        $csv = "Name,Tag\nAsset GC,GC-001\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'gc_test_');
        file_put_contents($tmpFile, $csv);
        // Note: UploadedFile with test=true copies (not moves) the file, so $tmpFile
        // still exists after storeAs(). Cleanup of /tmp is PHP's responsibility.
        $uploadedFile = new UploadedFile($tmpFile, 'test.csv', 'text/csv', null, true);

        $this->actingAs($this->userA);

        $response = $this->postJson(route('assets.import-parse'), [
            'import_file' => $uploadedFile,
        ]);

        $response->assertStatus(200);

        // The file must have been copied into local storage
        $cacheKey = 'import_state_' . $this->userA->id;
        $state    = Cache::get($cacheKey);
        $this->assertNotNull($state);
        $storedPath = $state['temp_file_path'];
        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('local')->exists($storedPath),
            'File was not stored in local disk after parse.'
        );

        $payload = [
            'temp_file_path' => $storedPath,
            'mapping' => [
                'name' => ['columns' => ['Name'], 'separator' => ' '],
                'tag'  => ['columns' => ['Tag'],  'separator' => ' '],
            ],
        ];

        // R11: The import_state cache is already seeded by parse() above.
        // processMapping() cross-checks temp_file_path against that cache.
        Cache::put($cacheKey, array_merge($state, ['true_header' => ['Name', 'Tag']]), 1800);

        $mappingResponse = $this->post(route('assets.import.process-mapping'), [
            'payload' => json_encode($payload),
        ]);

        $mappingResponse->assertStatus(200);
        $mappingResponse->assertJson(['success' => true]);

        // processMapping dispatches a job, which deletes the file after completion.
        // In test (sync driver), the job runs immediately — assert storage cleanup.
        $this->assertFalse(
            \Illuminate\Support\Facades\Storage::disk('local')->exists($storedPath),
            'Stored file was not cleaned up after processMapping dispatched the job.'
        );

        // Clean up the /tmp file we created
        @unlink($tmpFile);
    }

    public function test_uploaded_file_is_deleted_on_parse_failure(): void
    {
        // Upload a CSV with only empty cells — peek() will throw, triggering the
        // catch block which calls Storage::delete($path).
        $tmpFile = tempnam(sys_get_temp_dir(), 'gc_fail_');
        file_put_contents($tmpFile, "  ,  \n  ,  \n");
        $uploadedFile = new UploadedFile($tmpFile, 'test.csv', 'text/csv', null, true);

        $this->actingAs($this->userA);

        $response = $this->postJson(route('assets.import-parse'), [
            'import_file' => $uploadedFile,
        ]);

        $response->assertStatus(422);

        // The key invariant: no storage path was persisted when parse fails.
        $state = Cache::get('import_state_' . $this->userA->id);
        $this->assertNull($state, 'import_state cache must NOT be populated on parse failure.');

        // Clean up
        @unlink($tmpFile);
    }

    // ══════════════════════════════════════════════════════════════
    // 4. CONTROLLER FLOW TESTS (Parse → Cache → Review → Store)
    // ══════════════════════════════════════════════════════════════

    public function test_parse_returns_json_with_redirect_url(): void
    {
        $csv = "Name,Tag,Serial Number\nTest Asset,TA-001,SN001\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'flow_test_');
        file_put_contents($tmpFile, $csv);
        $uploadedFile = new UploadedFile($tmpFile, 'test.csv', 'text/csv', null, true);

        $this->actingAs($this->userA);

        $response = $this->postJson(route('assets.import-parse'), [
            'import_file' => $uploadedFile,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'redirect_url']);
        $response->assertJson(['success' => true, 'redirect_url' => route('assets.import-mapping')]);

        $cacheKey = 'import_state_'.$this->userA->id;
        $cached = Cache::get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals(['Name', 'Tag', 'Serial Number'], $cached['true_header']);

        @unlink($tmpFile);
    }

    public function test_review_page_loads_with_cached_data(): void
    {
        $this->actingAs($this->userA);

        // R6: review() now reads from staging table, not cache.
        \Illuminate\Support\Facades\DB::table('temporary_asset_imports')->insert([
            [
                'user_id'     => $this->userA->id,
                'property_id' => $this->propertyA->id,
                'tag'         => 'RV-001',
                'name'        => 'Review Asset',
                'category_id' => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status'      => 'in_service',
                'is_invalid'  => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        $response = $this->get(route('assets.import-review'));
        $response->assertStatus(200);
        $response->assertSee('Review Asset');
    }

    /**
     * review() redirects when there are no staging rows for the acting user's
     * property, so any test rendering that page needs at least one row seeded.
     */
    private function seedStagingRowForUserA(): void
    {
        \Illuminate\Support\Facades\DB::table('temporary_asset_imports')->insert([
            'user_id'     => $this->userA->id,
            'property_id' => $this->propertyA->id,
            'tag'         => 'RV-DEL-001',
            'name'        => 'Deletable Row',
            'category_id' => $this->categoryA->id,
            'department_id' => $this->departmentA->id,
            'status'      => 'in_service',
            'is_invalid'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * The delete-row confirmation used to be a browser confirm() with hardcoded
     * Indonesian text, which could not be localized or styled. It must now be a
     * real modal, wired through Alpine, with the browser-native dialog gone.
     */
    public function test_review_page_uses_modal_instead_of_native_confirm_for_row_delete(): void
    {
        $this->actingAs($this->userA);
        $this->seedStagingRowForUserA();

        $response = $this->get(route('assets.import-review'));

        $response->assertStatus(200);
        $response->assertSee('id="delete_row_modal"', false);
        $response->assertSee('requestDeleteSelected', false);
        $response->assertSee('confirmDeleteRow', false);
        $response->assertSee('cancelDeleteRow', false);
        $response->assertDontSee('Apakah Anda yakin ingin menghapus baris data ini?');
        $response->assertDontSee('confirm(\'', false);
    }

    /**
     * The modal's copy must follow the active locale, unlike the old confirm()
     * which was stuck in Indonesian regardless of app()->getLocale().
     */
    public function test_delete_row_modal_text_follows_active_locale(): void
    {
        $this->actingAs($this->userA);
        $this->seedStagingRowForUserA();

        app()->setLocale('en');
        $enResponse = $this->get(route('assets.import-review'));
        $enResponse->assertStatus(200);
        $enResponse->assertSee(__('assets.delete_row_confirm'));

        app()->setLocale('id');
        $idResponse = $this->get(route('assets.import-review'));
        $idResponse->assertStatus(200);
        $idResponse->assertSee(__('assets.delete_row_confirm'));

        // Sanity check that the two locale strings actually differ, so the
        // assertions above are not both trivially matching the same text.
        app()->setLocale('en');
        $enText = __('assets.delete_row_confirm');
        app()->setLocale('id');
        $idText = __('assets.delete_row_confirm');
        $this->assertNotEquals($enText, $idText);
    }

    public function test_review_redirects_when_cache_null(): void
    {
        $this->actingAs($this->userA);
        // R6: redirect happens when staging table has no rows for this user+property
        $response = $this->get(route('assets.import-review'));
        $response->assertRedirect(route('assets.index'));
    }

    public function test_bulk_manual_page_loads(): void
    {
        $this->actingAs($this->userA);
        $response = $this->get(route('assets.bulk-manual'));
        $response->assertStatus(200);
    }

    // ══════════════════════════════════════════════════════════════
    // 5. TENANT ISOLATION TESTS
    // ══════════════════════════════════════════════════════════════

    public function test_bulk_store_assigns_correct_property_id_to_user_a(): void
    {
        $this->actingAs($this->userA);
        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [[
                'name' => 'Tenant A Asset', 'tag' => 'TA-001',
                'category_id' => $this->categoryA->id, 'department_id' => $this->departmentA->id,
                'status' => 'in_service', 'serial_number' => 'SN-TA', 'purchase_date' => '2024-01-01',
            ]],
        ]);
        $response->assertRedirect(route('assets.index'));
        $asset = Asset::withoutGlobalScopes()->where('name', 'Tenant A Asset')->first();
        $this->assertNotNull($asset);
        $this->assertEquals($this->propertyA->id, $asset->property_id);
    }

    public function test_bulk_store_assigns_correct_property_id_to_user_b(): void
    {
        $this->actingAs($this->userB);
        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [[
                'name' => 'Tenant B Asset', 'tag' => 'TB-001',
                'category_id' => $this->categoryB->id, 'department_id' => $this->departmentB->id,
                'status' => 'in_service',
            ]],
        ]);
        $response->assertRedirect(route('assets.index'));
        $asset = Asset::withoutGlobalScopes()->where('name', 'Tenant B Asset')->first();
        $this->assertNotNull($asset);
        $this->assertEquals($this->propertyB->id, $asset->property_id);
    }

    public function test_user_a_cannot_see_user_b_assets(): void
    {
        Asset::withoutGlobalScopes()->create([
            'name' => 'Hidden B Asset', 'tag' => 'HB-001',
            'category_id' => $this->categoryB->id, 'department_id' => $this->departmentB->id,
            'status' => 'in_service', 'property_id' => $this->propertyB->id,
        ]);
        $this->actingAs($this->userA);
        $names = Asset::all()->pluck('name')->toArray();
        $this->assertNotContains('Hidden B Asset', $names, 'Tenant isolation broken!');
    }

    public function test_bulk_store_clears_cache_after_success(): void
    {
        $this->actingAs($this->userA);
        $cacheKey = 'import_review_'.$this->userA->id;
        Cache::put($cacheKey, [['name' => 'temp']], now()->addMinutes(30));

        $this->post(route('assets.bulk-manual.store'), [
            'assets' => [[
                'name' => 'Cache Clear Asset', 'tag' => 'CC-001',
                'category_id' => $this->categoryA->id, 'department_id' => $this->departmentA->id,
                'status' => 'in_service',
            ]],
        ]);

        $this->assertNull(Cache::get($cacheKey), 'Cache not cleared after import.');
    }

    public function test_bulk_store_validates_required_fields(): void
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
    }

    public function test_parse_requires_authentication(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'auth_');
        file_put_contents($tmpFile, 'data');
        $uploadedFile = new UploadedFile($tmpFile, 'test.csv', 'text/csv', null, true);
        $response = $this->post(route('assets.import-parse'), ['import_file' => $uploadedFile]);
        $response->assertRedirect(route('login'));
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [['name' => 'X', 'tag' => 'X', 'category_id' => 1, 'department_id' => 1, 'status' => 'in_service']],
        ]);
        $response->assertRedirect(route('login'));
    }

    public function test_multiple_assets_bulk_insert(): void
    {
        $this->actingAs($this->userA);
        $response = $this->post(route('assets.bulk-manual.store'), [
            'assets' => [
                ['name' => 'Bulk 1', 'tag' => 'BA-001', 'category_id' => $this->categoryA->id, 'department_id' => $this->departmentA->id, 'status' => 'in_service'],
                ['name' => 'Bulk 2', 'tag' => 'BA-002', 'category_id' => $this->categoryA->id, 'department_id' => $this->departmentA->id, 'status' => 'out_of_service'],
                ['name' => 'Bulk 3', 'tag' => 'BA-003', 'category_id' => $this->categoryA->id, 'department_id' => $this->departmentA->id, 'status' => 'disposed'],
            ],
        ]);
        $response->assertRedirect(route('assets.index'));
        $count = Asset::withoutGlobalScopes()
            ->where('property_id', $this->propertyA->id)
            ->whereIn('tag', ['BA-001', 'BA-002', 'BA-003'])
            ->count();
        $this->assertEquals(3, $count, 'Not all 3 bulk assets were inserted.');
    }

    public function test_sheet_selector_re_peeks_file_and_updates_cache(): void
    {
        $this->actingAs($this->userA);

        // Prepare mock cache
        $cacheKey = 'import_state_' . $this->userA->id;
        Cache::put($cacheKey, [
            'temp_file_path' => 'temp/test_import.xlsx',
            'sheets' => ['Sheet1', 'Sheet2'],
            'true_header' => ['Old Header'],
            'preview_data' => [['Old Header' => 'Old Value']],
            'mapping_proposals' => [],
            'current_sheet_index' => 0,
            'selected_sheet' => 0,
        ], 1800);

        // Fake the physical file existence
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('temp/test_import.xlsx', 'dummy binary excel content');

        // Mock the AssetImportService
        $this->mock(AssetImportService::class, function ($mock) {
            // The 4th argument is the manual header-row override; null here means
            // auto-detection, which is what a plain sheet switch must keep using.
            $mock->shouldReceive('peek')
                ->once()
                ->with(\Illuminate\Support\Facades\Storage::disk('local')->path('temp/test_import.xlsx'), 'xlsx', 1, null)
                ->andReturn([
                    'sheets' => ['Sheet1', 'Sheet2'],
                    'true_header' => ['New Header'],
                    'preview_data' => [['New Header' => 'New Value']],
                    'mapping_proposals' => ['tag' => ['New Header']],
                ]);
        });

        // Request with ?sheet=1 to switch sheets
        $response = $this->get(route('assets.import-mapping', ['sheet' => 1]));

        $response->assertStatus(200);

        // Verify that the cache was updated with the fresh sheet data!
        $updatedCached = Cache::get($cacheKey);
        $this->assertNotNull($updatedCached);
        $this->assertEquals(['New Header'], $updatedCached['true_header']);
        $this->assertEquals([['New Header' => 'New Value']], $updatedCached['preview_data']);
        $this->assertEquals(1, $updatedCached['current_sheet_index']);
        $this->assertEquals('1', $updatedCached['selected_sheet']);
    }

    public function test_heatmap_validation_and_pagination_highlighting(): void
    {
        $this->actingAs($this->userA);

        // R6: Seed staging table instead of cache.
        // Index 5 is invalid (missing name), Index 55 has empty category_id.
        $rows = [];
        for ($i = 0; $i < 110; $i++) {
            if ($i === 5) {
                $rows[] = [
                    'user_id'       => $this->userA->id,
                    'property_id'   => $this->propertyA->id,
                    'tag'           => 'TAG-5',
                    'name'          => '',
                    'category_id'   => $this->categoryA->id,
                    'department_id' => $this->departmentA->id,
                    'status'        => 'in_service',
                    'is_invalid'    => true,  // missing name
                    'created_at'    => now(), 'updated_at' => now(),
                ];
            } elseif ($i === 55) {
                $rows[] = [
                    'user_id'       => $this->userA->id,
                    'property_id'   => $this->propertyA->id,
                    'tag'           => 'TAG-55',
                    'name'          => 'Row 55',
                    'category_id'   => null,
                    'department_id' => $this->departmentA->id,
                    'status'        => 'in_service',
                    'is_invalid'    => true,  // missing category
                    'created_at'    => now(), 'updated_at' => now(),
                ];
            } else {
                $rows[] = [
                    'user_id'       => $this->userA->id,
                    'property_id'   => $this->propertyA->id,
                    'tag'           => 'TAG-' . $i,
                    'name'          => 'Valid Row ' . $i,
                    'category_id'   => $this->categoryA->id,
                    'department_id' => $this->departmentA->id,
                    'status'        => 'in_service',
                    'is_invalid'    => false,
                    'created_at'    => now(), 'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            \Illuminate\Support\Facades\DB::table('temporary_asset_imports')->insert($chunk);
        }

        $response = $this->get(route('assets.import-review', ['page' => 1]));
        $response->assertStatus(200);
        $response->assertViewHas('invalidPages', [1, 2]);

        // Check that page 1 has the highlighted invalid row and error styles
        $response->assertSee('border-l-4 border-error');
        $response->assertSee('bg-red-50 dark:bg-red-900/20');

        // Check for the absolute positioned red indicator dots in pagination
        $response->assertSee('animate-ping');
        $response->assertSee('bg-red-500');
    }

    public function test_auto_save_endpoint_updates_cache_and_recalculates_validation(): void
    {
        $this->actingAs($this->userA);

        // R6: Seed staging table with one invalid row (missing name at position 0).
        \Illuminate\Support\Facades\DB::table('temporary_asset_imports')->insert([
            [
                'user_id'       => $this->userA->id,
                'property_id'   => $this->propertyA->id,
                'tag'           => 'TAG-5',
                'name'          => '',
                'category_id'   => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status'        => 'in_service',
                'is_invalid'    => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        $rowId = \DB::table('temporary_asset_imports')->value('id');

        // Submit auto-save edit to fix the name of the row
        $response = $this->postJson(route('assets.import.update-row'), [
            'row_id'     => $rowId,
            'field_name' => 'name',
            'new_value'  => 'Now Valid Name',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'is_invalid'   => false,
            'invalidPages' => [],
            'validCount'   => 1,
            'invalidCount' => 0,
        ]);

        // Assert that the staging table has been updated
        $updatedRow = \Illuminate\Support\Facades\DB::table('temporary_asset_imports')
            ->where('user_id', $this->userA->id)
            ->where('property_id', $this->propertyA->id)
            ->first();
        $this->assertEquals('Now Valid Name', $updatedRow->name);
        $this->assertFalse((bool) $updatedRow->is_invalid);
    }

    public function test_clean_abandoned_imports_command(): void
    {
        // Fake local storage
        \Illuminate\Support\Facades\Storage::fake('local');
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        // Create an old file (>60 minutes) and a new file (<60 minutes)
        $disk->put('temp/old_import.xlsx', 'old content');
        $disk->put('temp/new_import.xlsx', 'new content');

        $oldPath = $disk->path('temp/old_import.xlsx');
        $newPath = $disk->path('temp/new_import.xlsx');

        @mkdir(dirname($oldPath), 0777, true);
        file_put_contents($oldPath, 'old content');
        file_put_contents($newPath, 'new content');

        // Set last modified of old path to 90 minutes ago
        touch($oldPath, time() - (90 * 60));
        // Set last modified of new path to now
        touch($newPath, time());

        // Prepare some expired cache records
        if (config('cache.default') === 'database') {
            $prefix = config('cache.prefix');
            \Illuminate\Support\Facades\DB::table('cache')->insert([
                [
                    'key' => $prefix . 'import_state_9999',
                    'value' => serialize('state_value'),
                    'expiration' => time() - 3600, // Expired 1 hour ago
                ],
                [
                    'key' => $prefix . 'import_state_8888',
                    'value' => serialize('state_value'),
                    'expiration' => time() + 3600, // Active 1 hour from now
                ]
            ]);
        }

        // Run the command
        $this->artisan('app:clean-abandoned-imports')
            ->expectsOutputToContain('Starting clean up of abandoned imports...')
            ->expectsOutputToContain('Cleaned up 1 abandoned temporary import file(s).')
            ->assertExitCode(0);

        // Assert file statuses
        $this->assertFalse($disk->exists('temp/old_import.xlsx'));
        $this->assertTrue($disk->exists('temp/new_import.xlsx'));

        // Assert cache statuses
        if (config('cache.default') === 'database') {
            $prefix = config('cache.prefix');
            $this->assertFalse(\Illuminate\Support\Facades\DB::table('cache')->where('key', $prefix . 'import_state_9999')->exists());
            $this->assertTrue(\Illuminate\Support\Facades\DB::table('cache')->where('key', $prefix . 'import_state_8888')->exists());
            
            // Cleanup database records
            \Illuminate\Support\Facades\DB::table('cache')->where('key', $prefix . 'import_state_8888')->delete();
        }
    }

    public function test_delete_row_endpoint_removes_staging_record_and_recalculates_validation(): void
    {
        $this->actingAs($this->userA);

        // Seed staging table with two rows
        \Illuminate\Support\Facades\DB::table('temporary_asset_imports')->insert([
            [
                'user_id'       => $this->userA->id,
                'property_id'   => $this->propertyA->id,
                'tag'           => 'TAG-A',
                'name'          => 'Asset A',
                'category_id'   => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status'        => 'in_service',
                'is_invalid'    => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'user_id'       => $this->userA->id,
                'property_id'   => $this->propertyA->id,
                'tag'           => 'TAG-B',
                'name'          => '',
                'category_id'   => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status'        => 'in_service',
                'is_invalid'    => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        // Assert they both exist in DB
        $this->assertEquals(2, \DB::table('temporary_asset_imports')->where('user_id', $this->userA->id)->count());

        // Call the delete-rows endpoint to delete the row tagged TAG-A
        $firstRowId = \DB::table('temporary_asset_imports')->where('tag', 'TAG-A')->value('id');
        $response = $this->postJson(route('assets.import.delete-rows'), [
            'row_ids' => [$firstRowId],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'totalCount'   => 1,
            'validCount'   => 0,
            'invalidCount' => 1,
        ]);

        // Assert only the second row remains
        $remaining = \DB::table('temporary_asset_imports')->where('user_id', $this->userA->id)->get();
        $this->assertCount(1, $remaining);
        $this->assertEquals('TAG-B', $remaining[0]->tag);
    }

    // ══════════════════════════════════════════════════════════════
    // 6. IMPORT ATTEMPT LIFECYCLE (abort → residual state on re-upload)
    // ══════════════════════════════════════════════════════════════

    /**
     * Write a CSV onto the faked local disk using the exact filename shape
     * processMapping() will accept (temp/import_<hex>.<ext>).
     *
     * @param  array<string, string>  $rows  tag => name
     */
    private function seedImportFile(string $hexId, array $rows): string
    {
        $csv = "Tag,Name\n";
        foreach ($rows as $tag => $name) {
            $csv .= $tag . ',' . $name . "\n";
        }

        $path = 'temp/import_' . $hexId . '.csv';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $csv);

        return $path;
    }

    /** The cache entry parse() leaves behind for a given temp file. */
    private function seedImportState(string $tempFilePath): void
    {
        Cache::put('import_state_' . $this->userA->id, [
            'temp_file_path'      => $tempFilePath,
            'sheets'              => ['Sheet1'],
            'true_header'         => ['Tag', 'Name'],
            'preview_data'        => [],
            'mapping_proposals'   => [],
            'current_sheet_index' => 0,
        ], 1800);
    }

    /** The mapping payload the frontend posts to processMapping(). */
    private function mappingPayload(string $tempFilePath): array
    {
        return [
            'temp_file_path' => $tempFilePath,
            'selected_sheet' => 0,
            'mapping'        => [
                'tag'  => ['columns' => ['Tag'], 'separator' => ' '],
                'name' => ['columns' => ['Name'], 'separator' => ' '],
            ],
        ];
    }

    /**
     * Reproduce the exact sequence from the bug report.
     *
     * Attempt A is dispatched but not yet picked up by a worker (or is sitting
     * between two chunk boundaries — indistinguishable from the job's point of
     * view). The user aborts it, then immediately uploads a different file and
     * starts attempt B, which runs inline here because QUEUE_CONNECTION=sync.
     *
     * Returns attempt A's job object, still unexecuted, so each test can decide
     * what to assert once that zombie finally runs.
     */
    private function abortAttemptAThenStartAttemptB(): ProcessImportJob
    {
        $progressKey = 'import_progress_' . $this->userA->id;

        // ── Attempt A: three rows ─────────────────────────────────────
        $fileA = $this->seedImportFile('aaa111', [
            'A-1' => 'Alpha One',
            'A-2' => 'Alpha Two',
            'A-3' => 'Alpha Three',
        ]);
        $this->seedImportState($fileA);
        Cache::put($progressKey, [
            'status'     => 'processing',
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
            'import_id'  => 'attempt-a',
        ], 600);

        $zombie = new ProcessImportJob(
            $this->userA->id,
            $fileA,
            $this->mappingPayload($fileA),
            0,
            'attempt-a',
        );

        // ── User aborts attempt A ─────────────────────────────────────
        $this->postJson(route('assets.import.cancel'))->assertStatus(200);

        // ── User starts attempt B (two rows), which completes inline ──
        $fileB = $this->seedImportFile('bbb222', [
            'B-1' => 'Bravo One',
            'B-2' => 'Bravo Two',
        ]);
        $this->seedImportState($fileB);
        $this->postJson(route('assets.import.process-mapping'), [
            'payload' => json_encode($this->mappingPayload($fileB)),
        ])->assertStatus(200);

        return $zombie;
    }

    public function test_superseded_import_job_cannot_clobber_a_newer_attempts_progress(): void
    {
        $this->actingAs($this->userA);

        $zombie = $this->abortAttemptAThenStartAttemptB();

        // The worker finally gets to the aborted attempt.
        $zombie->handle();

        $progress = Cache::get('import_progress_' . $this->userA->id);

        $this->assertSame(
            2,
            $progress['total'],
            'Superseded attempt A overwrote the progress bar with its own row count (3) — this is the reported symptom.'
        );
        $this->assertSame(2, $progress['processed']);
    }

    public function test_superseded_import_job_does_not_delete_a_newer_attempts_staging_rows(): void
    {
        $this->actingAs($this->userA);

        $zombie = $this->abortAttemptAThenStartAttemptB();

        $this->assertSame(2, \DB::table('temporary_asset_imports')->where('user_id', $this->userA->id)->count());

        $zombie->handle();

        $names = \DB::table('temporary_asset_imports')
            ->where('user_id', $this->userA->id)
            ->orderBy('id')
            ->pluck('name')
            ->toArray();

        $this->assertSame(
            ['Bravo One', 'Bravo Two'],
            $names,
            'Superseded attempt A wiped attempt B staging rows via its start-of-run DELETE and re-inserted its own.'
        );
    }

    public function test_superseded_import_job_does_not_delete_a_temp_file_the_live_attempt_is_reading(): void
    {
        $this->actingAs($this->userA);

        $progressKey = 'import_progress_' . $this->userA->id;

        // Cancel → reload → re-submit the same mapping page reuses temp_file_path,
        // so the abandoned attempt and the live one can point at the same file.
        $sharedFile = $this->seedImportFile('aaa111', [
            'A-1' => 'Alpha One',
            'A-2' => 'Alpha Two',
            'A-3' => 'Alpha Three',
        ]);
        $this->seedImportState($sharedFile);

        $zombie = new ProcessImportJob(
            $this->userA->id,
            $sharedFile,
            $this->mappingPayload($sharedFile),
            0,
            'attempt-a',
        );

        // Attempt B was dispatched against that same file and is mid-stream —
        // the state a real queue worker would find while it wakes up job A.
        Cache::put($progressKey, [
            'status'     => 'processing',
            'percentage' => 10,
            'processed'  => 100,
            'total'      => 1000,
            'error'      => '',
            'import_id'  => 'attempt-b',
        ], 600);

        $zombie->handle();

        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('local')->exists($sharedFile),
            'Superseded attempt A deleted the temp file that the live attempt B is still reading from.'
        );

        $progress = Cache::get($progressKey);
        $this->assertSame('attempt-b', $progress['import_id']);
        $this->assertSame(1000, $progress['total'], 'Superseded attempt A overwrote the in-flight attempt B progress record.');
    }

    public function test_cancel_within_the_same_attempt_still_stops_the_job(): void
    {
        $this->actingAs($this->userA);

        $progressKey = 'import_progress_' . $this->userA->id;

        // Under 500 rows, so the job never reaches a chunk boundary.
        $file = $this->seedImportFile('ccc333', [
            'C-1' => 'Charlie One',
            'C-2' => 'Charlie Two',
            'C-3' => 'Charlie Three',
        ]);
        $this->seedImportState($file);
        Cache::put($progressKey, [
            'status'     => 'processing',
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
            'import_id'  => 'attempt-c',
        ], 600);

        $job = new ProcessImportJob(
            $this->userA->id,
            $file,
            $this->mappingPayload($file),
            0,
            'attempt-c',
        );

        $this->postJson(route('assets.import.cancel'))->assertStatus(200);

        $job->handle();

        $this->assertSame(
            0,
            \DB::table('temporary_asset_imports')->where('user_id', $this->userA->id)->count(),
            'A cancelled job still imported its rows — the cancellation check only runs at 500-row chunk boundaries.'
        );
        $this->assertSame('cancelled', Cache::get($progressKey)['status']);
        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('local')->exists($file),
            'A cancelled attempt must keep its temp file so the user can retry from the mapping page.'
        );
    }

    public function test_cancel_records_a_full_progress_shape(): void
    {
        $this->actingAs($this->userA);

        Cache::put('import_progress_' . $this->userA->id, [
            'status'     => 'processing',
            'percentage' => 42,
            'processed'  => 420,
            'total'      => 1000,
            'error'      => '',
            'import_id'  => 'attempt-d',
        ], 600);

        $this->postJson(route('assets.import.cancel'))->assertStatus(200);

        $this->getJson(route('assets.import-status'))
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'percentage', 'processed', 'total', 'error'])
            ->assertJson([
                'status'     => 'cancelled',
                'percentage' => 42,
                'processed'  => 420,
                'total'      => 1000,
            ]);
    }

    public function test_invalid_pages_are_computed_at_page_boundaries(): void
    {
        $this->actingAs($this->userA);

        // 101 rows, 50 per page. Invalid at 0-based positions 0, 49, 50 and 100
        // → pages 1, 1, 2 and 3. Pins the boundary arithmetic itself.
        $invalidPositions = [0, 49, 50, 100];
        $rows = [];
        for ($i = 0; $i <= 100; $i++) {
            $isInvalid = in_array($i, $invalidPositions, true);
            $rows[] = [
                'user_id'       => $this->userA->id,
                'property_id'   => $this->propertyA->id,
                'tag'           => 'TAG-' . $i,
                'name'          => $isInvalid ? '' : 'Row ' . $i,
                'category_id'   => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status'        => 'in_service',
                'is_invalid'    => $isInvalid,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            \DB::table('temporary_asset_imports')->insert($chunk);
        }

        $this->get(route('assets.import-review', ['page' => 1]))
            ->assertStatus(200)
            ->assertViewHas('invalidPages', [1, 2, 3]);
    }

    // ══════════════════════════════════════════════════════════════
    // 7. MODEL FIELD PERSISTENCE (upload → mapping → staging → assets)
    // ══════════════════════════════════════════════════════════════

    /**
     * A four-column CSV whose Category values match an existing category, so
     * rapidAdd() can resolve the hint and storeBatch() will not skip the rows.
     *
     * @param  list<array{0:string,1:string,2:string,3:string}>  $rows  [tag, name, category, model]
     */
    private function seedImportFileWithModel(string $hexId, array $rows): string
    {
        $csv = "Tag,Name,Category,Model\n";
        foreach ($rows as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        $path = 'temp/import_' . $hexId . '.csv';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $csv);

        Cache::put('import_state_' . $this->userA->id, [
            'temp_file_path'      => $path,
            'sheets'              => ['Sheet1'],
            'true_header'         => ['Tag', 'Name', 'Category', 'Model'],
            'preview_data'        => [],
            'mapping_proposals'   => [],
            'current_sheet_index' => 0,
        ], 1800);

        return $path;
    }

    /** Mapping payload covering all four columns of seedImportFileWithModel(). */
    private function mappingPayloadWithModel(string $tempFilePath): array
    {
        return [
            'temp_file_path' => $tempFilePath,
            'selected_sheet' => 0,
            'mapping'        => [
                'tag'      => ['columns' => ['Tag'], 'separator' => ' '],
                'name'     => ['columns' => ['Name'], 'separator' => ' '],
                'category' => ['columns' => ['Category'], 'separator' => ' '],
                'model'    => ['columns' => ['Model'], 'separator' => ' '],
            ],
        ];
    }

    /**
     * The headline regression test for the reported bug.
     *
     * Model was collected by the mapping UI, streamed into temporary_asset_imports
     * correctly, and then silently dropped at the final INSERT INTO assets because
     * storeBatch()'s insert array had no 'model' key (and the assets table had no
     * such column). Every stage below is exercised through the real HTTP endpoints;
     * the job runs inline because QUEUE_CONNECTION=sync in the test env.
     */
    public function test_model_survives_the_full_smart_import_pipeline(): void
    {
        $this->actingAs($this->userA);

        $file = $this->seedImportFileWithModel('cafe01', [
            ['M-1', 'Laptop One', 'Electronics A', 'Latitude 5540'],
            ['M-2', 'Laptop Two', 'Electronics A', 'ThinkPad T14'],
        ]);

        // Upload → mapping → dispatch (job runs inline under the sync queue).
        $this->postJson(route('assets.import.process-mapping'), [
            'payload' => json_encode($this->mappingPayloadWithModel($file)),
        ])->assertStatus(200);

        // Staging already handled model correctly before this fix — assert it
        // so a future regression here is distinguishable from one in storeBatch.
        $staged = \DB::table('temporary_asset_imports')
            ->where('user_id', $this->userA->id)
            ->orderBy('id')
            ->pluck('model')
            ->toArray();
        $this->assertSame(['Latitude 5540', 'ThinkPad T14'], $staged);

        // Resolve the category hint into a real FK, then commit.
        $this->get(route('assets.import-rapid-add'))
            ->assertRedirect(route('assets.import-review'));

        $this->postJson(route('assets.import-store-batch'), ['offset' => 0, 'limit' => 500])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $assets = Asset::where('property_id', $this->propertyA->id)->orderBy('tag')->get();

        $this->assertCount(2, $assets);
        $this->assertSame(
            ['Latitude 5540', 'ThinkPad T14'],
            $assets->pluck('model')->toArray(),
            'The mapped Model value never reached assets.model — this is the reported bug.'
        );
    }

    /**
     * remarks used to double as the model's hiding place ('Imported. Model: X').
     * Now that model has a real column, remarks must stay clean.
     */
    public function test_import_remarks_no_longer_smuggle_the_model_value(): void
    {
        $this->actingAs($this->userA);

        $file = $this->seedImportFileWithModel('cafe02', [
            ['M-3', 'Printer', 'Electronics A', 'LaserJet 4000'],
        ]);

        $this->postJson(route('assets.import.process-mapping'), [
            'payload' => json_encode($this->mappingPayloadWithModel($file)),
        ])->assertStatus(200);

        $this->get(route('assets.import-rapid-add'));
        $this->postJson(route('assets.import-store-batch'), ['offset' => 0, 'limit' => 500])
            ->assertStatus(200);

        $asset = Asset::where('tag', 'M-3')->firstOrFail();

        $this->assertSame('LaserJet 4000', $asset->model);
        $this->assertSame('Imported.', $asset->remarks);
        $this->assertStringNotContainsString('Model:', (string) $asset->remarks);
    }

    /**
     * updateSingleRow() already whitelisted 'model' as an editable staging column,
     * but the edit was worthless while storeBatch() discarded the field.
     */
    public function test_model_edited_on_the_review_page_reaches_the_created_asset(): void
    {
        $this->actingAs($this->userA);

        \DB::table('temporary_asset_imports')->insert([
            'user_id'       => $this->userA->id,
            'property_id'   => $this->propertyA->id,
            'tag'           => 'EDIT-1',
            'name'          => 'Monitor',
            'category_id'   => $this->categoryA->id,
            'department_id' => $this->departmentA->id,
            'status'        => 'in_service',
            'model'         => 'wrong model',
            'is_invalid'    => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->postJson(route('assets.import.update-row'), [
            'row_id'     => \DB::table('temporary_asset_imports')->value('id'),
            'field_name' => 'model',
            'new_value'  => 'UltraSharp U2723QE',
        ])->assertStatus(200);

        $this->postJson(route('assets.import-store-batch'), ['offset' => 0, 'limit' => 500])
            ->assertStatus(200);

        $this->assertSame(
            'UltraSharp U2723QE',
            Asset::where('tag', 'EDIT-1')->firstOrFail()->model
        );
    }

    public function test_status_column_header_is_not_marked_required_on_the_review_page(): void
    {
        $this->actingAs($this->userA);

        \DB::table('temporary_asset_imports')->insert([
            'user_id'     => $this->userA->id,
            'property_id' => $this->propertyA->id,
            'tag'         => 'ST-1',
            'name'        => 'Desk',
            'category_id' => $this->categoryA->id,
            'status'      => 'in_service',
            'is_invalid'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Status defaults to in_service and is enforced nowhere, so the review
        // grid must not advertise it as required.
        $this->get(route('assets.import-review'))
            ->assertStatus(200)
            ->assertDontSee(__('assets.status') . ' *', false);
    }

    /**
     * The upload-modal tooltip recommends a Department column only to users whose
     * Department column would actually be honoured. ProcessImportJob discards the
     * file's department outright for anyone without executive oversight
     * (`'_department_hint' => !$isExecutive ? '' : ...`), so recommending it to a
     * department-locked user would be misleading, not merely redundant.
     */
    public function test_upload_tooltip_mentions_department_only_for_executive_oversight_users(): void
    {
        $this->departmentA->update(['is_executive_oversight' => true]);
        $this->actingAs($this->userA->fresh());

        $this->get(route('assets.index'))
            ->assertStatus(200)
            ->assertSee(__('assets.smart_import_help'), false)
            ->assertSee(__('assets.smart_import_help_department'), false);
    }

    public function test_upload_tooltip_omits_department_for_a_department_locked_user(): void
    {
        $this->departmentA->update(['is_executive_oversight' => false]);
        $this->actingAs($this->userA->fresh());

        $this->get(route('assets.index'))
            ->assertStatus(200)
            ->assertSee(__('assets.smart_import_help'), false)
            ->assertDontSee(__('assets.smart_import_help_department'), false);
    }

    public function test_upload_tooltip_follows_the_active_locale(): void
    {
        $this->departmentA->update(['is_executive_oversight' => true]);
        $this->actingAs($this->userA->fresh());

        app()->setLocale('en');
        $enText = __('assets.smart_import_help');
        $this->get(route('assets.index'))->assertSee($enText, false);

        app()->setLocale('id');
        $idText = __('assets.smart_import_help');
        $this->get(route('assets.index'))->assertSee($idText, false);

        $this->assertNotEquals($enText, $idText, 'The tooltip must be genuinely translated, not hardcoded.');
    }

    // ══════════════════════════════════════════════════════════════
    // 8. PARSE FAILURE RECOVERY (keep the file, name the cause)
    // ══════════════════════════════════════════════════════════════

    /** Seed the progress record the way processMapping() does, for a given attempt. */
    private function seedProgress(string $attemptId, string $status = 'processing'): void
    {
        Cache::put('import_progress_' . $this->userA->id, [
            'status'     => $status,
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
            'import_id'  => $attemptId,
        ], 600);
    }

    /**
     * A file the reader cannot open at all: an .xlsx extension over bytes that
     * are not a ZIP container. This is the single most common real failure —
     * a .csv renamed to .xlsx, or a truncated upload.
     */
    public function test_failed_import_keeps_the_temp_file_for_retry(): void
    {
        $this->actingAs($this->userA);

        $path = 'temp/import_dead01.xlsx';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'this is definitely not a zip container');
        $this->seedImportState($path);
        $this->seedProgress('attempt-fail');

        (new ProcessImportJob(
            $this->userA->id,
            $path,
            $this->mappingPayload($path),
            0,
            'attempt-fail',
        ))->handle();

        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('local')->exists($path),
            'A failed parse deleted the temp file, forcing the user to upload it again.'
        );

        $progress = Cache::get('import_progress_' . $this->userA->id);
        $this->assertSame('failed', $progress['status']);
        $this->assertSame('import_error_unreadable', $progress['error_code'] ?? null);
    }

    /**
     * The end-to-end promise of this change: a failure the user can recover from
     * without re-uploading.
     *
     * The failure here is deliberately *transient* rather than a bad file — the
     * import_state cache lost its property_id, which is what happens when a
     * super-admin's session property goes away mid-import. The file is fine; only
     * the surrounding state was broken, so a retry against the very same path
     * must work once that state is repaired.
     */
    public function test_retry_after_failure_succeeds_with_the_same_file(): void
    {
        $superAdmin = User::factory()->create([
            'property_id'    => null,
            'role_id'        => null,
            'department_id'  => null,
            'is_super_admin' => true,
        ]);
        $this->actingAs($superAdmin);

        $path = 'temp/import_beef01.csv';
        \Illuminate\Support\Facades\Storage::disk('local')->put(
            $path,
            "Tag,Name\nR-1,Retry One\nR-2,Retry Two\n"
        );

        $stateKey    = 'import_state_' . $superAdmin->id;
        $progressKey = 'import_progress_' . $superAdmin->id;
        $baseState   = [
            'temp_file_path'      => $path,
            'sheets'              => ['Sheet1'],
            'true_header'         => ['Tag', 'Name'],
            'preview_data'        => [],
            'mapping_proposals'   => [],
            'current_sheet_index' => 0,
        ];

        // ── Attempt 1: no property_id anywhere → the job cannot resolve a tenant ──
        Cache::put($stateKey, $baseState, 1800);
        Cache::put($progressKey, [
            'status' => 'processing', 'percentage' => 0, 'processed' => 0,
            'total' => 0, 'error' => '', 'import_id' => 'attempt-1',
        ], 600);

        (new ProcessImportJob($superAdmin->id, $path, $this->mappingPayload($path), 0, 'attempt-1'))->handle();

        $failed = Cache::get($progressKey);
        $this->assertSame('failed', $failed['status']);
        $this->assertSame('import_error_no_property', $failed['error_code'] ?? null);
        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('local')->exists($path),
            'The file must survive the failure — it is the whole point of the retry.'
        );
        $this->assertSame(0, \DB::table('temporary_asset_imports')->count());

        // ── Retry: repair the state exactly as processMapping() would, same file ──
        Cache::put($stateKey, $baseState + ['property_id' => $this->propertyA->id], 1800);
        Cache::put($progressKey, [
            'status' => 'processing', 'percentage' => 0, 'processed' => 0,
            'total' => 0, 'error' => '', 'import_id' => 'attempt-2',
        ], 600);

        (new ProcessImportJob($superAdmin->id, $path, $this->mappingPayload($path), 0, 'attempt-2'))->handle();

        $this->assertSame('completed', Cache::get($progressKey)['status']);
        $this->assertSame(
            ['Retry One', 'Retry Two'],
            \DB::table('temporary_asset_imports')
                ->where('user_id', $superAdmin->id)
                ->orderBy('id')
                ->pluck('name')
                ->toArray(),
            'The retry did not ingest the already-uploaded file.'
        );
    }

    /**
     * The job runs in a queue worker with no HTTP session, so __() inside it
     * resolves against the default locale, not the user's. It therefore stores a
     * locale-independent code and status() — which does run in the user's request
     * — does the translating.
     */
    public function test_status_endpoint_localizes_the_failure_message(): void
    {
        $this->actingAs($this->userA);

        Cache::put('import_progress_' . $this->userA->id, [
            'status'     => 'failed',
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
            'error_code' => 'import_error_unreadable',
            'import_id'  => 'attempt-x',
        ], 600);

        app()->setLocale('en');
        $en = $this->getJson(route('assets.import-status'))->assertStatus(200)->json();

        app()->setLocale('id');
        $id = $this->getJson(route('assets.import-status'))->assertStatus(200)->json();

        $this->assertSame(__('assets.import_error_unreadable', [], 'en'), $en['error']);
        $this->assertSame(__('assets.import_error_unreadable', [], 'id'), $id['error']);
        $this->assertNotEquals($en['error'], $id['error'], 'The failure message must be genuinely translated.');

        // The guidance block the modal renders under the message.
        $this->assertNotEmpty($en['error_hint']);
        $this->assertNotEquals($en['error_hint'], $id['error_hint']);

        // Internal bookkeeping must not leak to the browser.
        $this->assertArrayNotHasKey('error_code', $en);
        $this->assertArrayNotHasKey('import_id', $en);
    }

    /**
     * A file whose rows never match the expected header used to "succeed" with
     * zero rows — an empty review page with no explanation.
     */
    public function test_missing_header_reports_a_distinct_error_code(): void
    {
        $this->actingAs($this->userA);

        $path = 'temp/import_nohdr1.csv';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, "Foo\nBar\nBaz\n");
        $this->seedImportState($path);
        $this->seedProgress('attempt-nohdr');

        (new ProcessImportJob(
            $this->userA->id,
            $path,
            $this->mappingPayload($path),
            0,
            'attempt-nohdr',
        ))->handle();

        $progress = Cache::get('import_progress_' . $this->userA->id);
        $this->assertSame('failed', $progress['status']);
        $this->assertSame('import_error_no_header', $progress['error_code'] ?? null);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($path));
    }

    /**
     * The raw exception text must never reach the browser — it leaks absolute
     * server paths and library internals, and is untranslatable.
     */
    public function test_failure_message_is_not_a_raw_exception_string(): void
    {
        $this->actingAs($this->userA);

        $path = 'temp/import_dead02.xlsx';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'not a zip');
        $this->seedImportState($path);
        $this->seedProgress('attempt-raw');

        (new ProcessImportJob(
            $this->userA->id,
            $path,
            $this->mappingPayload($path),
            0,
            'attempt-raw',
        ))->handle();

        $error = $this->getJson(route('assets.import-status'))->json('error');

        $this->assertNotEmpty($error);
        $this->assertStringNotContainsString('/', $error, 'A filesystem path leaked into the user-facing error.');
        $this->assertStringNotContainsString('Exception', $error);
        $this->assertStringNotContainsString('OpenSpout', $error);
    }

    // ══════════════════════════════════════════════════════════════
    // 9. MANUAL HEADER ROW SELECTION
    // ══════════════════════════════════════════════════════════════

    /**
     * A file whose real table starts at row 3, under a two-row preamble. Both
     * preamble rows are booby-trapped, each defeating a *different* detector:
     *
     *  - Row 1 is WIDER than the real header (5 filled cells vs 3), so
     *    peek()'s "most non-empty cells" heuristic picks it.
     *  - Row 2 is a legend naming two of the real columns, so it intersects the
     *    expected header on 2 cells — which is exactly ProcessImportJob's
     *    content-matching threshold. The job latches onto it and treats the real
     *    header row as a data row.
     *
     * Without both traps a manual override looks like it works while the job
     * quietly re-derives the right answer on its own, and the test proves nothing.
     */
    private function seedBannerFile(string $hexId): string
    {
        $csv = "ASSET REGISTER,FY2026,CONFIDENTIAL,PAGE 1,DRAFT\n"
             . "Columns used:,Tag,Name\n"
             . "Tag,Name,Category\n"
             . "B-1,Banner One,Electronics A\n"
             . "B-2,Banner Two,Electronics A\n";

        $path = 'temp/import_' . $hexId . '.csv';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $csv);

        Cache::put('import_state_' . $this->userA->id, [
            'temp_file_path'      => $path,
            'sheets'              => ['Sheet1'],
            'true_header'         => ['ASSET REGISTER', 'FY2026', 'CONFIDENTIAL', 'PAGE 1', 'DRAFT'],
            'preview_data'        => [],
            'mapping_proposals'   => [],
            'current_sheet_index' => 0,
            'header_row_choice'   => 'auto',
            'header_row_index'    => null,
        ], 1800);

        return $path;
    }

    public function test_auto_header_detection_is_unchanged_without_the_parameter(): void
    {
        $this->actingAs($this->userA);
        $this->seedBannerFile('ba0001');

        $this->get(route('assets.import-mapping'))->assertStatus(200);

        $state = Cache::get('import_state_' . $this->userA->id);

        // Untouched: no re-peek runs, and auto remains in effect.
        $this->assertNull($state['header_row_index']);
        $this->assertSame('auto', $state['header_row_choice']);
    }

    public function test_manual_header_row_overrides_the_heuristic(): void
    {
        $this->actingAs($this->userA);
        $this->seedBannerFile('ba0002');

        // Row 3 (1-based) is the real header.
        $this->get(route('assets.import-mapping', ['header_row' => 3]))->assertStatus(200);

        $state = Cache::get('import_state_' . $this->userA->id);

        $this->assertSame(['Tag', 'Name', 'Category'], $state['true_header']);
        $this->assertSame(2, $state['header_row_index'], 'Stored index must be 0-based.');
        $this->assertSame('3', $state['header_row_choice'], 'The raw 1-based choice repopulates the select.');

        // Data starts on the row after the header — the same +1 offset auto uses.
        $this->assertSame(
            [
                ['Tag' => 'B-1', 'Name' => 'Banner One', 'Category' => 'Electronics A'],
                ['Tag' => 'B-2', 'Name' => 'Banner Two', 'Category' => 'Electronics A'],
            ],
            $state['preview_data']
        );

        // And the heuristic really would have got it wrong left alone.
        $this->assertNotContains('Tag', ['ASSET REGISTER', 'FY2026', 'CONFIDENTIAL', 'PAGE 1', 'DRAFT']);
    }

    /**
     * The header index has to reach ProcessImportJob, not just the preview. The
     * job re-finds the header by content, so without the explicit index it would
     * ingest the banner rows as data.
     */
    public function test_manual_header_row_shifts_the_data_offset_in_the_job(): void
    {
        $this->actingAs($this->userA);
        $path = $this->seedBannerFile('ba0003');

        $this->get(route('assets.import-mapping', ['header_row' => 3]))->assertStatus(200);

        $this->postJson(route('assets.import.process-mapping'), [
            'payload' => json_encode([
                'temp_file_path' => $path,
                'selected_sheet' => 0,
                'mapping'        => [
                    'tag'      => ['columns' => ['Tag'], 'separator' => ' '],
                    'name'     => ['columns' => ['Name'], 'separator' => ' '],
                    'category' => ['columns' => ['Category'], 'separator' => ' '],
                ],
            ]),
        ])->assertStatus(200);

        $names = \DB::table('temporary_asset_imports')
            ->where('user_id', $this->userA->id)
            ->orderBy('id')
            ->pluck('name')
            ->toArray();

        $this->assertSame(['Banner One', 'Banner Two'], $names);
        $this->assertNotContains(
            'Name',
            $names,
            'The real header row was ingested as data — the job fell back to content matching '
            . 'and latched onto the legend row above it.'
        );

        // The progress total must count against the same offset, or the bar lies.
        $this->assertSame(2, Cache::get('import_progress_' . $this->userA->id)['total']);
    }

    public function test_invalid_header_row_selection_warns_instead_of_silently_doing_nothing(): void
    {
        $this->actingAs($this->userA);
        $this->seedBannerFile('ba0004');

        // Row 12 exists in neither this 5-row file nor the peek sample.
        $this->get(route('assets.import-mapping', ['header_row' => 12]))
            ->assertStatus(200)
            ->assertSee(__('assets.header_row_invalid', ['row' => 12]), false);

        // Auto-detection stays in force rather than leaving a broken selection.
        $this->assertNull(Cache::get('import_state_' . $this->userA->id)['header_row_index']);
    }

    public function test_single_sheet_file_hides_the_sheet_selector_but_keeps_header_row(): void
    {
        $this->actingAs($this->userA);
        $this->seedBannerFile('ba0005');

        $html = $this->get(route('assets.import-mapping'))->assertStatus(200)->getContent();

        // The sheet <select> is behind x-if="hasMultipleSheets"; the header row one
        // is unconditional, so only the latter may be present for a 1-sheet file.
        $this->assertStringContainsString('id="headerRowSelector"', $html);
        $this->assertStringContainsString('hasMultipleSheets', $html);
        $this->assertStringContainsString(__('assets.header_row_auto'), $html);
        $this->assertStringContainsString(__('assets.header_row_option', ['number' => 15]), $html);
    }

    public function test_header_row_labels_follow_the_active_locale(): void
    {
        $this->actingAs($this->userA);
        $this->seedBannerFile('ba0006');

        app()->setLocale('en');
        $en = __('assets.header_row_auto');
        $this->get(route('assets.import-mapping'))->assertSee($en, false);

        app()->setLocale('id');
        $id = __('assets.header_row_auto');
        $this->get(route('assets.import-mapping'))->assertSee($id, false);

        $this->assertNotEquals($en, $id, 'The header row control must be genuinely translated.');
    }

    /**
     * The failure UI is only useful if it actually renders: the guidance block and
     * the retry affordance both have to reach the page, in the active locale.
     */
    public function test_failure_ui_renders_retry_and_guidance_in_the_active_locale(): void
    {
        $this->actingAs($this->userA);
        $this->seedBannerFile('ba0007');

        app()->setLocale('en');
        $this->get(route('assets.import-mapping'))
            ->assertStatus(200)
            ->assertSee(__('assets.retry_import'), false)
            ->assertSee(__('assets.possible_solutions'), false)
            ->assertSee(__('assets.import_timed_out'), false);

        app()->setLocale('id');
        $this->get(route('assets.import-mapping'))
            ->assertStatus(200)
            ->assertSee(__('assets.retry_import'), false)
            ->assertSee(__('assets.possible_solutions'), false);

        $this->assertNotEquals(
            __('assets.possible_solutions', [], 'en'),
            __('assets.possible_solutions', [], 'id')
        );
    }

    // ══════════════════════════════════════════════════════════════
    // 10. STAGING ROW IDENTITY (edit/delete target by ID, not position)
    // ══════════════════════════════════════════════════════════════

    /**
     * Seed three staging rows for userA and return their real IDs keyed by letter.
     */
    private function seedThreeStagingRows(): array
    {
        $ids = [];

        foreach (['A', 'B', 'C'] as $letter) {
            $ids[$letter] = \DB::table('temporary_asset_imports')->insertGetId([
                'user_id' => $this->userA->id,
                'property_id' => $this->propertyA->id,
                'tag' => 'TAG-'.$letter,
                'name' => 'Row '.$letter,
                'category_id' => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status' => 'in_service',
                'is_invalid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /**
     * The race this replaces: both endpoints used to resolve a row with
     * ->orderBy('id')->skip($absoluteIndex)->first(), i.e. by its position on the
     * rendered page. A delete shifts every later row down one slot, so an edit
     * still carrying its pre-delete index lands on the row that moved into that
     * slot. Auto-save fires on a 500ms debounce and never reloads, so the window
     * is wide and the wrong write is completely silent.
     *
     * Verified against the pre-fix controller: deleting A and then editing "index
     * 1" produced {"TAG-B":"Row B","TAG-C":"Edited B"} — C took B's edit.
     */
    public function test_row_operations_target_rows_by_id_after_a_delete_shifts_positions(): void
    {
        $this->actingAs($this->userA);
        $ids = $this->seedThreeStagingRows();

        // The page rendered A, B, C at positions 0, 1, 2. Delete A.
        $this->postJson(route('assets.import.delete-rows'), ['row_ids' => [$ids['A']]])
            ->assertStatus(200)
            ->assertJson(['success' => true, 'totalCount' => 2]);

        // Position 1 now resolves to C. Edit B by ID anyway.
        $this->postJson(route('assets.import.update-row'), [
            'row_id' => $ids['B'],
            'field_name' => 'name',
            'new_value' => 'Edited B',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $names = \DB::table('temporary_asset_imports')->orderBy('id')->pluck('name', 'tag');

        $this->assertSame('Edited B', $names['TAG-B'], 'The edit must land on the row it named.');
        $this->assertSame('Row C', $names['TAG-C'], 'C sat at the shifted position and must be untouched.');
    }

    /**
     * Looking rows up by ID makes the ID attacker-supplied, so the tenant
     * predicates carry the whole weight now.
     *
     * The two endpoints refuse differently on purpose. The single-cell path 422s,
     * indistinguishably from a stale id, so it can't be used to probe existence.
     * The bulk path drops unowned ids and reports only how many rows it touched —
     * a mixed list has to keep working, so it can't fail the whole request. Either
     * way the foreign row must come out untouched, which is what's asserted.
     */
    public function test_row_operations_reject_a_staging_row_id_from_another_property(): void
    {
        $foreignId = \DB::table('temporary_asset_imports')->insertGetId([
            'user_id' => $this->userB->id,
            'property_id' => $this->propertyB->id,
            'tag' => 'TAG-FOREIGN',
            'name' => 'Belongs To Beta',
            'category_id' => $this->categoryB->id,
            'department_id' => $this->departmentB->id,
            'status' => 'in_service',
            'is_invalid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();

        $this->postJson(route('assets.import.update-row'), [
            'row_id' => $foreignId,
            'field_name' => 'name',
            'new_value' => 'Hijacked',
        ])->assertStatus(422);

        $this->postJson(route('assets.import.delete-rows'), ['row_ids' => [$foreignId]])
            ->assertStatus(200)
            ->assertJson(['success' => true, 'deletedCount' => 0]);

        $foreign = \DB::table('temporary_asset_imports')->find($foreignId);
        $this->assertNotNull($foreign, 'The other property\'s row must survive.');
        $this->assertSame('Belongs To Beta', $foreign->name);
    }

    /**
     * Everything above depends on the id actually reaching the markup, which no
     * endpoint test can see. data-row-id is also the handle the planned
     * multi-select is meant to select rows with, so it is a contract, not an
     * implementation detail — pin both it and the Alpine scope value.
     */
    public function test_review_rows_expose_their_staging_id_to_the_dom_and_alpine_scope(): void
    {
        $this->actingAs($this->userA);
        $ids = $this->seedThreeStagingRows();

        $response = $this->get(route('assets.import-review'));
        $response->assertStatus(200);

        foreach ($ids as $id) {
            $response->assertSee('data-row-id="'.$id.'"', false);
            $response->assertSee('rowId: '.$id.',', false);
        }

        // The handlers must read the id from that scope, not take a position.
        $response->assertSee("autoSave('tag', \$event.target.value, \$data)", false);
        $response->assertSee('toggleRow(rowId)', false);
        $response->assertDontSee('absolute_index', false);
    }

    // ══════════════════════════════════════════════════════════════
    // 11. MULTI-SELECT: BULK COLUMN EDIT + BULK DELETE
    // ══════════════════════════════════════════════════════════════

    /**
     * Seed one staging row for userA, overriding whatever the case needs.
     */
    private function seedStagingRow(array $overrides = []): int
    {
        return \DB::table('temporary_asset_imports')->insertGetId(array_merge([
            'user_id' => $this->userA->id,
            'property_id' => $this->propertyA->id,
            'tag' => 'BULK-'.\Str::random(4),
            'name' => 'Bulk Row',
            'category_id' => $this->categoryA->id,
            'department_id' => $this->departmentA->id,
            'status' => 'in_service',
            'model' => 'original model',
            'serial_number' => 'SN-ORIGINAL',
            'remarks' => 'original remarks',
            'is_invalid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * The whole point of the bulk endpoint is surgical reach: exactly the named
     * column, on exactly the selected rows. This snapshots the full rows before
     * and after and diffs them, so an accidental write to a neighbouring column
     * (or an unselected row) shows up as a failure rather than passing unnoticed
     * because nobody thought to assert on that particular field.
     */
    public function test_bulk_update_touches_only_the_named_column_on_only_the_selected_rows(): void
    {
        $this->actingAs($this->userA);

        $selectedA = $this->seedStagingRow(['tag' => 'SEL-A', 'name' => 'Selected A']);
        $selectedB = $this->seedStagingRow(['tag' => 'SEL-B', 'name' => 'Selected B']);
        $untouched = $this->seedStagingRow(['tag' => 'UNSEL', 'name' => 'Not Selected']);

        $before = \DB::table('temporary_asset_imports')->orderBy('id')->get()->keyBy('id');

        $this->postJson(route('assets.import.bulk-update-rows'), [
            'row_ids' => [$selectedA, $selectedB],
            'field_name' => 'model',
            'new_value' => 'UltraSharp U2723QE',
        ])->assertStatus(200)->assertJson(['success' => true, 'updatedCount' => 2]);

        $after = \DB::table('temporary_asset_imports')->orderBy('id')->get()->keyBy('id');

        foreach ([$selectedA, $selectedB] as $id) {
            $this->assertSame('UltraSharp U2723QE', $after[$id]->model);

            // Every other column on the edited rows must be byte-identical.
            foreach ((array) $before[$id] as $column => $value) {
                if (in_array($column, ['model', 'updated_at'], true)) {
                    continue;
                }
                $this->assertSame($value, $after[$id]->$column, "Column [{$column}] was collateral damage.");
            }
        }

        $this->assertEquals((array) $before[$untouched], (array) $after[$untouched],
            'An unselected row was modified.');
    }

    /**
     * A selection can legitimately contain ids the caller does not own only if
     * someone hand-edits the request — but the legitimate ids in the same list
     * still have to apply. Failing the whole request would be the easy answer and
     * the wrong one; dropping the foreign id silently is the contract.
     */
    public function test_bulk_update_skips_foreign_ids_while_still_applying_the_owned_ones(): void
    {
        $foreignId = \DB::table('temporary_asset_imports')->insertGetId([
            'user_id' => $this->userB->id,
            'property_id' => $this->propertyB->id,
            'tag' => 'TAG-FOREIGN',
            'name' => 'Belongs To Beta',
            'category_id' => $this->categoryB->id,
            'department_id' => $this->departmentB->id,
            'status' => 'in_service',
            'model' => 'beta model',
            'is_invalid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->userA);
        $ownedId = $this->seedStagingRow(['tag' => 'OWNED']);

        $this->postJson(route('assets.import.bulk-update-rows'), [
            'row_ids' => [$ownedId, $foreignId],
            'field_name' => 'model',
            'new_value' => 'Applied',
        ])->assertStatus(200)->assertJson(['success' => true, 'updatedCount' => 1]);

        $this->assertSame('Applied', \DB::table('temporary_asset_imports')->find($ownedId)->model);
        $this->assertSame('beta model', \DB::table('temporary_asset_imports')->find($foreignId)->model,
            'The other property\'s row must not be reachable through a bulk list.');
    }

    /**
     * The bulk path must not become a shortcut around the FK check the
     * single-cell path enforces — otherwise selecting rows would be a way to
     * assign another tenant's category. The rejection also has to land before
     * any write, so a bad value can't apply to part of the selection.
     */
    public function test_bulk_update_enforces_the_same_fk_validation_as_the_single_row_path(): void
    {
        $this->actingAs($this->userA);
        $rowId = $this->seedStagingRow();

        foreach ([['category_id', $this->categoryB->id], ['department_id', $this->departmentB->id]] as [$field, $foreignValue]) {
            $this->postJson(route('assets.import.bulk-update-rows'), [
                'row_ids' => [$rowId],
                'field_name' => $field,
                'new_value' => (string) $foreignValue,
            ])->assertStatus(422);

            $this->assertNotEquals(
                $foreignValue,
                \DB::table('temporary_asset_imports')->find($rowId)->$field,
                "A foreign {$field} was written despite the 422."
            );
        }

        // The same value from the caller's own property is accepted.
        $this->postJson(route('assets.import.bulk-update-rows'), [
            'row_ids' => [$rowId],
            'field_name' => 'category_id',
            'new_value' => (string) $this->categoryA->id,
        ])->assertStatus(200);

        $this->assertEquals($this->categoryA->id, \DB::table('temporary_asset_imports')->find($rowId)->category_id);
    }

    public function test_bulk_update_rejects_a_field_outside_the_editable_whitelist(): void
    {
        $this->actingAs($this->userA);
        $rowId = $this->seedStagingRow();

        foreach (['user_id', 'property_id', 'is_invalid', 'id'] as $field) {
            $this->postJson(route('assets.import.bulk-update-rows'), [
                'row_ids' => [$rowId],
                'field_name' => $field,
                'new_value' => '999',
            ])->assertStatus(422);
        }

        $row = \DB::table('temporary_asset_imports')->find($rowId);
        $this->assertEquals($this->userA->id, $row->user_id);
        $this->assertEquals($this->propertyA->id, $row->property_id);
    }

    /**
     * is_invalid is recalculated for the whole selection in one statement, so the
     * expression has to reproduce updateSingleRow()'s rule exactly — including
     * the easily-missed part where a row with neither name nor tag counts as a
     * blank placeholder and stays VALID. Two other places in the controller use a
     * simpler expression that flags such rows invalid; if the bulk path copied
     * that one instead, editing fifty rows and editing one would disagree.
     */
    public function test_bulk_update_recalculates_invalid_flags_and_global_counters(): void
    {
        $this->actingAs($this->userA);

        $valid = $this->seedStagingRow(['tag' => 'V-1', 'name' => 'Has Everything']);
        $noName = $this->seedStagingRow(['tag' => 'V-2', 'name' => '', 'is_invalid' => true]);
        $blank = $this->seedStagingRow(['tag' => '', 'name' => '', 'is_invalid' => true]);

        // Clearing the category invalidates any row that still has a name.
        $response = $this->postJson(route('assets.import.bulk-update-rows'), [
            'row_ids' => [$valid, $noName, $blank],
            'field_name' => 'category_id',
            'new_value' => '',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true, 'updatedCount' => 3]);

        $rows = \DB::table('temporary_asset_imports')->orderBy('id')->get()->keyBy('id');

        $this->assertTrue((bool) $rows[$valid]->is_invalid, 'A named row with no category is invalid.');
        $this->assertTrue((bool) $rows[$noName]->is_invalid, 'A tagged row with no name is invalid.');
        $this->assertFalse((bool) $rows[$blank]->is_invalid, 'A wholly blank row is a placeholder, not an error.');

        // The global counters follow the pre-existing definition, which is NOT a
        // sum of the is_invalid flags: validCount requires a non-empty name, and
        // invalidCount is whatever's left over. So the blank row reads as valid
        // per-row yet still counts against invalidCount. Asserting the real
        // numbers rather than the intuitive ones keeps this test honest about
        // what the endpoint actually reports.
        $response->assertJson(['totalCount' => 3, 'validCount' => 0, 'invalidCount' => 3]);
        $this->assertSame([1], $response->json('invalidPages'));

        // And the per-row flags handed back for repainting must match too.
        $this->assertTrue($response->json('rowFlags.'.$valid));
        $this->assertFalse($response->json('rowFlags.'.$blank));
    }

    public function test_bulk_delete_removes_exactly_the_selected_rows(): void
    {
        $this->actingAs($this->userA);

        $doomedA = $this->seedStagingRow(['tag' => 'DEL-A']);
        $doomedB = $this->seedStagingRow(['tag' => 'DEL-B']);
        $keep = $this->seedStagingRow(['tag' => 'KEEP']);

        $this->postJson(route('assets.import.delete-rows'), [
            'row_ids' => [$doomedA, $doomedB],
        ])->assertStatus(200)->assertJson([
            'success' => true,
            'deletedCount' => 2,
            'totalCount' => 1,
        ]);

        $remaining = \DB::table('temporary_asset_imports')->pluck('tag')->all();
        $this->assertSame(['KEEP'], $remaining);
        $this->assertNotNull(\DB::table('temporary_asset_imports')->find($keep));
    }

    public function test_bulk_delete_leaves_another_propertys_rows_alone(): void
    {
        $foreignId = \DB::table('temporary_asset_imports')->insertGetId([
            'user_id' => $this->userB->id,
            'property_id' => $this->propertyB->id,
            'tag' => 'TAG-FOREIGN',
            'name' => 'Belongs To Beta',
            'category_id' => $this->categoryB->id,
            'department_id' => $this->departmentB->id,
            'status' => 'in_service',
            'is_invalid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->userA);
        $ownedId = $this->seedStagingRow(['tag' => 'OWNED']);

        $this->postJson(route('assets.import.delete-rows'), [
            'row_ids' => [$ownedId, $foreignId],
        ])->assertStatus(200)->assertJson(['deletedCount' => 1]);

        $this->assertNull(\DB::table('temporary_asset_imports')->find($ownedId));
        $this->assertNotNull(\DB::table('temporary_asset_imports')->find($foreignId));
    }

    /**
     * The Action column is gone, so selection is now the only route to delete.
     * The bulk header widgets must also be the same element type the column uses
     * per row — a text box where a category dropdown belongs would let the user
     * type a name that can never resolve to a category id.
     */
    public function test_review_page_renders_multi_select_controls_instead_of_an_action_column(): void
    {
        $this->actingAs($this->userA);
        $ids = $this->seedThreeStagingRows();

        $response = $this->get(route('assets.import-review'));
        $response->assertStatus(200);

        // Selection plumbing.
        $response->assertSee('x-ref="selectAll"', false);
        $response->assertSee('indeterminate = someSelected && !allSelected', false);
        $response->assertSee('@click="toggleRow(rowId)"', false);
        $response->assertSee('@contextmenu="openRowMenu($event, rowId)"', false);
        $response->assertSee('pageRowIds', false);

        foreach ($ids as $id) {
            $response->assertSee('data-row-id="'.$id.'"', false);
        }

        // Bulk widgets, with the FK columns wired to real option lists.
        $response->assertSee("bulkUpdate('category_id', \$event.target.value)", false);
        $response->assertSee("bulkUpdate('status', \$event.target.value)", false);
        $response->assertSee("bulkUpdate('purchase_date', \$event.target.value)", false);
        $response->assertSee(route('assets.import.bulk-update-rows', [], false), false);

        // The Action column and its per-row delete button are gone.
        $response->assertDontSee('>'.__('assets.action').'</th>', false);
        $response->assertDontSee('requestDeleteRow($data)', false);

        // Header and body must still agree on the column count. Counting the
        // rendered cells catches a dropped <th>/<td> pair, which eyeballing the
        // Blade does not.
        $html = $response->getContent();
        preg_match('/<thead.*?<\/thead>/s', $html, $thead);
        preg_match('/<tr data-row-id.*?<\/tr>/s', $html, $firstRow);

        // [\s>] so the opening <thead> tag isn't counted as a cell.
        $this->assertSame(11, preg_match_all('/<th[\s>]/', $thead[0] ?? ''), 'Expected 11 header cells.');
        $this->assertSame(11, preg_match_all('/<td[\s>]/', $firstRow[0] ?? ''), 'Body row must match the header.');
    }

    /**
     * A selected row swaps its number for a ticked box and takes a background of
     * its own. The background is asserted as an exclusive branch rather than two
     * stacked class lists on purpose: "selected" and "invalid" both set a
     * background, and if both classes were emitted at once the winner would be
     * decided by Tailwind's output order rather than by anything in this file.
     */
    public function test_selected_rows_swap_the_number_for_a_tick_and_change_colour(): void
    {
        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();

        $html = $this->get(route('assets.import-review'))->assertStatus(200)->getContent();

        preg_match('/<tr data-row-id.*?<\/td>/s', $html, $m);
        $rowMarkup = $m[0] ?? '';

        // Number and tick are mutually exclusive within the one cell.
        $this->assertStringContainsString('x-show="!isSelected(rowId)"', $rowMarkup);
        $this->assertStringContainsString('x-show="isSelected(rowId)"', $rowMarkup);
        $this->assertStringContainsString('x-cloak', $rowMarkup,
            'The tick must be cloaked or it flashes on every row before Alpine boots.');

        // Exactly one background class per state — no stacking.
        $this->assertStringContainsString('bg-accent/10 dark:bg-accent/20', $rowMarkup,
            'Selected valid rows need their own background, not just a ring.');
        $this->assertStringContainsString('bg-red-100 dark:bg-red-900/50', $rowMarkup,
            'A selected invalid row must stay visibly invalid.');
        // Scoped to the <tr> tag alone — the number <td> has its own, unrelated
        // isSelected() binding for the text colour.
        preg_match('/<tr data-row-id.*?>/s', $html, $trTag);
        $this->assertSame(1, substr_count($trTag[0] ?? '', ':class='),
            'Row background must come from a single exclusive branch, not stacked class lists.');
    }

    public function test_multi_select_copy_follows_the_active_locale(): void
    {
        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();

        foreach (['en', 'id'] as $locale) {
            app()->setLocale($locale);
            $response = $this->get(route('assets.import-review'));
            $response->assertStatus(200);
            $response->assertSee(__('assets.clear_selection'), false);
            $response->assertSee(__('assets.select_all_rows'), false);
        }

        // Guard against both assertions above trivially matching the same string.
        foreach (['clear_selection', 'select_all_rows', 'delete_rows_title', 'rows_selected'] as $key) {
            app()->setLocale('en');
            $en = __('assets.'.$key);
            app()->setLocale('id');
            $this->assertNotEquals($en, __('assets.'.$key), "Key [{$key}] is not actually translated.");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // 12. QUICK ADD CATEGORY / DEPARTMENT FROM THE REVIEW PAGE
    // ══════════════════════════════════════════════════════════════

    /**
     * Grant userA a permission string on the RBAC module that gates this entity.
     *
     * Set through the real column the policy reads rather than by mocking Gate:
     * the whole point of these tests is that quick-add goes through the same
     * hasPermission() matrix the create forms do, including its non-obvious
     * compound values ('create & update' grants create, 'update' does not).
     */
    private function grantPermission(string $module, string $permission): void
    {
        $this->userA->role->update([$module => $permission]);
        $this->userA->refresh()->load('role');
    }

    private function quickAdd(array $payload)
    {
        return $this->postJson(route('assets.import.quick-add-entity'), $payload);
    }

    public function test_quick_add_is_refused_without_the_create_permission(): void
    {
        $this->actingAs($this->userA);

        // RoleFactory defaults every perm_* to 'no access', so this is the
        // out-of-the-box state, not a contrived one.
        $this->quickAdd(['entity_type' => 'category', 'name' => 'Furniture'])
            ->assertStatus(403);
        $this->quickAdd(['entity_type' => 'department', 'name' => 'Housekeeping'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('categories', ['name' => 'FURNITURE']);
        $this->assertDatabaseMissing('departments', ['name' => 'HOUSEKEEPING']);
    }

    /**
     * Each permission string is exercised against the real policy, so a shortcut
     * like `$perm === 'create'` in the controller would fail here rather than
     * silently locking out everyone on a compound permission.
     */
    public function test_quick_add_honours_every_permission_string_that_grants_create(): void
    {
        $this->actingAs($this->userA);

        foreach (['full access', 'create', 'create & update', 'create & delete'] as $i => $permission) {
            $this->grantPermission('perm_categories', $permission);
            $this->quickAdd(['entity_type' => 'category', 'name' => 'Allowed '.$i])
                ->assertStatus(200);
        }

        foreach (['no access', 'update', 'delete', 'update & delete'] as $i => $permission) {
            $this->grantPermission('perm_categories', $permission);
            $this->quickAdd(['entity_type' => 'category', 'name' => 'Refused '.$i])
                ->assertStatus(403);
        }

        $this->assertSame(4, Category::where('property_id', $this->propertyA->id)
            ->where('name', 'like', 'ALLOWED%')->count());
        $this->assertSame(0, Category::where('property_id', $this->propertyA->id)
            ->where('name', 'like', 'REFUSED%')->count());
    }

    /**
     * Category and Department are gated by separate RBAC modules — holding one
     * must not imply the other.
     */
    public function test_category_and_department_permissions_are_independent(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Linens'])->assertStatus(200);
        $this->quickAdd(['entity_type' => 'department', 'name' => 'Linens'])->assertStatus(403);
    }

    /**
     * The generated code must be byte-identical to what the Categories screen
     * would produce, so it is compared against the service itself rather than a
     * hardcoded string that could drift from the generator.
     */
    public function test_a_blank_code_is_generated_by_the_same_service_the_rest_of_the_app_uses(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $expected = app(EntityCodeGeneratorService::class)
            ->generateUniqueCode('WORKSTATIONS', Category::class, $this->propertyA->id);

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Workstations', 'code' => ''])
            ->assertStatus(200)
            ->assertJsonPath('entity.code', $expected);

        $this->assertDatabaseHas('categories', [
            'property_id' => $this->propertyA->id,
            'name'        => 'WORKSTATIONS',
            'code'        => $expected,
        ]);
    }

    /**
     * The happy path only exercises the generator's first branch. Two names
     * sharing a 3-char prefix force it into its collision fallbacks, which is
     * where a re-implementation would most plausibly disagree.
     */
    public function test_generated_codes_stay_unique_when_names_share_a_prefix(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $codes = [];
        foreach (['Cabling', 'Cabinets', 'Cabins'] as $name) {
            $response = $this->quickAdd(['entity_type' => 'category', 'name' => $name])
                ->assertStatus(200);
            $codes[] = $response->json('entity.code');
        }

        $this->assertCount(3, array_unique($codes), 'Generated codes collided: '.implode(', ', $codes));
    }

    public function test_a_supplied_code_is_kept_and_both_fields_are_upper_cased(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_departments', 'full access');

        $this->quickAdd([
            'entity_type' => 'department',
            'name'        => 'front office',
            'code'        => 'fo',
        ])->assertStatus(200);

        // Matches CategoryController/DepartmentController::store, which upper-case
        // both fields before persisting.
        $this->assertDatabaseHas('departments', [
            'property_id' => $this->propertyA->id,
            'name'        => 'FRONT OFFICE',
            'code'        => 'FO',
        ]);
    }

    public function test_notes_are_stored_when_given(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $this->quickAdd([
            'entity_type' => 'category',
            'name'        => 'Vehicles',
            'notes'       => 'Fleet only',
        ])->assertStatus(200);

        $this->assertDatabaseHas('categories', ['name' => 'VEHICLES', 'notes' => 'Fleet only']);
    }

    /**
     * The flag has to reach the column hasExecutiveOversight() actually reads,
     * not just be accepted by validation.
     */
    public function test_executive_oversight_applies_to_a_department(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_departments', 'full access');

        $this->quickAdd([
            'entity_type'            => 'department',
            'name'                   => 'Executive',
            'is_executive_oversight' => true,
        ])->assertStatus(200);

        $this->quickAdd([
            'entity_type' => 'department',
            'name'        => 'Laundry',
        ])->assertStatus(200);

        $executive = Department::where('name', 'EXECUTIVE')->firstOrFail();
        $laundry   = Department::where('name', 'LAUNDRY')->firstOrFail();

        $this->assertTrue((bool) $executive->is_executive_oversight);
        $this->assertFalse((bool) $laundry->is_executive_oversight);

        $member = User::factory()->create([
            'property_id'   => $this->propertyA->id,
            'role_id'       => $this->userA->role_id,
            'department_id' => $executive->id,
        ]);
        $this->assertTrue($member->hasExecutiveOversight());
    }

    /**
     * Categories have no such column. The request must not error, and must not
     * smuggle the attribute through — a mass-assignment change on Category would
     * otherwise turn this into a silent write.
     */
    public function test_executive_oversight_is_ignored_for_a_category(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $this->quickAdd([
            'entity_type'            => 'category',
            'name'                   => 'Appliances',
            'is_executive_oversight' => true,
        ])->assertStatus(200);

        $category = Category::where('name', 'APPLIANCES')->firstOrFail();
        $this->assertArrayNotHasKey('is_executive_oversight', $category->getAttributes());
    }

    /**
     * Same rules as CategoryController::store, with the single deliberate
     * exception that code may be blank (it is generated instead).
     */
    public function test_validation_matches_the_existing_creation_path(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $this->quickAdd(['entity_type' => 'category'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->quickAdd(['entity_type' => 'category', 'name' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Ok', 'code' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Ok', 'notes' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->quickAdd(['entity_type' => 'location', 'name' => 'Ok'])
            ->assertStatus(422)->assertJsonValidationErrors('entity_type');

        $this->assertSame(1, Category::where('property_id', $this->propertyA->id)->count(),
            'Only the setUp fixture should exist — no invalid request wrote a row.');
    }

    public function test_quick_add_entities_belong_to_the_callers_property(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_categories', 'full access');

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Signage'])->assertStatus(200);

        $created = Category::withoutGlobalScopes()->where('name', 'SIGNAGE')->firstOrFail();
        $this->assertSame($this->propertyA->id, $created->property_id);

        // And it must not leak into the other tenant's review page.
        $this->actingAs($this->userB);
        $this->assertSame(0, Category::where('property_id', $this->propertyB->id)
            ->where('name', 'SIGNAGE')->count());
    }

    /**
     * A super-admin has no property_id of their own; every other creation path in
     * the app reads session('active_property_id') instead, and so must this one.
     */
    public function test_super_admin_creates_against_the_active_session_property(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_id'    => null,
            'department_id'  => null,
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['active_property_id' => $this->propertyB->id]);

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Global'])->assertStatus(200);

        $created = Category::withoutGlobalScopes()->where('name', 'GLOBAL')->firstOrFail();
        $this->assertSame($this->propertyB->id, $created->property_id);
    }

    public function test_super_admin_without_an_active_property_is_refused(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'property_id'    => null,
            'department_id'  => null,
        ]);

        $this->actingAs($superAdmin);
        $this->quickAdd(['entity_type' => 'category', 'name' => 'Orphan'])->assertStatus(422);

        $this->assertSame(0, Category::withoutGlobalScopes()->where('name', 'ORPHAN')->count());
    }

    // ── Review page rendering ────────────────────────────────────────

    public function test_the_quick_add_trigger_is_absent_without_the_permission(): void
    {
        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();

        $html = $this->get(route('assets.import-review'))->assertStatus(200)->getContent();

        // Absent from the DOM entirely, not merely disabled.
        $this->assertStringNotContainsString('data-quick-add="category"', $html);
        $this->assertStringNotContainsString('data-quick-add="department"', $html);
        $this->assertStringNotContainsString(__('assets.quick_add_category'), $html);
    }

    public function test_the_quick_add_trigger_renders_per_entity_permission(): void
    {
        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();
        $this->grantPermission('perm_categories', 'create');

        $html = $this->get(route('assets.import-review'))->assertStatus(200)->getContent();

        $this->assertStringContainsString('data-quick-add="category"', $html);
        $this->assertStringContainsString(__('assets.quick_add_category'), $html);

        // perm_departments is still 'no access' — one permission must not imply the other.
        $this->assertStringNotContainsString('data-quick-add="department"', $html);
    }

    /**
     * The new option has to land in the per-row select AND the bulk-edit header
     * select. They are separate markup, so a fix to one that misses the other
     * would leave bulk edit unable to reach the entity that was just created.
     */
    public function test_a_new_entity_is_selectable_in_both_the_row_and_bulk_dropdowns(): void
    {
        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();
        $this->grantPermission('perm_categories', 'full access');

        $this->quickAdd(['entity_type' => 'category', 'name' => 'Zippers'])->assertStatus(200);
        $created = Category::where('name', 'ZIPPERS')->firstOrFail();

        $html = $this->get(route('assets.import-review'))->assertStatus(200)->getContent();

        // Both select flavours carry the marker the frontend injects options into,
        // and both are server-rendered with the new entity after a reload.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'data-entity-select="category"'),
            'Expected the bulk header select plus at least one row select.'
        );
        $this->assertSame(
            4,
            substr_count($html, 'value="'.$created->id.'"'),
            'Expected the new option in the bulk header select and in all three row selects.'
        );
    }

    /**
     * Applies to the whole Smart Import flow, not just the new icon: a
     * question-mark cursor reads as a broken page, so hover colour is the only
     * affordance these triggers use.
     */
    public function test_no_smart_import_screen_uses_a_help_cursor(): void
    {
        $this->actingAs($this->userA);
        $this->grantPermission('perm_assets', 'full access');
        $this->grantPermission('perm_categories', 'full access');
        $this->seedThreeStagingRows();
        $this->seedBannerFile('cu0001');

        $screens = [
            route('assets.index'),          // renders add-asset-modal (upload help icon)
            route('assets.import-mapping'), // status help icon + department "Fixed" badge
            route('assets.import-review'),  // the new quick-add triggers
        ];

        foreach ($screens as $url) {
            $html = $this->get($url)->assertStatus(200)->getContent();
            $this->assertStringNotContainsString('cursor-help', $html, "cursor-help still present on {$url}");
        }
    }

    public function test_quick_add_copy_follows_the_active_locale(): void
    {
        $this->actingAs($this->userA);
        $this->seedThreeStagingRows();
        $this->grantPermission('perm_categories', 'full access');
        $this->grantPermission('perm_departments', 'full access');

        foreach (['en', 'id'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('assets.import-review'))
                ->assertStatus(200)
                ->assertSee(__('assets.quick_add_category'), false)
                ->assertSee(__('assets.quick_add_department'), false)
                ->assertSee(__('assets.quick_add_code_hint'), false)
                ->assertSee(__('messages.add_new_category'), false)
                ->assertSee(__('messages.executive_oversight'), false);
        }

        foreach (['quick_add_category', 'quick_add_department', 'quick_add_code_hint', 'quick_add_error'] as $key) {
            app()->setLocale('en');
            $en = __('assets.'.$key, ['message' => 'x']);
            app()->setLocale('id');
            $this->assertNotEquals($en, __('assets.'.$key, ['message' => 'x']),
                "Key [{$key}] is not actually translated.");
        }
    }
}

