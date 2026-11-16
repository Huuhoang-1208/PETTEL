<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $notifications = $request->user()->notifications()->latest()->paginate($perPage);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (DatabaseNotification $notification): array => [
                'id' => (string) $notification->id,
                'type' => $notification->data['type'] ?? class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString(),
            ])->values(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
            'links' => [
                'next' => $notifications->nextPageUrl(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $request->user()->notifications()->whereKey($id)->first();

        abort_if($notification === null, 404);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json(['data' => ['id' => (string) $notification->id, 'read_at' => $notification->fresh()->read_at?->toISOString()]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['updated' => true]]);
    }
}
