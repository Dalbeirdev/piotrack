<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_with_an_organization_can_visit_the_dashboard()
    {
        [, $owner] = makeOrganization();

        $this->actingAs($owner);

        $this->get('/dashboard')->assertOk();
    }

    public function test_authenticated_users_without_an_organization_are_sent_to_create_one()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertRedirect(route('organizations.create'));
    }
}
