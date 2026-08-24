<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Notification;
use Modules\Core\Models\User;

/**
 * The SPA-facing API for a user's in-app notification tray: list the most recent
 * notifications (optionally scoped to one module), report the unread count, and
 * mark one or all as read. Every query is bound to the authenticated user through
 * their {@see User::notifications()} relation, so a user only ever sees their own.
 */
final class NotificationController extends Controller
{
    private const int DEFAULT_LIMIT = 20;

    private const int MAX_LIMIT = 50;

    /**
     * The most recent notifications for the tray, plus the unread count. `?scope=`
     * narrows to one module (the SPA passes its own module slug).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $scope = $this->scope($request);
        $limit = min(max((int) $request->integer('limit', self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);

        $notifications = $user->notifications()->forModule($scope)->limit($limit)->get();

        return response()->json([
            'data' => $notifications->map(fn (Notification $notification): array => $this->present($notification))->all(),
            'meta' => ['unread' => $this->unread($user, $scope)],
        ]);
    }

    /**
     * The unread count, optionally scoped — cheap enough to poll for a badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => $this->unread($this->user($request), $this->scope($request))]]);
    }

    /**
     * Mark a single notification read. A notification that is not the caller's is a
     * 404, never a cross-user write.
     */
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $this->user($request)->notifications()->findOrFail($notification);
        $model->markAsRead();

        return response()->json(['data' => $this->present($model)]);
    }

    /**
     * Mark every (optionally scoped) unread notification read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $scope = $this->scope($request);

        $user->notifications()->forModule($scope)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['unread' => $this->unread($user, $scope)]]);
    }

    private function unread(User $user, ?string $scope): int
    {
        return $user->notifications()->forModule($scope)->whereNull('read_at')->count();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        return $request->user();
    }

    private function scope(Request $request): ?string
    {
        $scope = $request->query('scope');

        return is_string($scope) && $scope !== '' ? $scope : null;
    }

    /**
     * Flatten a stored notification into the tray's item shape: the identity and
     * read state around the producer's `data` contract (level, title, body, scope,
     * action, meta).
     *
     * @return array<string, mixed>
     */
    private function present(Notification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? $notification->type,
            'level' => $data['level'] ?? 'info',
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'scope' => $notification->module_name,
            'action' => $data['action'] ?? null,
            'meta' => $data['meta'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
