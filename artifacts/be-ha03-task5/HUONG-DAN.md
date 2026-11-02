# BE-HA03 task 5 — Test nghiệp vụ và accept đồng thời

Bộ mẫu đã được kiểm chứng với code hiện tại: Pint PASS, PHPStan PASS, migrate/seed và seed lặp PASS, 243 tests passed (2.763 assertions). Hai file mẫu bên dưới chưa được copy vào backend; kết quả này đến từ việc gắn hai file mẫu vào đúng đường dẫn trong container kiểm tra.

## 1. Ma trận checklist

| Checklist | Test thực hiện |
| --- | --- |
| Accept/reject hợp lệ | BookingAcceptanceTest và BookingRejectionTest hiện có: trạng thái, deadline, actor/source/event, snapshot và replay. |
| Sai caregiver, dữ liệu không đổi | BookingAcceptanceBusinessTest, data provider accept/reject: 404 NOT_FOUND, đối chiếu toàn bộ attributes và toàn bộ event của booking trước/sau. |
| Listing ngừng nhận | BookingAcceptanceBusinessTest: DRAFT, PENDING_REVIEW, SUSPENDED, ARCHIVED đều trả INVALID_STATE và không đổi booking/event. |
| Chuyển trạng thái không hợp lệ | Business test kiểm tra API accept ở sáu trạng thái đã xử lý; RejectionTest hiện có kiểm tra reject. |
| Hai REQUESTED trùng tồn tại | Business test tạo hai booking qua API với key khác nhau; cả hai REQUESTED trước accept, chỉ booking đầu được nhận. |
| Hai accept trùng đồng thời | ConcurrencyTest dùng hai tiến trình/DB connection: một 200, một 409 AVAILABILITY_CONFLICT; booking thua nguyên vẹn và không thêm event. |
| Booking không trùng vẫn accept | Business test và concurrency test: hai booking có khoảng cách cùng được nhận. Khác listing cùng caregiver cũng có công suất riêng theo P0. |
| Hai khoảng tiếp giáp | Business test cả hai hướng end = next start; concurrency test kiểm tra hai accept tại ranh giới tiếp giáp đều thành công. |
| Không ghi dở | AcceptanceTest và RejectionTest hiện có cố ý gây lỗi khi ghi event; kiểm tra rollback toàn bộ booking và event trong DB thực. |

## 2. Copy đúng vị trí

Thêm backend/tests/Feature/BookingAcceptanceBusinessTest.php và thay toàn bộ backend/tests/Feature/BookingAcceptanceConcurrencyTest.php bằng hai file mẫu cùng tên dưới đây. Giữ BookingAcceptanceTest.php, BookingRejectionTest.php và BookingAcceptanceConditionsTest.php hiện có.

Trong Command Prompt có thể copy chính xác bằng các lệnh:

```bat
cd /d D:\Dev\Projects\PETTEL
copy /Y "D:\Dev\Projects\PETTELrtifactse-ha03-task5\BookingAcceptanceBusinessTest.php" "D:\Dev\Projects\PETTELackend	ests\Feature\BookingAcceptanceBusinessTest.php"
copy /Y "D:\Dev\Projects\PETTELrtifactse-ha03-task5\BookingAcceptanceConcurrencyTest.php" "D:\Dev\Projects\PETTELackend	ests\Feature\BookingAcceptanceConcurrencyTest.php"
```

## 3. Format trước khi chạy test

Copy nguyên dòng sau trong CMD, sau khi đã copy đủ hai file:

```bat
docker compose -p pettel-test -f compose.test.yaml run --build --rm --no-deps --volume "D:/Dev/Projects/PETTEL/backend/tests:/var/www/html/tests" test vendor/bin/pint tests/Feature/BookingAcceptanceBusinessTest.php tests/Feature/BookingAcceptanceConcurrencyTest.php
```

Kết quả mong đợi: 2 files PASS hoặc Pint báo đã sửa format.

## 4. Chạy kiểm tra

Chạy sau format:

```bat
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Runner dùng MySQL pettel_test riêng, tạo lại database test, kiểm tra Pint/PHPStan, migration/seed, seed lặp và toàn bộ test. Với baseline hiện tại và hai file mẫu: 243 tests, 2.763 assertions. Nhóm accept/reject/điều kiện/nghiệp vụ/concurrency gồm 44 trường hợp.

ConcurrencyTest dùng DatabaseMigrations để fixture được commit cho tiến trình con nhìn thấy, không thay bằng RefreshDatabase. Mỗi tiến trình khởi động Laravel, xác thực caregiver và gọi API accept thật. Tiến trình A được giữ lại sau khi khóa listing; B đã đọc locator trước khi xin khóa listing. Parent chỉ cho A tiếp tục sau khi B bắt đầu yêu cầu khóa. Cách này kiểm tra cạnh tranh thật và lỗi ảnh chụp cũ sau khi đợi khóa; không dùng mock hay hai lời gọi tuần tự.

Với hai listing khác nhau, hai tiến trình dùng hai khóa listing riêng. Với cùng booking, chỉ một lần chuyển trạng thái/event và một lần đặt hạn; request còn lại nhận INVALID_STATE. Không tự nới hạn thanh toán.

Lưu kết quả kiểm tra vào PR/bàn giao. Chưa đánh dấu Hoàng đã review hoặc issue đã nghiệm thu chỉ vì bộ mẫu chạy qua.

## Nội dung đầy đủ: BookingAcceptanceBusinessTest.php

Tạo file mới tại D:/Dev/Projects/PETTEL/backend/tests/Feature/BookingAcceptanceBusinessTest.php. File có 17 trường hợp nhờ DataProvider; DataProvider cho chạy cùng logic với nhiều trạng thái/khoảng thời gian.

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceBusinessTest extends TestCase
{
    use RefreshDatabase;

    public static function caregiverActions(): array
    {
        return ['accept' => ['accept'], 'reject' => ['reject']];
    }

    #[DataProvider('caregiverActions')]
    public function test_wrong_caregiver_cannot_change_booking_or_history(string $action): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $other = BookingFixture::make()['caregiver'];
        $before = $booking->getAttributes();
        $events = $this->eventsFor($booking);

        $this->actingAs($other)->postJson("/api/bookings/{$booking->id}/{$action}")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertBookingUnchanged($booking, $before, $events);
    }

    public static function stoppedListingStates(): array
    {
        return array_map(fn ($status) => [$status], ['DRAFT', 'PENDING_REVIEW', 'SUSPENDED', 'ARCHIVED']);
    }

    #[DataProvider('stoppedListingStates')]
    public function test_stopped_listing_cannot_accept_booking(string $status): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        DB::table('listings')->where('id', $booking->listing_id)->update(['status' => $status]);
        $before = $booking->getAttributes();
        $events = $this->eventsFor($booking);

        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$booking->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertBookingUnchanged($booking, $before, $events);
    }

    public static function processedBookingStates(): array
    {
        return array_map(fn ($status) => [$status], [
            'AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS',
            'AWAITING_CONFIRMATION', 'COMPLETED', 'CANCELLED',
        ]);
    }

    #[DataProvider('processedBookingStates')]
    public function test_accept_from_processed_state_changes_nothing(string $status): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => $status]);
        $before = $booking->getAttributes();
        $events = $this->eventsFor($booking);

        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$booking->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertBookingUnchanged($booking, $before, $events);
    }

    public function test_two_overlapping_requests_exist_until_one_is_accepted(): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $second = $this->secondBooking($fixture);
        $before = $second->getAttributes();
        $events = $this->eventsFor($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('REQUESTED', $first->fresh()->status);
        $this->assertSame('REQUESTED', $second->fresh()->status);
        $this->assertDatabaseCount('bookings', 2);

        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$first->id}/accept")
            ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
        $this->postJson("/api/bookings/{$second->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');

        $this->assertBookingUnchanged($second, $before, $events);
        $this->assertDatabaseCount('booking_status_events', 3);
    }

    public static function separateTimeRanges(): array
    {
        return [
            'ends at first start' => [true, 0],
            'starts at first end' => [false, 0],
            'one second gap' => [false, 1],
        ];
    }

    #[DataProvider('separateTimeRanges')]
    public function test_separate_or_adjacent_bookings_can_both_be_accepted(bool $beforeFirst, int $gap): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $start = $beforeFirst ? $first->start_at->subHour() : $first->end_at->addSeconds($gap);
        $end = $start->addHour();
        $lastEnd = $end->greaterThan($first->end_at) ? $end : $first->end_at;
        DB::table('availability_slots')->where('listing_id', $first->listing_id)->update([
            'start_at' => $first->start_at->subHours(2),
            'end_at' => $lastEnd->addHour(),
        ]);
        $second = $this->secondBooking($fixture, [
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $end->format('Y-m-d\TH:i:s.u\Z'),
        ]);

        $this->actingAs($fixture['caregiver']);
        foreach ([$first, $second] as $booking) {
            $this->postJson("/api/bookings/{$booking->id}/accept")
                ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
            $this->assertNotNull($booking->fresh()->payment_due_at);
        }
        $this->assertDatabaseCount('booking_status_events', 4);
    }

    public function test_different_listings_of_one_caregiver_have_separate_capacity(): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        DB::table('listings')->where('id', $fixture['draft_listing_id'])
            ->update(['status' => 'ACTIVE', 'published_at' => now()]);
        $second = $this->secondBooking($fixture, ['listing_id' => (string) $fixture['draft_listing_id']]);

        $this->actingAs($fixture['caregiver']);
        foreach ([$first, $second] as $booking) {
            $this->postJson("/api/bookings/{$booking->id}/accept")
                ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
        }
        $this->assertDatabaseCount('booking_status_events', 4);
    }

    private function secondBooking(array $fixture, array $overrides = []): Booking
    {
        $response = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'business-second-booking')
            ->postJson('/api/bookings', array_replace($fixture['payload'], $overrides))
            ->assertCreated();

        return Booking::query()->findOrFail($response->json('id'));
    }

    private function eventsFor(Booking $booking): string
    {
        return DB::table('booking_status_events')->where('booking_id', $booking->id)
            ->orderBy('id')->get()->toJson();
    }

    private function assertBookingUnchanged(Booking $booking, array $before, string $events): void
    {
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertSame($events, $this->eventsFor($booking));
    }
}
```

## Nội dung đầy đủ: BookingAcceptanceConcurrencyTest.php

Thay toàn bộ file D:/Dev/Projects/PETTEL/backend/tests/Feature/BookingAcceptanceConcurrencyTest.php. File có 5 tình huống đồng thời và giữ cách đồng bộ bằng tín hiệu qua stdin/stdout của task 4.

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceConcurrencyTest extends TestCase
{
    // Separate connections must be able to read committed fixtures.
    use DatabaseMigrations;

    public static function acceptScenarios(): array
    {
        return [
            'overlap' => ['overlap', 409, 'AVAILABILITY_CONFLICT'],
            'adjacent' => ['adjacent', 200, null],
            'separate' => ['separate', 200, null],
            'same booking' => ['same_booking', 409, 'INVALID_STATE'],
            'different listings' => ['different_listing', 200, null],
        ];
    }

    #[DataProvider('acceptScenarios')]
    public function test_concurrent_accepts_follow_calendar_rules(string $scenario, int $secondStatus, ?string $errorCode): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $second = $first;
        if ($scenario !== 'same_booking') {
            $payload = $fixture['payload'];
            if (in_array($scenario, ['adjacent', 'separate'], true)) {
                $start = $first->end_at->addSeconds($scenario === 'separate' ? 1 : 0);
                $end = $start->addHour();
                DB::table('availability_slots')->where('listing_id', $first->listing_id)
                    ->update(['end_at' => $end->addHour()]);
                $payload['start_at'] = $start->format('Y-m-d\TH:i:s.u\Z');
                $payload['end_at'] = $end->format('Y-m-d\TH:i:s.u\Z');
            }
            if ($scenario === 'different_listing') {
                DB::table('listings')->where('id', $fixture['draft_listing_id'])
                    ->update(['status' => 'ACTIVE', 'published_at' => now()]);
                $payload['listing_id'] = (string) $fixture['draft_listing_id'];
            }
            $response = $this->actingAs($fixture['owner'])
                ->withHeader('Idempotency-Key', 'concurrent-accept-second')
                ->postJson('/api/bookings', $payload)->assertCreated();
            $second = Booking::query()->findOrFail($response->json('id'));
        }
        $beforeSecond = $second->getAttributes();
        $initialEvents = DB::table('booking_status_events')->count();
        $this->assertSame(0, DB::transactionLevel());

        [$winner, $loser] = $this->concurrentAccepts(
            (string) $fixture['caregiver']->id,
            [(string) $first->id, (string) $second->id],
        );
        $this->assertSame(200, $winner['status']);
        $this->assertSame($secondStatus, $loser['status']);
        if ($errorCode !== null) {
            $this->assertSame($errorCode, $loser['body']['error']['code']);
        }
        $this->assertSame('AWAITING_PAYMENT', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->payment_due_at);
        if ($scenario === 'overlap') {
            $this->assertSame($beforeSecond, $second->fresh()->getAttributes());
            $this->assertNull($second->fresh()->payment_due_at);
        } else {
            $this->assertSame('AWAITING_PAYMENT', $second->fresh()->status);
            $this->assertNotNull($second->fresh()->payment_due_at);
        }
        $accepted = $secondStatus === 200 ? 2 : 1;
        $this->assertDatabaseCount('booking_status_events', $initialEvents + $accepted);
        $this->assertSame($accepted, DB::table('booking_status_events')->where('to_status', 'AWAITING_PAYMENT')->count());
        if ($scenario === 'same_booking') {
            $this->assertSame($beforeSecond['version'] + 1, $second->fresh()->version);
            $this->assertSame($winner['body']['payment_due_at'], $second->fresh()->payment_due_at->utc()->format('Y-m-d\TH:i:s.u\Z'));
        }
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
