<?php

namespace Tests\Feature;

use App\Domain\Review\Actions\SetReviewVisibility;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hiding_a_reported_review_updates_public_reads_and_records_history(): void
    {
        $fixture = $this->fixture();
        $listingId = $fixture['booking']->listing_id;
        $url = "/api/listings/{$listingId}";

        // A report alone does not hide the review.
        $this->getJson($url.'/reviews')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('review_count', 1);

        app(SetReviewVisibility::class)->handle(
            $fixture['admin'],
            $fixture['report_id'],
            'HIDDEN',
            '  Nội dung vi phạm  ',
        );

        $this->getJson($url.'/reviews')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('rating_average', null)
            ->assertJsonPath('review_count', 0);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.rating_average', null)
            ->assertJsonPath('data.review_count', 0);
        $this->getJson('/api/listings')->assertOk()
            ->assertJsonPath('data.0.rating_average', null)
            ->assertJsonPath('data.0.review_count', 0);

        $this->assertDatabaseHas('reviews', [
            'id' => $fixture['review_id'],
            'status' => 'HIDDEN',
            'rating' => 5,
            'body' => 'Review moderation test',
        ]);
        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $fixture['report_id'],
            'admin_user_id' => $fixture['admin']->id,
            'action' => 'REVIEW_HIDDEN',
            'target_type' => 'REVIEW',
            'target_id' => $fixture['review_id'],
            'reason' => 'Nội dung vi phạm',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $fixture['admin']->id,
            'action' => 'REVIEW_HIDDEN',
            'resource_type' => 'REVIEW',
            'resource_id' => $fixture['review_id'],
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'COMPLETED',
        ]);
        $this->assertDatabaseHas('reports', [
            'id' => $fixture['report_id'],
            'status' => 'IN_REVIEW',
        ]);
    }

    public function test_repeated_requests_do_not_duplicate_history_and_restore_updates_rating(): void
    {
        $fixture = $this->fixture();
        $action = app(SetReviewVisibility::class);
        $reviewsUrl = "/api/listings/{$fixture['booking']->listing_id}/reviews";

        $this->getJson($reviewsUrl)->assertOk()->assertJsonCount(1, 'data');

        foreach (['HIDDEN', 'HIDDEN'] as $status) {
            $action->handle($fixture['admin'], $fixture['report_id'], $status, 'Hide review');
        }
        $this->getJson($reviewsUrl)->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('moderation_actions', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        foreach (['VISIBLE', 'VISIBLE'] as $status) {
            $action->handle($fixture['admin'], $fixture['report_id'], $status, 'Restore review');
        }

        $this->getJson($reviewsUrl)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('rating_average', 5)
            ->assertJsonPath('review_count', 1);
        $this->assertDatabaseCount('moderation_actions', 2);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('moderation_actions', [
            'action' => 'REVIEW_RESTORED',
            'target_id' => $fixture['review_id'],
        ]);
    }

    public function test_action_enforces_admin_role_and_current_account_status(): void
    {
        $fixture = $this->fixture();
        $action = app(SetReviewVisibility::class);

        $this->assertActionFails(403, fn () => $action->handle(
            $fixture['owner'],
            $fixture['report_id'],
            'HIDDEN',
            'Not an admin',
        ));

        $fixture['admin']->forceFill(['status' => 'SUSPENDED'])->save();
        $this->assertActionFails(403, fn () => $action->handle(
            $fixture['admin'],
            $fixture['report_id'],
            'HIDDEN',
            'Inactive admin',
        ));

        $fixture['admin']->forceFill(['status' => 'ACTIVE'])->save();
        DB::table('user_roles')->where('user_id', $fixture['admin']->id)
            ->where('role', 'ADMIN')->delete();
        $this->assertActionFails(403, fn () => $action->handle(
            $fixture['admin'],
            $fixture['report_id'],
            'HIDDEN',
            'Revoked role',
        ));

        $this->assertDatabaseHas('reviews', ['id' => $fixture['review_id'], 'status' => 'VISIBLE']);
        $this->assertDatabaseCount('moderation_actions', 0);
    }

    public function test_invalid_status_and_reason_are_rejected(): void
    {
        $fixture = $this->fixture();
        $action = app(SetReviewVisibility::class);

        foreach ([
            ['PENDING', 'Invalid status'],
            ['HIDDEN', '   '],
            ['HIDDEN', str_repeat('a', 1001)],
        ] as [$status, $reason]) {
            $this->assertActionFails(422, fn () => $action->handle(
                $fixture['admin'],
                $fixture['report_id'],
                $status,
                $reason,
            ));
        }

        $this->assertDatabaseHas('reviews', ['id' => $fixture['review_id'], 'status' => 'VISIBLE']);
        $this->assertDatabaseCount('moderation_actions', 0);
    }

    public function test_missing_report_and_report_for_another_target_are_rejected(): void
    {
        $fixture = $this->fixture();
        $otherReport = DB::table('reports')->insertGetId([
            'reporter_user_id' => $fixture['owner']->id,
            'booking_id' => $fixture['booking']->id,
            'reason_code' => 'OTHER',
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['0', (string) $otherReport] as $id) {
            $this->assertActionFails(404, fn () => app(SetReviewVisibility::class)->handle(
                $fixture['admin'],
                $id,
                'HIDDEN',
                'Review moderation',
            ));
        }

        $this->assertDatabaseHas('reviews', ['id' => $fixture['review_id'], 'status' => 'VISIBLE']);
        $this->assertDatabaseCount('moderation_actions', 0);
    }

    public function test_audit_failure_rolls_back_visibility_and_moderation_history(): void
    {
        $fixture = $this->fixture();

        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `audit_logs`')) {
                throw new RuntimeException('Simulated audit failure');
            }
        });

        try {
            app(SetReviewVisibility::class)->handle(
                $fixture['admin'],
                $fixture['report_id'],
                'HIDDEN',
                'Rollback test',
            );
            $this->fail('Expected the audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('reviews', ['id' => $fixture['review_id'], 'status' => 'VISIBLE']);
        $this->assertDatabaseCount('moderation_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_restored_demo_review_still_does_not_count_toward_rating(): void
    {
        $fixture = $this->fixture();
        DB::table('reviews')->where('id', $fixture['review_id'])->update(['is_demo' => true]);
        $action = app(SetReviewVisibility::class);
        $url = "/api/listings/{$fixture['booking']->listing_id}/reviews";

        $action->handle($fixture['admin'], $fixture['report_id'], 'HIDDEN', 'Hide demo');
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');

        $action->handle($fixture['admin'], $fixture['report_id'], 'VISIBLE', 'Restore demo');
        $this->getJson($url)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_demo', true)
            ->assertJsonPath('rating_average', null)
            ->assertJsonPath('review_count', 0);
    }

    private function assertActionFails(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected the action to reject the operation.');
        } catch (HttpResponseException $exception) {
            $this->assertSame($status, $exception->getResponse()->getStatusCode());
        }
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $response = $this->postJson("/api/bookings/{$booking->id}/reviews", [
            'rating' => 5,
            'body' => 'Review moderation test',
        ])->assertCreated();

        $admin = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);
        $reportId = DB::table('reports')->insertGetId([
            'reporter_user_id' => $fixture['caregiver']->id,
            'review_id' => $response->json('data.id'),
            'reason_code' => 'OTHER',
            'description' => 'Review report fixture',
            'status' => 'IN_REVIEW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            ...$fixture,
            'admin' => $admin,
            'review_id' => (string) $response->json('data.id'),
            'report_id' => (string) $reportId,
        ];
    }
}
