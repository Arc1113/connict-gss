<?php

namespace Tests\Feature\EnhTts1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AnnouncementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ABILITIES = [
        'manage-announcement-settings',
        'manage-student-awards',
        'generate-announcement-candidates',
        'review-announcement-candidates',
        'view-announcement-usage',
    ];

    public function test_administrator_has_every_announcement_ability(): void
    {
        $administrator = User::factory()->role(UserRole::Administrator)->create();

        foreach (self::ABILITIES as $ability) {
            $this->assertTrue(Gate::forUser($administrator)->allows($ability));
        }
    }

    public function test_data_encoder_can_manage_awards_and_generate_but_cannot_review(): void
    {
        $encoder = User::factory()->role(UserRole::DataEncoder)->create();
        $gate = Gate::forUser($encoder);

        $this->assertTrue($gate->allows('manage-student-awards'));
        $this->assertTrue($gate->allows('generate-announcement-candidates'));
        $this->assertFalse($gate->allows('manage-announcement-settings'));
        $this->assertFalse($gate->allows('review-announcement-candidates'));
        $this->assertFalse($gate->allows('view-announcement-usage'));
    }

    public function test_stage_scanner_and_technical_roles_have_no_announcement_authority(): void
    {
        foreach ([
            UserRole::StageOperator,
            UserRole::ScannerOperator,
            UserRole::TechnicalOperator,
        ] as $role) {
            $user = User::factory()->role($role)->create();

            foreach (self::ABILITIES as $ability) {
                $this->assertFalse(
                    Gate::forUser($user)->allows($ability),
                    "{$role->value} unexpectedly received {$ability}.",
                );
            }
        }
    }

    public function test_inactive_users_are_denied_regardless_of_role(): void
    {
        foreach ([UserRole::Administrator, UserRole::DataEncoder] as $role) {
            $user = User::factory()->role($role)->inactive()->create();

            foreach (self::ABILITIES as $ability) {
                $this->assertFalse(Gate::forUser($user)->allows($ability));
            }
        }
    }
}
