<?php

namespace Database\Seeders;

use App\Models\CaregiverProfile;
use App\Models\Listing;
use App\Models\MediaObject;
use App\Models\User;
use App\Support\PublicReviewCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SitterCatalogSeeder extends Seeder
{
    private const AREAS = [
        'Hải Châu' => 'DN_CENTER',
        'Cẩm Lệ' => 'DN_SOUTH',
        'Sơn Trà' => 'DN_EAST',
        'Thanh Khê' => 'DN_NORTH',
        'Liên Chiểu' => 'DN_WEST',
        'Ngũ Hành Sơn' => 'DN_EAST',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SitterCatalogSeeder is only available in local and testing.');
        }

        $records = json_decode(file_get_contents(__DIR__.'/data/sitters.json') ?: '[]', true, flags: JSON_THROW_ON_ERROR);
        $assetDirectory = __DIR__.'/assets/sitters';
        $disk = Storage::disk('local');

        foreach ($records as $index => $record) {
            DB::transaction(function () use ($record, $index, $assetDirectory, $disk): void {
                $number = $index + 1;
                $email = sprintf('sitter-%03d@example.test', $number);
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    ['name' => $record['name'], 'password' => 'DemoOnly123!'],
                );

                DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role' => 'CAREGIVER',
                    'assigned_at' => now(),
                ]);

                $location = (string) ($record['location'] ?? 'Hải Châu, Đà Nẵng');
                $district = trim(explode(',', $location)[0]);
                $areaCode = self::AREAS[$district] ?? 'DN_CENTER';
                $imageName = basename((string) $record['image']);
                $source = $assetDirectory.'/'.$imageName;
                $image = file_get_contents($source);

                if ($image === false) {
                    throw new RuntimeException("Demo sitter image is missing: {$imageName}");
                }

                $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
                $mime = match ($extension) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };
                $objectKey = sprintf('demo/sitters/sitter-%03d.%s', $number, $extension);
                $thumbnailKey = sprintf('demo/sitters/sitter-%03d-thumb.webp', $number);
                $thumbnail = $this->makeThumbnail($image);
                $disk->put($objectKey, $image);
                $disk->put($thumbnailKey, $thumbnail);

                DB::table('media_objects')->updateOrInsert(
                    ['object_key' => $objectKey],
                    [
                        'owner_user_id' => $user->id,
                        'mime_type' => $mime,
                        'byte_size' => strlen($image),
                        'sha256' => hash('sha256', $image),
                        'visibility' => 'PUBLIC_ELIGIBLE',
                        'state' => 'READY',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $media = MediaObject::query()->where('object_key', $objectKey)->firstOrFail();

                $petTypes = $record['petType'] ?? ['dog', 'cat'];
                $species = array_values(array_unique(array_map(
                    static fn (string $type): string => match ($type) {
                        'dog' => 'DOG',
                        'cat' => 'CAT',
                        default => 'OTHER',
                    },
                    $petTypes,
                )));

                $profile = CaregiverProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'type' => ($record['type'] ?? '') === 'Khách sạn thú cưng' ? 'FACILITY' : 'INDIVIDUAL',
                        'display_name' => $record['name'],
                        'bio' => sprintf('%s · %s. Hồ sơ minh họa để thử luồng tìm sitter, xem chi tiết và đặt lịch.', $record['type'] ?? 'Người chăm sóc thú cưng', $location),
                        'demo_profile_json' => [
                            'home_type' => $record['type'] ?? 'Nhà riêng',
                            'photo_file' => $imageName,
                            'status' => $record['status'] ?? 'Sẵn sàng nhận thêm',
                            'amenities' => $record['amenities'] ?? [],
                            'trust_level' => $record['trustLevel'] ?? 'verified',
                            'confidence_score' => (int) ($record['confidenceScore'] ?? 80),
                            'verified_details' => $record['verifiedDetails'] ?? [],
                            'rating_average' => (float) ($record['rating'] ?? 4.8),
                            'review_count' => (int) ($record['reviewCount'] ?? 0),
                            'introduction' => $record['introduction'] ?? $this->demoIntroduction($record),
                            'experience_years' => (int) ($record['experienceYears'] ?? $this->demoExperience($record)),
                            'care_principles' => $record['carePrinciples'] ?? $this->demoCarePrinciples($record),
                            'space_amenities' => $record['spaceAmenities'] ?? $this->demoSpaceAmenities($record, $district),
                            'is_demo' => true,
                        ],
                        'area_code' => $areaCode,
                    ],
                );

                $description = sprintf(
                    '%s. %s. Nhận chăm sóc %s tại %s, Đà Nẵng. Có thể trao đổi trước để thống nhất lịch và nhu cầu riêng của thú cưng.',
                    $record['status'] ?? 'Sẵn sàng nhận thêm',
                    implode(' · ', $record['amenities'] ?? ['Chăm sóc tận tâm']),
                    implode(' và ', array_map(static fn (string $value): string => $value === 'DOG' ? 'chó' : ($value === 'CAT' ? 'mèo' : 'thú cưng khác'), $species)),
                    $district,
                );

                foreach (['BOARDING', 'DAYCARE'] as $offeredType) {
                    $offeredUnit = $offeredType === 'DAYCARE' ? 'HOUR' : 'NIGHT';
                    $offeredTitle = $offeredType === 'DAYCARE' ? 'Chăm sóc ban ngày' : 'Gửi gắm qua đêm';
                    $offer = Listing::query()->updateOrCreate(
                        ['caregiver_profile_id' => $profile->id, 'service_type' => $offeredType],
                        [
                            'title' => $offeredTitle,
                            'description' => $description,
                            'service_type' => $offeredType,
                            'area_code' => $areaCode,
                            'price_vnd' => max(1, (int) ($record['price'] ?? 150000)),
                            'price_unit' => $offeredUnit,
                            'acceptance_rules_json' => [
                                'schema_version' => 1,
                                'species' => $species,
                                'sizes' => ['SMALL', 'MEDIUM', 'LARGE'],
                            ],
                        ],
                    );
                    $offer->forceFill(['status' => 'ACTIVE', 'capacity' => 1, 'published_at' => now()])->save();

                    DB::table('listing_media')->updateOrInsert(
                        ['listing_id' => $offer->id, 'media_object_id' => $media->id],
                        ['sort_order' => 1],
                    );

                    DB::table('availability_slots')->updateOrInsert(
                        ['listing_id' => $offer->id, 'created_by_user_id' => $user->id],
                        [
                            'start_at' => now()->utc()->startOfDay(),
                            'end_at' => now()->utc()->addMonths(6)->endOfDay(),
                            'updated_at' => now(),
                            'created_at' => now(),
                            'deleted_at' => null,
                        ],
                    );
                }
            });
        }

        $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
        foreach ([
            ['name' => 'Demo Mèo Nấm', 'species' => 'CAT', 'size' => 'SMALL', 'age_note' => '2 tuổi'],
            ['name' => 'Demo Corgi Bắp', 'species' => 'DOG', 'size' => 'MEDIUM', 'age_note' => '3 tuổi'],
        ] as $pet) {
            DB::table('pets')->updateOrInsert(
                ['user_id' => $owner->id, 'name' => $pet['name']],
                [
                    ...$pet,
                    'sex' => 'UNKNOWN',
                    'status' => 'ACTIVE',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->seedDemoReviews();
    }

    private function seedDemoReviews(): void
    {
        /** @var list<array{name: string, email: string, pets: array<string, string>, user_id: int, pet_ids: array<string, int>}> $reviewers */
        $reviewers = [
            ['name' => 'Nguyễn Mai Phương', 'email' => 'reviewer-mai@example.test', 'pets' => ['DOG' => 'Mochi', 'CAT' => 'Mochi Mèo'], 'user_id' => 0, 'pet_ids' => []],
            ['name' => 'Trần Vũ Tuấn', 'email' => 'reviewer-tuan@example.test', 'pets' => ['DOG' => 'Bơ', 'CAT' => 'Bơ Mèo'], 'user_id' => 0, 'pet_ids' => []],
            ['name' => 'Lê Hương Giang', 'email' => 'reviewer-giang@example.test', 'pets' => ['DOG' => 'Simba', 'CAT' => 'Simba Mèo'], 'user_id' => 0, 'pet_ids' => []],
        ];

        foreach ($reviewers as &$reviewer) {
            $user = User::query()->firstOrCreate(
                ['email' => $reviewer['email']],
                ['name' => $reviewer['name'], 'password' => 'DemoOnly123!'],
            );
            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $user->id,
                'role' => 'OWNER',
                'assigned_at' => now(),
            ]);
            $reviewer['user_id'] = $user->id;
            $reviewer['pet_ids'] = [];
            foreach ($reviewer['pets'] as $species => $petName) {
                DB::table('pets')->updateOrInsert(
                    ['user_id' => $user->id, 'name' => $petName],
                    [
                        'species' => $species,
                        'size' => $species === 'CAT' ? 'SMALL' : 'MEDIUM',
                        'sex' => 'UNKNOWN',
                        'age_note' => '3 tuổi',
                        'status' => 'ACTIVE',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $reviewer['pet_ids'][$species] = DB::table('pets')
                    ->where('user_id', $user->id)
                    ->where('name', $petName)
                    ->value('id');
                $reviewer['pet_ids'][$species] = (int) $reviewer['pet_ids'][$species];
            }
        }
        unset($reviewer);

        $messages = [
            'Gia đình mình đã gửi bé %s vài lần và rất yên tâm. Sitter cập nhật ảnh, clip đúng hẹn và chăm bé theo thói quen đã trao đổi.',
            'Không gian sạch sẽ, sitter kiên nhẫn làm quen với bé %s. Mình nhận được cập nhật trong suốt thời gian gửi.',
            'Sitter trao đổi rõ ràng và chăm bé %s cẩn thận. Bé về nhà vui vẻ, gia đình sẽ tiếp tục đặt lịch.',
        ];

        Listing::query()
            ->where('status', 'ACTIVE')
            ->whereHas('caregiverProfile', fn ($profile) => $profile->whereNotNull('demo_profile_json'))
            ->with('caregiverProfile')
            ->orderBy('id')
            ->get()
            ->each(function (Listing $listing) use ($reviewers, $messages): void {
                $acceptedSpecies = $listing->acceptance_rules_json['species'] ?? ['DOG'];
                if ($acceptedSpecies === []) {
                    $acceptedSpecies = ['DOG'];
                }

                foreach ($reviewers as $index => $reviewer) {
                    $idempotencyKey = sprintf('demo-review-%d-%d', $listing->id, $index + 1);
                    $bookingId = DB::table('bookings')
                        ->where('owner_id', $reviewer['user_id'])
                        ->where('request_idempotency_key', $idempotencyKey)
                        ->value('id');
                    $start = now('UTC')->subDays(30 - ($index * 8))->startOfHour();
                    $duration = $listing->service_type === 'DAYCARE' ? 2 : 24;
                    $end = $start->copy()->addHours($duration);
                    $petSpecies = $acceptedSpecies[$index % count($acceptedSpecies)];
                    $petName = $reviewer['pets'][$petSpecies];
                    $petId = $reviewer['pet_ids'][$petSpecies];
                    $petSize = $petSpecies === 'CAT' ? 'SMALL' : 'MEDIUM';
                    $petSnapshot = [
                        'schema_version' => 1,
                        'pet_id' => (string) $petId,
                        'name' => $petName,
                        'species' => $petSpecies,
                        'size' => $petSize,
                    ];

                    if ($bookingId === null) {
                        $bookingId = DB::table('bookings')->insertGetId([
                            'owner_id' => $reviewer['user_id'],
                            'pet_id' => $petId,
                            'listing_id' => $listing->id,
                            'caregiver_user_id' => $listing->caregiverProfile->user_id,
                            'start_at' => $start->format('Y-m-d H:i:s.u'),
                            'end_at' => $end->format('Y-m-d H:i:s.u'),
                            'status' => 'COMPLETED',
                            'price_snapshot_vnd' => $listing->price_vnd,
                            'price_unit' => $listing->price_unit,
                            'price_quantity' => 1,
                            'pet_snapshot_json' => json_encode($petSnapshot, JSON_THROW_ON_ERROR),
                            'listing_snapshot_json' => json_encode([
                                'schema_version' => 1,
                                'listing_id' => (string) $listing->id,
                                'title' => $listing->title,
                                'service_type' => $listing->service_type,
                                'price_unit' => $listing->price_unit,
                                'unit_price_vnd' => $listing->price_vnd,
                            ], JSON_THROW_ON_ERROR),
                            'policy_snapshot_json' => json_encode(['schema_version' => 1, 'payment_mode' => 'SANDBOX'], JSON_THROW_ON_ERROR),
                            'request_idempotency_key' => $idempotencyKey,
                            'request_payload_hash' => hash('sha256', $idempotencyKey),
                            'created_at' => $start->format('Y-m-d H:i:s.u'),
                            'updated_at' => $end->format('Y-m-d H:i:s.u'),
                        ]);
                    }

                    $reviewedAt = $end->copy()->addHours(4);
                    DB::table('reviews')->updateOrInsert(
                        ['booking_id' => $bookingId],
                        [
                            'owner_id' => $reviewer['user_id'],
                            'listing_id' => $listing->id,
                            'rating' => $index === 2 ? 4 : 5,
                            'body' => sprintf($messages[$index % count($messages)], $petName),
                            'status' => 'VISIBLE',
                            'is_demo' => true,
                            'created_at' => $reviewedAt->format('Y-m-d H:i:s.u'),
                            'updated_at' => $reviewedAt->format('Y-m-d H:i:s.u'),
                        ],
                    );
                    PublicReviewCache::invalidate((string) $listing->id);
                }
            });
    }

    private function makeThumbnail(string $contents): string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('A demo sitter image could not be decoded by GD.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 640 / max($width, $height));
        $thumbnail = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, imagesx($thumbnail), imagesy($thumbnail), $width, $height);
        ob_start();
        $encoded = imagewebp($thumbnail, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumbnail);

        if (! $encoded || ! is_string($bytes)) {
            throw new RuntimeException('A demo sitter thumbnail could not be generated.');
        }

        return $bytes;
    }

    /** @param array<string, mixed> $record */
    private function demoIntroduction(array $record): string
    {
        if (($record['name'] ?? '') === 'Bảo Trâm') {
            return 'Xin chào các ba mẹ! Mình là Trâm. Mình làm công việc thiết kế sáng tạo từ xa nên có thời gian ở nhà, trò chuyện, vuốt ve và theo dõi các bé trong ngày. Mình đã có 5 năm làm bạn cùng Poodle và Corgi, luôn cố gắng chăm sóc từng bé theo đúng thói quen của gia đình.';
        }

        $location = trim(explode(',', (string) ($record['location'] ?? 'Đà Nẵng'))[0]);
        $amenities = implode(', ', array_slice($record['amenities'] ?? ['không gian an toàn'], 0, 2));

        return sprintf(
            'Xin chào, mình là %s. Mình nhận chăm sóc thú cưng tại %s và luôn trao đổi trước với gia đình về lịch sinh hoạt, khẩu phần ăn cùng những lưu ý riêng của bé. Không gian chăm sóc có %s.',
            $record['name'] ?? 'người chăm sóc',
            $location,
            $amenities,
        );
    }

    /** @param array<string, mixed> $record */
    private function demoExperience(array $record): int
    {
        return match ($record['experienceLevel'] ?? '') {
            'professional' => 5,
            'medical-experience' => 4,
            'experienced-with-shy' => 3,
            default => 2,
        };
    }

    /** @param array<string, mixed> $record
     * @return array<int, array{title: string, description: string}>
     */
    private function demoCarePrinciples(array $record): array
    {
        if (($record['name'] ?? '') === 'Bảo Trâm') {
            return [
                ['title' => 'Dùng cữ ăn, đúng thực đơn', 'description' => 'Chuẩn bị khẩu phần riêng, chia bữa theo gram và giờ ăn quen thuộc của bé.'],
                ['title' => 'Dạo bộ 2 cữ mỗi ngày', 'description' => 'Chọn khung giờ mát, ưu tiên tuyến đi dạo an toàn và thoáng đãng.'],
                ['title' => 'Báo cáo nhật ký chi tiết', 'description' => 'Cập nhật giờ ăn, lượng nước, sinh hoạt và hình ảnh trong ngày.'],
            ];
        }

        $amenities = implode(' và ', array_slice($record['amenities'] ?? ['không gian an toàn'], 0, 2));

        return [
            ['title' => 'Trao đổi trước khi nhận bé', 'description' => 'Thống nhất lịch trình, khẩu phần và các lưu ý chăm sóc cùng gia đình.'],
            ['title' => 'Tôn trọng thói quen của bé', 'description' => 'Giữ nhịp sinh hoạt quen thuộc trong thời gian bé được chăm sóc.'],
            ['title' => 'Cập nhật trong thời gian chăm sóc', 'description' => sprintf('Chia sẻ tình hình của bé và các tiện nghi như %s.', $amenities)],
        ];
    }

    /** @param array<string, mixed> $record
     * @return array<int, array{title: string, description: string}>
     */
    private function demoSpaceAmenities(array $record, string $district): array
    {
        if (($record['name'] ?? '') === 'Bảo Trâm') {
            return [
                ['title' => 'Ban công lưới an toàn', 'description' => 'Khu vực ban công có lưới bảo vệ; gia đình có thể trao đổi để xem ảnh thực tế trước khi đặt.'],
                ['title' => 'Máy lọc không khí cho thú cưng', 'description' => 'Hồ sơ demo ghi nhận máy lọc không khí và khu vực sinh hoạt thoáng.'],
                ['title' => 'Tủ trữ thức ăn riêng', 'description' => 'Có thể gửi thức ăn riêng của bé và thống nhất cách bảo quản trước chuyến đi.'],
                ['title' => 'Đồ chơi được vệ sinh định kỳ', 'description' => 'Đồ dùng và khu vực chơi được vệ sinh trước khi đón thú cưng.'],
                ['title' => 'Môi trường không khói thuốc', 'description' => 'Thông tin demo: không gian trong nhà không hút thuốc.'],
                ['title' => 'Có cơ sở thú y trong khu vực', 'description' => sprintf('Khu vực %s, Đà Nẵng. Vui lòng nhắn sitter để xác nhận cơ sở thú y gần nhất.', $district)],
            ];
        }

        $amenities = $record['amenities'] ?? [];
        $firstAmenity = $amenities[0] ?? 'Chưa có tiện nghi được khai báo';
        $otherAmenities = implode(' · ', array_slice($amenities, 1, 2));
        $environment = ($record['environment'] ?? '') === 'indoor-only'
            ? 'Thú cưng sinh hoạt trong nhà theo hồ sơ demo.'
            : 'Trao đổi với sitter về khu vực vận động và lịch ra ngoài.';

        return [
            ['title' => (string) ($record['type'] ?? 'Không gian chăm sóc'), 'description' => sprintf('Không gian demo tại %s, Đà Nẵng. Liên hệ sitter để xem ảnh và xác nhận bố trí thực tế.', $district)],
            ['title' => (string) $firstAmenity, 'description' => $otherAmenities !== '' ? $otherAmenities : 'Tiện nghi được ghi trong hồ sơ demo.'],
            ['title' => 'Khu vực sinh hoạt của thú cưng', 'description' => $environment],
            ['title' => 'Đồ dùng và thức ăn', 'description' => 'Gia đình có thể gửi đồ dùng quen thuộc; thống nhất cách bảo quản với sitter.'],
            ['title' => 'Vệ sinh không gian', 'description' => 'Trao đổi với sitter về lịch vệ sinh và khu vực bé sẽ sử dụng.'],
            ['title' => 'Hỗ trợ thú y lân cận', 'description' => sprintf('Khu vực %s. Nhắn sitter để xác nhận địa chỉ cơ sở thú y gần nhất.', $district)],
        ];
    }
}
