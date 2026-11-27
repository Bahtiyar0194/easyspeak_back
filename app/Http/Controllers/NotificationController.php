<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;

/**
 * Уведомления в личном кабинете (колокольчик в шапке).
 * Каждый видит и отмечает только свои уведомления.
 */
class NotificationController extends Controller
{
    const PAGE_SIZE = 20;

    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    /**
     * Последние уведомления, новые сверху. before — created_at последнего
     * из уже показанных (для «Показать ещё»).
     */
    public function get(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'before' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = auth()->user();

        $query = $user->notifications()->latest();

        if ($request->filled('before')) {
            $query->where('created_at', '<', $request->before);
        }

        // Берём на одно больше — чтобы знать, есть ли ещё
        $items = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $items->count() > self::PAGE_SIZE;

        return response()->json([
            'items' => $items->take(self::PAGE_SIZE)->map(function ($notification) {
                $data = $notification->data;
                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? null,
                    'params' => $data['params'] ?? (object) [],
                    'link' => $data['link'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            })->values(),
            'has_more' => $hasMore,
            'unread' => $user->unreadNotifications()->count(),
        ], 200);
    }

    public function read(Request $request)
    {
        $user = auth()->user();
        $user->notifications()
            ->where('id', $request->notification_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['unread' => $user->unreadNotifications()->count()], 200);
    }

    public function read_all()
    {
        $user = auth()->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0], 200);
    }
}
