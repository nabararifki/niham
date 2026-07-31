<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The single-asset path for the `model` column.
 *
 * `assets` had no `model` column at all, so this field could not round-trip
 * anywhere in the app. The Bulk Add Manual and Smart Import paths are covered by
 * BulkManualEntryTest and SmartImportTest respectively; this file covers the
 * ordinary create/edit/show form, which never offered the field to begin with.
 */
class AssetModelFieldTest extends TestCase
{
    use RefreshDatabase;

    protected Property $property;

    protected User $user;

    protected Category $category;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['name' => 'Hotel Alpha', 'code' => 'HA']);
        $role = Role::factory()->create([
            'property_id' => $this->property->id,
            'name' => 'Admin A',
            'perm_assets' => 'full access',
        ]);
        $this->department = Department::factory()->create([
            'property_id' => $this->property->id,
            'name' => 'IT Alpha',
            'is_executive_oversight' => true,
        ]);
        $this->category = Category::factory()->create([
            'property_id' => $this->property->id,
            'name' => 'Electronics A',
        ]);
        $this->user = User::factory()->create([
            'property_id' => $this->property->id,
            'role_id' => $role->id,
            'department_id' => $this->department->id,
        ]);
    }

    /** A valid HTTP payload for AssetController@store / @update. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tag' => 'SA-001',
            'name' => 'Single Asset',
            'category_id' => $this->category->id,
            'department_id' => $this->department->id,
            'status' => 'in_service',
            'warranty_duration' => 'none',
        ], $overrides);
    }

    /**
     * Create an Asset directly, bypassing the controller. warranty_duration is a
     * form-only field the controller unsets before Asset::create(), so it must not
     * reach mass assignment here either.
     */
    private function makeAsset(array $attributes): Asset
    {
        return Asset::create(array_merge(
            \Illuminate\Support\Arr::except($this->payload(), ['warranty_duration']),
            ['property_id' => $this->property->id],
            $attributes,
        ));
    }

    public function test_model_persists_through_the_create_form(): void
    {
        $this->actingAs($this->user);

        $this->post(route('assets.store'), $this->payload([
            'model' => 'Latitude 5540',
        ]))->assertRedirect();

        $this->assertSame(
            'Latitude 5540',
            Asset::withoutGlobalScopes()->where('tag', 'SA-001')->firstOrFail()->model
        );
    }

    public function test_model_can_be_changed_through_the_edit_form(): void
    {
        $this->actingAs($this->user);

        $asset = $this->makeAsset(['tag' => 'SA-002', 'model' => 'old model']);

        $this->put(route('assets.update', $asset), $this->payload([
            'tag' => 'SA-002',
            'model' => 'ThinkPad T14',
        ]))->assertRedirect();

        $this->assertSame('ThinkPad T14', $asset->fresh()->model);
    }

    public function test_model_can_be_cleared_through_the_edit_form(): void
    {
        $this->actingAs($this->user);

        $asset = $this->makeAsset(['tag' => 'SA-003', 'model' => 'to be removed']);

        $this->put(route('assets.update', $asset), $this->payload([
            'tag' => 'SA-003',
            'model' => '',
        ]))->assertRedirect();

        $this->assertNull($asset->fresh()->model);
    }

    public function test_over_long_model_fails_validation(): void
    {
        $this->actingAs($this->user);

        $this->post(route('assets.store'), $this->payload([
            'tag' => 'SA-004',
            'model' => str_repeat('x', 256),
        ]))->assertSessionHasErrors('model');

        $this->assertEquals(0, Asset::withoutGlobalScopes()->where('tag', 'SA-004')->count());
    }

    public function test_create_form_renders_a_model_input(): void
    {
        $this->actingAs($this->user);

        $this->get(route('assets.create'))
            ->assertStatus(200)
            ->assertSee('name="model"', false)
            ->assertSee(__('messages.model'));
    }

    public function test_edit_form_renders_the_current_model_value(): void
    {
        $this->actingAs($this->user);

        $asset = $this->makeAsset(['tag' => 'SA-005', 'model' => 'UltraSharp U2723QE']);

        $this->get(route('assets.edit', $asset))
            ->assertStatus(200)
            ->assertSee('name="model"', false)
            ->assertSee('UltraSharp U2723QE', false);
    }

    public function test_show_page_displays_the_model_value(): void
    {
        $this->actingAs($this->user);

        $asset = $this->makeAsset(['tag' => 'SA-006', 'model' => 'LaserJet 4000']);

        $this->get(route('assets.show', $asset))
            ->assertStatus(200)
            ->assertSee(__('messages.model'))
            ->assertSee('LaserJet 4000', false);
    }

    public function test_model_label_is_localized_in_both_locales(): void
    {
        foreach (['en', 'id'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotEquals(
                'messages.model',
                __('messages.model'),
                "Translation key [messages.model] missing for '{$locale}'."
            );
        }
    }
}
