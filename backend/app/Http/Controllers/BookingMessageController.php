<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\BookingMessageAccess;
use App\Domain\Booking\Actions\StoreBookingMessage;
use App\Http\Resources\BookingMessageResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Normalizer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingMessageController extends Controller
{
    public function __construct(private BookingMessageAccess $access) {}

    public function index(Request $request, string $id): AnonymousResourceCollection
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $booking = $this->access->bookingForRead($actor, $id);
        $data = validator($request->query(), [
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'cursor' => ['sometimes', 'required', 'string', 'max:512', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ])->validate();

        $cursor = isset($data['cursor']) ? $this->decodeCursor($data['cursor']) : null;
        $messages = $booking->messages()->where('visibility', 'VISIBLE')
            ->with('attachments')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($data['per_page'] ?? 20), ['*'], 'cursor', $cursor);

        return BookingMessageResource::collection($messages)->withQuery(
            isset($data['per_page']) ? ['per_page' => $data['per_page']] : [],
        );
    }

    public function store(Request $request, string $id, StoreBookingMessage $storeMessage): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $this->access->bookingForSend($actor, $id, lock: false);
        $input = $request->all();
        if (array_diff(array_keys($input), ['body', 'attachments']) !== []) {
            throw ValidationException::withMessages(['body' => ['Only body and attachments are accepted.']]);
        }

        if (is_string($input['body'] ?? null)) {
            $normalized = Normalizer::normalize(Str::trim($input['body']), Normalizer::FORM_C);
            $input['body'] = $normalized === '' ? null : $normalized;
        }

        $data = validator($input, [
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
            'attachments' => ['sometimes', 'array', 'list', 'max:5'],
            'attachments.*' => ['required', 'file'],
        ])->validate();

        /** @var list<UploadedFile> $attachments */
        $attachments = $data['attachments'] ?? [];
        $message = $storeMessage->handle($actor, $id, $data['body'] ?? null, $attachments);

        return BookingMessageResource::make($message->load('attachments'))->response()->setStatusCode(201);
    }

    public function attachment(Request $request, string $id, string $messageId, ?string $mediaId = null): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $booking = $this->access->bookingForRead($actor, $id);
        $message = $booking->messages()->where('visibility', 'VISIBLE')->findOrFail($messageId);
        $attachments = $message->attachments();
        if ($mediaId !== null) {
            $attachments->where('media_objects.id', $mediaId);
        }
        $media = $attachments->firstOrFail();

        $stream = Storage::disk('local')->readStream($media->object_key);
        abort_unless(is_resource($stream), 404);

        return response()->stream(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function decodeCursor(string $encoded): Cursor
    {
        $cursor = Cursor::fromEncoded($encoded);
        $parameters = $cursor?->toArray() ?? [];

        if ($cursor === null || ! is_bool($parameters['_pointsToNextItems'] ?? null)
            || validator($parameters, [
                'created_at' => ['required', 'date_format:Y-m-d H:i:s.u,Y-m-d H:i:s'],
                'id' => ['required', 'regex:/\A[1-9][0-9]{0,19}\z/'],
            ])->fails()) {
            throw ValidationException::withMessages(['cursor' => ['The pagination cursor is invalid.']]);
        }

        return $cursor;
    }
}
