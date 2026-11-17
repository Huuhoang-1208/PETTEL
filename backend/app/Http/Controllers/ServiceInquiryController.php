<?php

namespace App\Http\Controllers;

use App\Events\InquiryMessageSent;
use App\Models\Listing;
use App\Models\Pet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $page = $this->accessible($request)->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($thread) => $this->thread($thread)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->thread($this->accessible($request)->where('id', $id)->firstOrFail())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => ['required', 'integer', 'min:1'],
            'pet_id' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'body' => ['required', 'string', 'max:500', 'regex:/\S/u'],
            'request_key' => ['required', 'uuid'],
        ]);
        $listing = Listing::query()->with('caregiverProfile.user')->where('status', 'ACTIVE')
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('listing_change_requests')
                ->whereColumn('listing_change_requests.listing_id', 'listings.id')
                ->where('listing_change_requests.status', 'PENDING_REVIEW'))
            ->findOrFail($data['listing_id']);
        $profile = $listing->caregiverProfile;
        abort_unless($profile && $profile->user?->status === 'ACTIVE' && $profile->user->hasRole('CAREGIVER'), 404);
        abort_if((string) $profile->user_id === (string) $request->user()->id, 422, 'Bạn không thể nhắn tin cho chính mình.');
        $pet = isset($data['pet_id']) ? Pet::query()->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->findOrFail($data['pet_id']) : null;
        $context = [
            'pet_name' => $pet?->name,
            'pet_breed' => $pet?->breed,
            'pet_weight_kg' => $pet?->weight_kg,
            'service_type' => $listing->service_type,
            'start_at' => isset($data['start_at']) ? CarbonImmutable::parse($data['start_at'])->utc()->toISOString() : null,
            'end_at' => isset($data['end_at']) ? CarbonImmutable::parse($data['end_at'])->utc()->toISOString() : null,
        ];
        [$id, $messageId] = DB::transaction(function () use ($request, $data, $listing, $profile, $context) {
            // Serialize sends by this owner so repeated clicks reuse the same thread.
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $thread = DB::table('service_inquiries')->where('owner_id', $request->user()->id)->where('listing_id', $listing->id)->first();
            $id = $thread?->id ?? DB::table('service_inquiries')->insertGetId([
                'owner_id' => $request->user()->id,
                'caregiver_user_id' => $profile->user_id,
                'listing_id' => $listing->id,
                'listing_title' => $listing->title,
                'sitter_name' => $profile->display_name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $messageId = $this->saveMessage($request, (string) $id, $data, $context);

            return [(string) $id, $messageId];
        });

        InquiryMessageSent::dispatch($id, $messageId);

        return response()->json(['id' => $id], 201);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $this->accessible($request)->where('id', $id)->firstOrFail();
        $data = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,50'], 'cursor' => ['sometimes', 'string', 'max:512']]);
        $page = DB::table('inquiry_messages')->where('inquiry_id', $id)->orderByDesc('id')->cursorPaginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($message) => [
                'id' => (string) $message->id, 'sender_id' => (string) $message->sender_id,
                'body' => $message->body, 'created_at' => CarbonImmutable::parse($message->created_at, 'UTC')->toISOString(),
                'context' => $message->context_json ? json_decode($message->context_json, true) : null,
                'attachments' => [],
            ]),
            'links' => ['next' => $page->nextPageUrl()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, string $id): JsonResponse
    {
        $this->accessible($request)->where('id', $id)->firstOrFail();
        $data = $request->validate(['body' => ['required', 'string', 'max:2000', 'regex:/\S/u'], 'request_key' => ['required', 'uuid']]);
        $messageId = DB::transaction(function () use ($request, $id, $data) {
            DB::table('service_inquiries')->where('id', $id)->lockForUpdate()->firstOrFail();

            return $this->saveMessage($request, $id, $data);
        });

        InquiryMessageSent::dispatch($id, $messageId);

        return response()->json(['sent' => true], 201);
    }

    private function accessible(Request $request): Builder
    {
        return DB::table('service_inquiries')->where(fn (Builder $query) => $query
            ->where('owner_id', $request->user()->id)->orWhere('caregiver_user_id', $request->user()->id));
    }

    private function thread(object $thread): array
    {
        return ['id' => (string) $thread->id, 'listing_id' => (string) $thread->listing_id,
            'listing_title' => $thread->listing_title, 'sitter_name' => $thread->sitter_name];
    }

    private function saveMessage(Request $request, string $id, array $data, ?array $context = null): string
    {
        $hash = hash('sha256', json_encode([$data['body'], $context], JSON_THROW_ON_ERROR));
        $existing = DB::table('inquiry_messages')->where('inquiry_id', $id)->where('sender_id', $request->user()->id)->where('request_key', $data['request_key'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Mã gửi tin đã được sử dụng cho nội dung khác.');

            return (string) $existing->id;
        }
        $messageId = DB::table('inquiry_messages')->insertGetId([
            'inquiry_id' => $id, 'sender_id' => $request->user()->id,
            'body' => trim($data['body']), 'context_json' => $context ? json_encode($context, JSON_THROW_ON_ERROR) : null,
            'request_key' => $data['request_key'], 'payload_hash' => $hash, 'created_at' => now(),
        ]);
        DB::table('service_inquiries')->where('id', $id)->update(['updated_at' => now()]);

        return (string) $messageId;
    }
}
