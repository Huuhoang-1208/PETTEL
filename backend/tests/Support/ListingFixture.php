<?php

namespace Tests\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ListingFixture
{
    /** @return array<string, mixed> */
    public static function make(): array
    {
        if (
            ! app()->environment('testing')
            || DB::connection()->getDatabaseName() !== 'pettel_test'
        ) {
            throw new RuntimeException('ListingFixture requires the pettel_test database in testing.');
        }

        $fixture = BookingFixture::make();
        $otherCaregiver = User::factory()->create();

        DB::table('user_roles')->insert([
            'user_id' => $otherCaregiver->id,
            'role' => 'CAREGIVER',
            'assigned_at' => now(),
        ]);

        $profile = $otherCaregiver->caregiverProfile()->create([
            'type' => 'INDIVIDUAL',
            'display_name' => 'Other Test Caregiver',
            'area_code' => 'DN_CENTER',
        ]);
        $template = Listing::query()->findOrFail($fixture['draft_listing_id']);
        $otherDraft = $profile->listings()->create([
            ...$template->only($template->getFillable()),
            'title' => 'Other caregiver draft',
        ]);

        return [
            ...$fixture,
            'other_caregiver' => $otherCaregiver,
            'other_draft_listing_id' => $otherDraft->id,
        ];
    }
}
