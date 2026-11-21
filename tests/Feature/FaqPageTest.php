<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_see_only_the_user_faq(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get(route('faq'));

        $response->assertOk();
        $response->assertSee('href="' . route('faq') . '"', false);
        $response->assertSee('How does GPOA monitoring work?');
        $response->assertSee('What do Pending, Ongoing and Completed mean?');
        $response->assertSee('How is my overall GPOA progress calculated?');
        $response->assertSee('How do I submit a communication letter?');
        $response->assertSee('How do I submit the narrative report?');
        $response->assertDontSee('Awaiting Report');
        $response->assertDontSee('Report Submitted');
        $response->assertDontSee('How do I review and approve or reject a GPOA or Activity Request?');
    }

    public function test_admins_see_only_the_admin_faq(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('faq'));

        $response->assertOk();
        $response->assertSee('data-interface="admin"', false);
        $response->assertSee('href="' . route('faq') . '"', false);
        $response->assertSee('How do I monitor GPOA progress per organization?');
        $response->assertSee('How does Activity Monitoring work, and what do the status filters mean?');
        $response->assertSee('How do I review a communication letter or narrative report?');
        $response->assertSee('How do I record a monitoring result?');
        $response->assertDontSee('Awaiting Report');
        $response->assertDontSee('Report Submitted');
        $response->assertDontSee('What do Pending, Ongoing and Completed mean?');
    }

    public function test_user_dashboard_sidebar_links_to_the_faq(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('faq') . '"', false);
    }

    public function test_authenticated_layout_refreshes_the_notification_badge_from_the_count_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'activity_report_approved',
            'title' => 'Report approved',
            'message' => 'Your report has been approved.',
        ]);

        $this->actingAs($user)
            ->get(route('faq'))
            ->assertOk()
            ->assertSee('data-interface="organization"', false)
            ->assertSee('unreadCountUrl:', false)
            ->assertSee('x-text="unreadCount"', false)
            ->assertSee('data-appearance-select', false)
            ->assertSee('value="system">System default', false)
            ->assertSee('orgtrack-appearance:', false)
            ->assertSee("window.matchMedia('(prefers-color-scheme: dark)')", false)
            ->assertSee('window.localStorage.setItem(storageKey, preference)', false)
            ->assertSee('button type="submit" form="sidebar-logout-form"', false);

        $this->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertExactJson(['count' => 1]);

        $notification->markAsRead();

        $this->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertExactJson(['count' => 0]);
    }

    public function test_user_can_view_notifications_and_mark_them_read(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'activity_report_approved',
            'title' => 'Report approved',
            'message' => 'Your report has been approved.',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Report approved')
            ->assertSee('Your report has been approved.')
            ->assertSee(route('notifications.read', $notification), false);

        $this->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}