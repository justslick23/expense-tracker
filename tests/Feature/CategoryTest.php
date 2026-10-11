<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function make(User $u, string $name = 'Groceries', string $type = 'expense')
    {
        return $u->categories()->create(['name' => $name, 'type' => $type]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/categories')->assertRedirect('/login');
    }

    public function test_user_can_create_a_category(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->post('/categories', ['name' => 'Groceries', 'type' => 'expense', 'color' => '#006BB6', 'icon' => 'fa-cart-shopping'])
            ->assertRedirect(route('categories.index'));

        $this->assertSame('Groceries', $u->categories()->first()->name);
    }

    public function test_duplicate_name_in_same_type_is_rejected(): void
    {
        $u = User::factory()->create();
        $this->make($u);

        $this->actingAs($u)->post('/categories', ['name' => 'Groceries', 'type' => 'expense'])->assertSessionHasErrors('name');
        $this->assertSame(1, $u->categories()->count());
    }

    public function test_same_name_is_allowed_in_other_type_and_for_other_users(): void
    {
        $u = User::factory()->create();
        $this->make($u, 'Gifts');

        $this->actingAs($u)->post('/categories', ['name' => 'Gifts', 'type' => 'income'])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create())->post('/categories', ['name' => 'Gifts', 'type' => 'expense'])->assertSessionHasNoErrors();
    }

    public function test_index_lists_only_own_categories(): void
    {
        $this->make(User::factory()->create(), 'Secret category');

        $this->actingAs(User::factory()->create())->get('/categories')->assertOk()->assertDontSee('Secret category');
    }

    public function test_cannot_edit_or_update_or_archive_someone_elses_category(): void
    {
        $cat = $this->make(User::factory()->create());
        $other = User::factory()->create();

        $this->actingAs($other)->get("/categories/{$cat->id}/edit")->assertForbidden();
        $this->actingAs($other)->put("/categories/{$cat->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($other)->patch("/categories/{$cat->id}/archive")->assertForbidden();

        $this->assertSame('Groceries', $cat->fresh()->name);
        $this->assertFalse($cat->fresh()->isArchived());
    }

    public function test_archive_hides_category_and_restore_brings_it_back(): void
    {
        $u = User::factory()->create();
        $cat = $this->make($u, 'Zzyzx Fund');

        $this->actingAs($u)->patch("/categories/{$cat->id}/archive");
        $this->assertTrue($cat->fresh()->isArchived());
        $this->actingAs($u)->get('/categories')->assertDontSee('Zzyzx Fund');
        $this->actingAs($u)->get('/categories?archived=1')->assertSee('Zzyzx Fund');

        $this->actingAs($u)->patch("/categories/{$cat->id}/restore");
        $this->assertFalse($cat->fresh()->isArchived());
        $this->actingAs($u)->get('/categories')->assertSee('Zzyzx Fund');
    }

    public function test_type_cannot_be_changed(): void
    {
        $u = User::factory()->create();
        $cat = $this->make($u);

        $this->actingAs($u)->put("/categories/{$cat->id}", ['name' => 'Groceries', 'type' => 'income'])->assertSessionHasErrors('type');
        $this->assertSame('expense', $cat->fresh()->type);
    }
}