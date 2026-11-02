# BE-HA03 task 4 — Accept an toàn khi đồng thời

Bộ file mẫu chưa được áp dụng vào backend. Nội dung dưới đây là bản đã kiểm chứng: Pint PASS, PHPStan PASS, migration/seed lặp PASS, 222 tests passed (2.578 assertions) trên MySQL pettel_test. Các test hiện tại của task 2 và task 3 vẫn đạt.

## Cơ chế

Dùng transaction và khóa dòng InnoDB. Trong transaction: tìm listing từ booking thuộc caregiver; khóa listing trước booking; đọc lại booking, tài khoản/role/profile, listing và slot bằng locking read; kiểm tra overlap theo cùng listing và bốn trạng thái giữ lịch; ghi AWAITING_PAYMENT, payment_due_at và event trong cùng transaction. Các đọc ban đầu chỉ định vị listing, không quyết định trạng thái hay lịch.

Khóa listing bảo vệ cả lúc chưa có booking giữ lịch. Locking read trên slot và booking xung đột tránh đọc ảnh chụp cũ trong MySQL REPEATABLE READ. Không dùng cache. DB::transaction(..., 3) thử lại khi gặp deadlock theo cơ chế Laravel; lỗi nghiệp vụ 409 không được chuyển thành thành công.

Lịch bận được biểu diễn bằng chính booking ở trạng thái giữ lịch; không cần thêm bảng hoặc ghi đè availability_slots. Slot là khoảng mở do BE-T03 quản lý. BE-T03 phải khóa cùng listing khi sửa slot/lifecycle; các luồng cần cả listing và booking dùng thứ tự listing → booking → payment attempt. Chưa coi đây là bằng chứng tích hợp BE-T03/BE-T04 hoàn chỉnh.

## 1. ApplyListingAvailability.php

Thay toàn bộ nội dung file hiện có. Giữ apply() cho create/search; thêm isAvailableFor() với truy vấn có khóa cho accept.

Đặt tại: `D:\Dev\Projects\PETTEL\backend\app\Domain\Booking\Actions\ApplyListingAvailability.php`

```php
<?php

namespace App\Domain\Booking\Actions;

use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApplyListingAvailability
{
    private const BLOCKING_STATUSES = [
        'AWAITING_PAYMENT',
        'CONFIRMED',
        'IN_PROGRESS',
        'AWAITING_CONFIRMATION',
    ];

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function apply(
        Builder $query,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Builder {
        $startSql = $start->utc()->format('Y-m-d H:i:s.u');
        $endSql = $end->utc()->format('Y-m-d H:i:s.u');

        return $query
            ->whereHas('availabilitySlots', function (Builder $slots) use ($startSql, $endSql): void {
                $slots->where('start_at', '<=', $startSql)
                    ->where('end_at', '>=', $endSql);
            })
            ->whereNotExists(function (QueryBuilder $bookings) use ($startSql, $endSql): void {
                $bookings->selectRaw('1')
                    ->from('bookings')
                    ->whereColumn('bookings.listing_id', 'listings.id')
                    ->whereIn('bookings.status', self::BLOCKING_STATUSES)
                    ->where('bookings.start_at', '<', $endSql)
                    ->where('bookings.end_at', '>', $startSql);
            });
    }

    public function isAvailableFor(Booking $booking, bool $lock = false): bool
    {
        if ($lock && DB::transactionLevel() === 0) {
            throw new LogicException('Calendar locks require a transaction.');
        }

        $start = $booking->start_at->utc()->format('Y-m-d H:i:s.u');
        $end = $booking->end_at->utc()->format('Y-m-d H:i:s.u');
        $slots = AvailabilitySlot::query()->where('listing_id', $booking->listing_id)
            ->where('start_at', '<=', $start)->where('end_at', '>=', $end);
        $conflicts = Booking::query()->where('listing_id', $booking->listing_id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('start_at', '<', $end)->where('end_at', '>', $start);

        if ($lock) {
            // Locking reads see committed changes even after an earlier snapshot read.
            $slots->sharedLock();
            $conflicts->lockForUpdate();
        }

        return $slots->first(['id']) !== null && $conflicts->first(['id']) === null;
    }
}
```

## 2. ValidateBookingAcceptance.php

Thay toàn bộ file của task 3. Tham số mặc định vẫn hỗ trợ các test điều kiện; AcceptBooking dùng lockForAcceptance: true bên trong transaction.

Đặt tại: `D:\Dev\Projects\PETTEL\backend\app\Domain\Booking\Actions\ValidateBookingAcceptance.php`

```php
<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\CaregiverProfile;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

class ValidateBookingAcceptance
{
    public function handle(User $caregiver, string $id, bool $lockForAcceptance = false): Booking
    {
        if ($lockForAcceptance && DB::transactionLevel() === 0) {
            throw new LogicException('Accept validation requires a transaction.');
        }

        // Only locate the listing here. All decisions below use fresh locked reads.
        $locator = Booking::query()->whereKey($id)
            ->where('caregiver_user_id', $caregiver->id)->first(['listing_id']);
        if ($locator === null) {
            $this->fail('NOT_FOUND', 'Booking not found.', 404);
        }

        $listings = Listing::withTrashed()->whereKey($locator->listing_id);
        if ($lockForAcceptance) {
            $listings->lockForUpdate();
        }
        $listing = $listings->first();

        $users = User::query()->whereKey($caregiver->id);
        $roles = DB::table('user_roles')->where('user_id', $caregiver->id)
            ->where('role', 'CAREGIVER');
        if ($lockForAcceptance) {
            $users->sharedLock();
            $roles->sharedLock();
        }
        $currentCaregiver = $users->first();
        abort_if($currentCaregiver === null, 401);
        abort_unless($currentCaregiver->status === 'ACTIVE' && $roles->first() !== null, 403);

        $bookings = Booking::query()->whereKey($id)
            ->where('caregiver_user_id', $currentCaregiver->id);
        if ($lockForAcceptance) {
            $bookings->lockForUpdate();
        }
        $booking = $bookings->first();
        if ($booking === null) {
            $this->fail('NOT_FOUND', 'Booking not found.', 404);
        }
        if ($booking->status !== 'REQUESTED') {
            $this->fail('INVALID_STATE', 'Only REQUESTED bookings can be accepted.');
        }
        $this->ensureNotExpired($booking, CarbonImmutable::now('UTC'));

        if (
            $listing === null || $listing->trashed() || $listing->status !== 'ACTIVE'
            || (string) $booking->listing_id !== (string) $listing->id
        ) {
            $this->fail('INVALID_STATE', 'Listing cannot receive bookings.');
        }
        $profiles = CaregiverProfile::query()->whereKey($listing->caregiver_profile_id);
        if ($lockForAcceptance) {
            $profiles->sharedLock();
        }
        $profile = $profiles->first();
        if ($profile === null || (string) $profile->user_id !== (string) $currentCaregiver->id) {
            $this->fail('INVALID_STATE', 'Listing cannot receive bookings.');
        }

        /** @var array<string, mixed> $snapshot */
        $snapshot = $booking->listing_snapshot_json;
        if (
            $listing->service_type !== ($snapshot['service_type'] ?? null)
            || $listing->price_unit !== ($snapshot['price_unit'] ?? null)
            || $this->normalizeRules($listing->acceptance_rules_json)
                !== $this->normalizeRules($snapshot['acceptance_rules'] ?? null)
        ) {
            $this->fail('INVALID_STATE', 'Listing conditions changed. Create a new booking.');
        }

        if (! app(ApplyListingAvailability::class)->isAvailableFor($booking, $lockForAcceptance)) {
            $this->fail('AVAILABILITY_CONFLICT', 'Time range is unavailable.');
        }

        return $booking;
    }

    public function ensureNotExpired(Booking $booking, CarbonImmutable $at): void
    {
        if (
            $at->greaterThanOrEqualTo($booking->created_at->addHours(24))
            || $at->greaterThanOrEqualTo($booking->start_at)
        ) {
            $this->fail('REQUEST_EXPIRED', 'This booking request has expired.');
        }
    }

    /** @return array{schema_version: int, species: list<string>, sizes: list<string>} */
    private function normalizeRules(mixed $rules): array
    {
        if (! is_array($rules) || ($rules['schema_version'] ?? null) !== 1) {
            $this->fail('INVALID_STATE', 'Listing conditions are invalid.');
        }
        $validator = validator($rules, [
            'species' => ['required', 'array', 'list', 'min:1'],
            'species.*' => ['required', 'string', Rule::in(['DOG', 'CAT', 'OTHER'])],
            'sizes' => ['required', 'array', 'list', 'min:1'],
            'sizes.*' => ['required', 'string', Rule::in(['SMALL', 'MEDIUM', 'LARGE'])],
        ]);
        if ($validator->fails()) {
            $this->fail('INVALID_STATE', 'Listing conditions are invalid.');
        }
        $species = array_values(array_unique($rules['species']));
        $sizes = array_values(array_unique($rules['sizes']));
        sort($species);
        sort($sizes);

        return ['schema_version' => 1, 'species' => $species, 'sizes' => $sizes];
    }

    private function fail(string $code, string $message, int $status = 409): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
```

## 3. AcceptBooking.php

Tạo file mới. Đây là nơi mở transaction, đặt hold 15 phút và ghi event nguyên tử.

Đặt tại: `D:\Dev\Projects\PETTEL\backend\app\Domain\Booking\Actions\AcceptBooking.php`

```php
<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AcceptBooking
{
    public function handle(User $caregiver, string $id): Booking
    {
        return DB::transaction(function () use ($caregiver, $id): Booking {
            $validator = app(ValidateBookingAcceptance::class);
            $booking = $validator->handle($caregiver, $id, lockForAcceptance: true);
            $acceptedAt = CarbonImmutable::now('UTC');
            // Time can advance while waiting for a DB lock.
            $validator->ensureNotExpired($booking, $acceptedAt);

            $booking->status = 'AWAITING_PAYMENT';
            $booking->payment_due_at = $acceptedAt->addMinutes(15);
            $booking->version = $booking->version + 1;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => 'REQUESTED',
                'to_status' => 'AWAITING_PAYMENT',
                'actor_user_id' => $caregiver->id,
                'source' => 'USER',
                'reason_code' => null,
                'reason_text' => null,
                'created_at' => $acceptedAt->format('Y-m-d H:i:s.u'),
            ]);

            return $booking;
        }, 3);
    }
}
```

## 4. AcceptBookingController.php

Tạo file mới để nhận request từ caregiver đã đăng nhập.

Đặt tại: `D:\Dev\Projects\PETTEL\backend\app\Http\Controllers\AcceptBookingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\AcceptBooking;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcceptBookingController extends Controller
{
    public function __invoke(Request $request, string $id, AcceptBooking $acceptBooking): JsonResponse
    {
        $caregiver = $request->user();
        abort_unless($caregiver instanceof User, 401);

        $booking = $acceptBooking->handle($caregiver, $id);

        return response()->json(BookingResource::make($booking)->resolve($request));
    }
}
```

## 5. BookingAcceptanceTest.php

Tạo file test: accept/retry, quyền, listing ngừng nhận, rollback và thời gian hết hạn trong lúc xử lý.

Đặt tại: `D:\Dev\Projects\PETTEL\backend\tests\Feature\BookingAcceptanceTest.php`

```php
<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_sets_one_hold_and_event_without_changing_snapshots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01T00:00:00.123456Z'));
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $due = CarbonImmutable::now('UTC')->addMinutes(15)->format('Y-m-d\TH:i:s.u\Z');
        $url = "/api/bookings/{$booking->id}/accept";

        $this->actingAs($fixture['caregiver'])->postJson($url)
            ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT')
            ->assertJsonPath('payment_due_at', $due);
        $after = $booking->fresh()->getAttributes();
        foreach ($before as $field => $value) {
            if (! in_array($field, ['status', 'version', 'updated_at', 'payment_due_at'], true)) {
                $this->assertSame($value, $after[$field], $field);
            }
        }
        $this->assertSame($before['version'] + 1, $after['version']);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'REQUESTED',
            'to_status' => 'AWAITING_PAYMENT',
            'actor_user_id' => $fixture['caregiver']->id,
            'source' => 'USER',
        ]);

        $this->postJson($url)->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertSame($after, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_route_checks_auth_ownership_and_listing_state(): void
    {
        $this->postJson('/api/bookings/0/accept')->assertUnauthorized();
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/accept";
        $this->actingAs($fixture['owner'])->postJson($url)->assertForbidden();
        $other = BookingFixture::make()['caregiver'];
        $this->actingAs($other)->postJson($url)->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
        DB::table('listings')->where('id', $fixture['active_listing_id'])
            ->update(['status' => 'SUSPENDED']);
        $this->actingAs($fixture['caregiver'])->postJson($url)->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertSame('REQUESTED', $fixture['booking']->fresh()->status);
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_event_failure_rolls_back_booking_and_payment_deadline(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `booking_status_events`')) {
                throw new RuntimeException('Simulated accept event failure');
            }
        });

        try {
            $this->actingAs($fixture['caregiver'])
                ->postJson("/api/bookings/{$booking->id}/accept");
            $this->fail('Expected event failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated accept event failure', $exception->getMessage());
        }
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_expiry_is_checked_again_after_calendar_reads(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $deadline = $booking->created_at->addHours(24);
        DB::listen(function (QueryExecuted $query) use ($deadline): void {
            if (str_contains($query->sql, 'from `bookings`') && str_contains($query->sql, '`status` in')) {
                $this->travelTo($deadline);
            }
        });

        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$booking->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'REQUEST_EXPIRED');
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 1);
    }
}
```

## 6. BookingAcceptanceConcurrencyTest.php

Tạo file test hai tiến trình. Dùng DatabaseMigrations để dữ liệu fixture được commit và kết nối con đọc được.

Đặt tại: `D:\Dev\Projects\PETTEL\backend\tests\Feature\BookingAcceptanceConcurrencyTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceConcurrencyTest extends TestCase
{
    // Separate connections must be able to read committed fixtures.
    use DatabaseMigrations;

    public function test_two_overlapping_accepts_only_create_one_calendar_hold(): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $response = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'concurrent-accept-second')
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $second = Booking::query()->findOrFail($response->json('id'));
        $this->assertSame(0, DB::transactionLevel());

        [$winner, $loser] = $this->concurrentAccepts(
            (string) $fixture['caregiver']->id,
            [(string) $first->id, (string) $second->id],
        );
        $this->assertSame(200, $winner['status']);
        $this->assertSame(409, $loser['status']);
        $this->assertSame('AVAILABILITY_CONFLICT', $loser['body']['error']['code']);
        $this->assertSame('AWAITING_PAYMENT', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->payment_due_at);
        $this->assertSame('REQUESTED', $second->fresh()->status);
        $this->assertNull($second->fresh()->payment_due_at);
        $this->assertDatabaseCount('booking_status_events', 3);
        $this->assertSame(1, DB::table('booking_status_events')->where('to_status', 'AWAITING_PAYMENT')->count());
    }

    /**
     * @param  list<string>  $bookingIds
     * @return list<array<string, mixed>>
     */
    private function concurrentAccepts(string $caregiverId, array $bookingIds): array
    {
        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/api/bookings/'.$argv[2].'/accept', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
], '{}');
$app->instance('request', $request);
$kernel->bootstrap();
$connection = \Illuminate\Support\Facades\DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'pettel_test' || $connection->getConfig('username') !== 'pettel_test') {
    throw new \RuntimeException('Use the dedicated test database.');
}
$app['auth']->guard('web')->setUser(\App\Models\User::findOrFail($argv[1]));
$isListingLock = fn ($sql) => str_contains($sql, 'from `listings`') && str_contains($sql, 'for update');
$connection->beforeExecuting(function ($sql) use ($isListingLock) {
    if ($isListingLock($sql)) { echo "ATTEMPTING\n"; flush(); }
});
if ($argv[3] === 'hold') {
    $held = false;
    \Illuminate\Support\Facades\DB::listen(function ($query) use ($isListingLock, &$held) {
        if (! $held && $isListingLock($query->sql)) {
            $held = true;
            echo "LOCKED\n"; flush();
            fgets(STDIN);
        }
    });
}
echo "READY\n"; flush();
fgets(STDIN);
$response = $kernel->handle($request);
echo 'RESULT '.json_encode(['status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR)."\n";
$kernel->terminate($request, $response);
PHP;
        $processes = [];
        $inputs = [];
        try {
            foreach ($bookingIds as $index => $bookingId) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY, '-r', $worker, $caregiverId, $bookingId,
                    $index === 0 ? 'hold' : 'normal',
                ], base_path(), null, $input, 20);
                $processes[] = $process;
                $inputs[] = $input;
                $process->start();
                $this->assertTrue($this->waitForSignal($process, 'READY'), $process->getErrorOutput());
            }
            $inputs[0]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[0], 'LOCKED'));
            $inputs[1]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[1], 'ATTEMPTING'));
            // Process 2 has read its locator before process 1 commits.
            $inputs[0]->write("release\n");
            foreach ($inputs as $input) {
                $input->close();
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame(1, preg_match('/^RESULT (.+)$/m', $process->getOutput(), $matches), $process->getOutput());
                $results[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(implode("\n", array_map(fn ($p) => $p->getOutput().$p->getErrorOutput(), $processes)), 0, $exception);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
    }

    private function waitForSignal(Process $process, string $signal): bool
    {
        $buffer = $process->getOutput();
        if (str_contains($buffer, $signal)) {
            return true;
        }

        return $process->waitUntil(function (string $type, string $output) use (&$buffer, $signal): bool {
            $buffer .= $output;

            return str_contains($buffer, $signal);
        });
    }
}
```

## 7. Thêm route accept

Mở `D:/Dev/Projects/PETTEL/backend/routes/api.php`. Giữ các route đang có, thêm import:

```php
use App\Http\Controllers\AcceptBookingController;
```

Thêm route ở cuối file, bên ngoài các group hiện có:

```php
Route::post('/bookings/{id}/accept', AcceptBookingController::class)
    ->middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->whereNumber('id')
    ->name('bookings.accept');
```

File `api.php` trong thư mục mẫu là bản đầy đủ đối chiếu tại thời điểm soạn. Nếu bạn đã thêm route khác, giữ phần đó và chỉ bổ sung import/route trên.

## 8. Format trước khi chạy test — Command Prompt

Sau khi copy đủ file vào backend, mở CMD tại thư mục dự án:

```bat
cd /d D:\Dev\Projects\PETTEL
```

Copy nguyên dòng dưới đây; không dùng dấu nối dòng PowerShell:

```bat
docker compose -p pettel-test -f compose.test.yaml run --build --rm --no-deps --volume "D:/Dev/Projects/PETTEL/backend/app:/var/www/html/app" --volume "D:/Dev/Projects/PETTEL/backend/routes:/var/www/html/routes" --volume "D:/Dev/Projects/PETTEL/backend/tests:/var/www/html/tests" test vendor/bin/pint app/Domain/Booking/Actions/ApplyListingAvailability.php app/Domain/Booking/Actions/ValidateBookingAcceptance.php app/Domain/Booking/Actions/AcceptBooking.php app/Http/Controllers/AcceptBookingController.php routes/api.php tests/Feature/BookingAcceptanceTest.php tests/Feature/BookingAcceptanceConcurrencyTest.php
```

Kết quả mong đợi: 7 files PASS hoặc Pint thông báo đã sửa format.

## 9. Chạy kiểm tra

Chạy sau format:

```bat
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Runner kiểm tra format, PHPStan, migrate:fresh/seed, seed lại và toàn bộ test. Chỉ database test riêng pettel_test được tạo lại. Với đúng baseline và các file mẫu này: 222 tests, 2.578 assertions.

Test đồng thời cố ý giữ khóa listing ở tiến trình A; tiến trình B đã đọc locator trong transaction rồi yêu cầu cùng khóa trước khi A được cho tiếp tục. Kết quả phải là một HTTP 200 và một 409 AVAILABILITY_CONFLICT, chỉ một AWAITING_PAYMENT, booking còn lại REQUESTED/payment_due_at=null và chỉ một event accept. Test này kiểm tra cả lỗi dùng snapshot cũ sau khi đợi khóa, không dùng hai request tuần tự thay cho cạnh tranh.

## Kết quả API

`POST /api/bookings/{id}/accept` dùng phiên đăng nhập caregiver và CSRF hiện có, không cần body. Thành công HTTP 200, object booking ở gốc, status AWAITING_PAYMENT và hạn thanh toán 15 phút. Accept lặp trả 409 INVALID_STATE, không gia hạn và không thêm event. Booking xung đột khác trả 409 AVAILABILITY_CONFLICT; sai caregiver trả 404 NOT_FOUND. Giá và snapshots không đổi. REQUESTED chưa chiếm lịch; hold quá hạn chưa chuyển trạng thái vẫn giữ lịch theo contract BE-T04.

Không gọi thanh toán sandbox trong transaction accept. BE-T04 tiếp tục xử lý expiry/payment/cancel; Hoàng review commit thực tế sau khi bạn copy và chạy kiểm tra.
