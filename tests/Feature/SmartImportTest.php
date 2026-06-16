<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\AssetImportService;
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
        $uploadedFile = new UploadedFile($tmpFile, 'test.csv', 'text/csv', null, true);

        $this->actingAs($this->userA);

        $response = $this->postJson(route('assets.import-parse'), [
            'import_file' => $uploadedFile,
        ]);

        $response->assertStatus(200);

        clearstatcache();
        $this->assertFileDoesNotExist($tmpFile, 'Uploaded /tmp file was not deleted after copy.');

        $cacheKey = 'import_state_' . $this->userA->id;
        $state = Cache::get($cacheKey);
        $this->assertNotNull($state);
        $storedPath = $state['temp_file_path'];
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($storedPath));

        $payload = [
            'temp_file_path' => $storedPath,
            'mapping' => [
                'name' => ['columns' => ['Name'], 'separator' => ' '],
                'tag' => ['columns' => ['Tag'], 'separator' => ' '],
            ],
        ];

        Cache::put($cacheKey, array_merge($state, ['true_header' => ['Name', 'Tag']]), 1800);

        $mappingResponse = $this->post(route('assets.import.process-mapping'), [
            'payload' => json_encode($payload),
        ]);

        $mappingResponse->assertStatus(200);
        $mappingResponse->assertJson(['success' => true]);
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('local')->exists($storedPath));
    }

    public function test_uploaded_file_is_deleted_on_parse_failure(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'gc_fail_');
        file_put_contents($tmpFile, "  ,  \n  ,  \n");
        $uploadedFile = new UploadedFile($tmpFile, 'test.csv', 'text/csv', null, true);

        $this->actingAs($this->userA);

        $response = $this->postJson(route('assets.import-parse'), [
            'import_file' => $uploadedFile,
        ]);

        $response->assertStatus(422);
        clearstatcache();
        $this->assertFileDoesNotExist($tmpFile, 'File was not deleted after failed parse.');
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
        $cacheKey = 'import_review_'.$this->userA->id;
        Cache::put($cacheKey, [
            ['name' => 'Review Asset', 'tag' => 'RV-001', 'category_id' => '', 'department_id' => '', 'status' => 'in_service', 'model' => '', 'serial_number' => 'SN999', 'purchase_date' => ''],
        ], now()->addMinutes(30));

        $response = $this->get(route('assets.import-review'));
        $response->assertStatus(200);
        $response->assertSee('Review Asset');
    }

    public function test_review_redirects_when_cache_null(): void
    {
        $this->actingAs($this->userA);
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
        $response = $this->post(route('assets.import-store'), [
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
        $response = $this->post(route('assets.import-store'), [
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

        $this->post(route('assets.import-store'), [
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
        $response = $this->post(route('assets.import-store'), [
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
        $response = $this->post(route('assets.import-store'), [
            'assets' => [['name' => 'X', 'tag' => 'X', 'category_id' => 1, 'department_id' => 1, 'status' => 'in_service']],
        ]);
        $response->assertRedirect(route('login'));
    }

    public function test_multiple_assets_bulk_insert(): void
    {
        $this->actingAs($this->userA);
        $response = $this->post(route('assets.import-store'), [
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
            $mock->shouldReceive('peek')
                ->once()
                ->with(\Illuminate\Support\Facades\Storage::disk('local')->path('temp/test_import.xlsx'), 'xlsx', 1)
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

        $cacheKey = 'import_review_' . $this->userA->id;

        // Generate 110 rows:
        // Index 5 (Page 1) is invalid (missing name)
        // Index 55 (Page 2) is invalid (missing category_id)
        // Others are valid
        $data = [];
        for ($i = 0; $i < 110; $i++) {
            if ($i === 5) {
                $data[] = [
                    'name' => '',
                    'tag' => 'TAG-5',
                    'category_id' => $this->categoryA->id,
                    'department_id' => $this->departmentA->id,
                    'status' => 'in_service'
                ];
            } elseif ($i === 55) {
                $data[] = [
                    'name' => 'Row 55',
                    'tag' => 'TAG-55',
                    'category_id' => '',
                    'department_id' => $this->departmentA->id,
                    'status' => 'in_service'
                ];
            } else {
                $data[] = [
                    'name' => 'Valid Row ' . $i,
                    'tag' => 'TAG-' . $i,
                    'category_id' => $this->categoryA->id,
                    'department_id' => $this->departmentA->id,
                    'status' => 'in_service'
                ];
            }
        }

        Cache::put($cacheKey, $data, now()->addMinutes(30));

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

        $cacheKey = 'import_review_' . $this->userA->id;

        // Start with an invalid row (index 5) missing name
        $data = [
            5 => [
                'name' => '',
                'tag' => 'TAG-5',
                'category_id' => $this->categoryA->id,
                'department_id' => $this->departmentA->id,
                'status' => 'in_service',
                'is_invalid' => true
            ]
        ];

        Cache::put($cacheKey, $data, now()->addMinutes(30));

        // Submit auto-save edit to fix the name of the row (index 5)
        $response = $this->postJson(route('assets.import.update-row'), [
            'absolute_index' => 5,
            'field_name' => 'name',
            'new_value' => 'Now Valid Name'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_invalid' => false,
            'invalidPages' => [],
            'validCount' => 1,
            'invalidCount' => 0
        ]);

        // Assert that the cache has been updated
        $updatedData = Cache::get($cacheKey);
        $this->assertEquals('Now Valid Name', $updatedData[5]['name']);
        $this->assertFalse($updatedData[5]['is_invalid']);
    }
}
