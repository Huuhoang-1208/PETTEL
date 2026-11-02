<?php

namespace Tests\Feature;

use App\Models\AvailabilitySlot;
use App\Models\Listing;
use App\Models\MediaObject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListingRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_media_is_ordered_and_has_reverse_relations(): void
    {
        [$user, $listing] = $this->fixture();
        $first = $this->mediaFor($user);
        $second = $this->mediaFor($user);
        $listing->media()->attach($first->id, ['sort_order' => 2]);
        $listing->media()->attach($second->id, ['sort_order' => 1]);

        $media = $listing->media()->get();

        $this->assertSame([$second->id, $first->id], $media->modelKeys());
        $this->assertSame(1, (int) $media[0]->pivot->sort_order);
        $this->assertSame(2, (int) $media[1]->pivot->sort_order);
        $this->assertTrue($second->listings()->firstOrFail()->is($listing));
        $this->assertTrue($second->owner->is($user));
        $this->assertSame('PRIVATE', $second->visibility);
        $this->assertSame('READY', $second->state);
        $this->assertDatabaseCount('listing_media', 2);
    }

    public function test_slots_keep_utc_microseconds_and_exclude_soft_deleted_rows(): void
    {
        [$user, $listing] = $this->fixture();
        $later = $this->slotFor(
            $listing,
            $user,
            '2030-01-01T12:00:00+07:00',
            '2030-01-01T13:00:00+07:00',
        );
        $earlier = $this->slotFor(
            $listing,
            $user,
            '2030-01-01T08:00:00.123456+07:00',
            '2030-01-01T10:00:00.654321+07:00',
        );

        $this->assertSame([$earlier->id, $later->id], $listing->availabilitySlots()->get()->modelKeys());
        $this->assertInstanceOf(CarbonImmutable::class, $earlier->start_at);
        $this->assertSame('2030-01-01T01:00:00.123456Z', $earlier->start_at->toISOString());
        $this->assertSame('2030-01-01T03:00:00.654321Z', $earlier->end_at->toISOString());
        $this->assertTrue($earlier->listing->is($listing));
        $this->assertTrue($earlier->creator->is($user));

        $earlier->delete();

        $this->assertSoftDeleted('availability_slots', ['id' => $earlier->id]);
        $this->assertSame([$later->id], $listing->availabilitySlots()->get()->modelKeys());
        $this->assertSame(2, $listing->availabilitySlots()->withTrashed()->count());
    }

    public function test_relations_are_scoped_to_each_listing_and_preserve_rows_on_soft_delete(): void
    {
        [$user, $first] = $this->fixture();
        $second = $user->caregiverProfile()->firstOrFail()->listings()
            ->create($first->only($first->getFillable()));
        $media = $this->mediaFor($user);
        $first->media()->attach($media->id, ['sort_order' => 1]);
        $slot = $this->slotFor(
            $first,
            $user,
            '2030-01-01T08:00:00Z',
            '2030-01-01T10:00:00Z',
        );

        $this->assertSame(1, $first->media()->count());
        $this->assertSame(1, $first->availabilitySlots()->count());
        $this->assertSame(0, $second->media()->count());
        $this->assertSame(0, $second->availabilitySlots()->count());

        $first->delete();

        $this->assertSoftDeleted('listings', ['id' => $first->id]);
        $this->assertDatabaseHas('media_objects', ['id' => $media->id]);
        $this->assertDatabaseHas('listing_media', ['listing_id' => $first->id]);
        $this->assertDatabaseHas('availability_slots', ['id' => $slot->id, 'deleted_at' => null]);
    }

    /** @return array{User, Listing} */
    private function fixture(): array
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'CAREGIVER',
            'assigned_at' => now(),
        ]);
        $profile = $user->caregiverProfile()->create([
            'type' => 'INDIVIDUAL',
            'display_name' => 'Demo',
            'area_code' => 'DN_CENTER',
        ]);
        $listing = $profile->listings()->create([
            'title' => 'Demo daycare',
            'description' => 'Relations test',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 50000,
            'price_unit' => 'HOUR',
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['DOG'],
                'sizes' => ['SMALL'],
            ],
        ]);

        return [$user, $listing];
    }

    private function mediaFor(User $user): MediaObject
    {
        $media = new MediaObject([
            'object_key' => 'test/listings/'.Str::uuid().'.png',
            'mime_type' => 'image/png',
            'byte_size' => 512,
            'sha256' => str_repeat('a', 64),
        ]);
        $media->owner()->associate($user);
        $media->save();

        return $media->refresh();
    }

    private function slotFor(Listing $listing, User $user, string $start, string $end): AvailabilitySlot
    {
        $slot = new AvailabilitySlot([
            'start_at' => CarbonImmutable::parse($start)->utc(),
            'end_at' => CarbonImmutable::parse($end)->utc(),
        ]);
        $slot->creator()->associate($user);
        $listing->availabilitySlots()->save($slot);

        return $slot->refresh();
    }
}
