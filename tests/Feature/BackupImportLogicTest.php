<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Department;
use App\Models\Property;
use App\Models\Role;
use App\Services\TenantBackupService;
use App\Services\TenantRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupImportLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $propertyA;

    protected $propertyB;

    protected $backupZipPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        // Target Property for import
        $this->propertyB = Property::factory()->create(['name' => 'Property B', 'code' => 'P-B']);

        // --- Setup Source Property A and Backup ---
        $this->propertyA = Property::factory()->create(['name' => 'Property A', 'code' => 'P-A']);
        $roleA = Role::factory()->create(['property_id' => $this->propertyA->id, 'name' => 'Admin Role']);
        $deptA = Department::factory()->create(['property_id' => $this->propertyA->id, 'name' => 'IT Dept', 'code' => 'IT']);
        $catA = Category::factory()->create(['property_id' => $this->propertyA->id, 'name' => 'Laptops']);

        $assetA = Asset::factory()->create([
            'property_id' => $this->propertyA->id,
            'department_id' => $deptA->id,
            'category_id' => $catA->id,
            'name' => 'MacBook Pro',
        ]);

        Attachment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'asset_id' => $assetA->id,
            'path' => 'attachments/macbook.pdf',
            'original_name' => 'macbook.pdf',
        ]);
        Storage::disk('public')->put('attachments/macbook.pdf', 'fake PDF content');

        AssetHistory::create([
            'asset_id' => $assetA->id,
            'action'   => 'created',
            'original' => ['status' => null],
            'changes'  => ['status' => 'active'],
        ]);

        $backupService = new TenantBackupService($this->propertyA);
        $this->backupZipPath = $backupService->build();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->backupZipPath)) {
            unlink($this->backupZipPath);
        }
        parent::tearDown();
    }

    public function test_successful_restore_inserts_data_into_target_property()
    {
        // Property B currently has no related records
        $this->assertEquals(0, Role::where('property_id', $this->propertyB->id)->count());
        $this->assertEquals(0, Asset::where('property_id', $this->propertyB->id)->count());

        // Restore into Property B
        $restoreService = new TenantRestoreService($this->propertyB, $this->backupZipPath);
        $restoreService->restore();

        // Property B should now have all the data originally from Property A
        $this->assertEquals(1, Role::where('property_id', $this->propertyB->id)->count());
        $this->assertEquals('Admin Role', Role::where('property_id', $this->propertyB->id)->first()->name);

        $this->assertEquals(1, Department::where('property_id', $this->propertyB->id)->count());
        $this->assertEquals(1, Category::where('property_id', $this->propertyB->id)->count());

        $assets = Asset::where('property_id', $this->propertyB->id)->get();
        $this->assertCount(1, $assets);
        $restoredAsset = $assets->first();

        // Verify UUID relational integrity (local IDs change, UUID-based relationships are rebuilt)
        $this->assertEquals('MacBook Pro', $restoredAsset->name);
        $this->assertEquals('IT Dept', $restoredAsset->department->name);
        $this->assertEquals('Laptops', $restoredAsset->category->name);

        // Verify histories and attachments restored correctly under new Asset local ID
        $this->assertEquals(2, $restoredAsset->histories()->count());
        $this->assertEquals('created', $restoredAsset->histories()->first()->action);

        $this->assertNotNull($restoredAsset->attachments);
        $this->assertEquals('attachments/macbook.pdf', $restoredAsset->attachments->path);

        // Verify media physically extracted
        Storage::disk('public')->assertExists('attachments/macbook.pdf');
    }

    public function test_restore_does_not_overwrite_target_property_code()
    {
        $restoreService = new TenantRestoreService($this->propertyB, $this->backupZipPath);
        $restoreService->restore();

        $this->propertyB->refresh();

        // Name updates from backup, but CODE strictly does not
        $this->assertEquals('Property A', $this->propertyB->name);
        $this->assertEquals('P-B', $this->propertyB->code); // Remained P-B, not P-A!
    }

    public function test_restore_transactions_roll_back_on_failure()
    {
        // Intentionally corrupt the data.json in the zip to cause a constraint failure mid-restore
        $zip = new \ZipArchive;
        $zip->open($this->backupZipPath);
        $dataStr = $zip->getFromName('data.json');
        $data = json_decode($dataStr, true);

        // Malform the payload to force a DB exception inside DB::transaction()
        // PDO cannot bind an array to a string column, so this is guaranteed to crash.
        $data['roles'][0]['name'] = ['invalid_array' => 'true'];
        $zip->addFromString('data.json', json_encode($data));
        $zip->close();

        try {
            $restoreService = new TenantRestoreService($this->propertyB, $this->backupZipPath);
            $restoreService->restore();
            $this->fail('Restore should have thrown an exception');
        } catch (\Exception $e) {
            // Expected exception
        }

        // Assert ENTIRE transaction rolled back (No roles or departments left behind)
        $this->assertEquals(0, Role::where('property_id', $this->propertyB->id)->count());
        $this->assertEquals(0, Department::where('property_id', $this->propertyB->id)->count());
        $this->assertEquals(0, Asset::where('property_id', $this->propertyB->id)->count());

        // Assert property name also rolled back (since it was part of transaction)
        $this->propertyB->refresh();
        $this->assertEquals('Property B', $this->propertyB->name);
    }
}
