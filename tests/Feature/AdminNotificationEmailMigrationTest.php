<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\CMS\Facades\CMS;
use Tests\TestCase;

/**
 * Covers `move_admin_notification_email_to_notification_recipients`, which hands
 * the store's old "Admin notification email" to the CMS's notification recipients.
 */
class AdminNotificationEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require base_path('packages/ecommerce/src/Integrations/Cms/migrations/2026_10_20_000001_move_admin_notification_email_to_notification_recipients.php');
    }

    public function test_admin_emails_not_yet_recipients_are_added_to_to_and_the_old_setting_cleared(): void
    {
        CMS::settings()->set('notifications.to', 'office@example.com');
        CMS::settings()->set('notifications.cc', 'sales@example.com');
        CMS::settings()->set('notifications.admin_email', 'owner@example.com; sales@example.com');

        $this->migration()->up();

        $this->assertSame('office@example.com, owner@example.com', CMS::settings()->get('notifications.to'));
        $this->assertSame('sales@example.com', CMS::settings()->get('notifications.cc'));
        $this->assertSame('', CMS::settings()->get('notifications.admin_email'));
    }

    public function test_an_admin_email_already_a_recipient_leaves_the_recipients_alone(): void
    {
        CMS::settings()->set('notifications.to', 'owner@example.com');
        CMS::settings()->set('notifications.admin_email', 'owner@example.com');

        $this->migration()->up();

        $this->assertSame('owner@example.com', CMS::settings()->get('notifications.to'));
        $this->assertSame('', CMS::settings()->get('notifications.admin_email'));
    }
}
