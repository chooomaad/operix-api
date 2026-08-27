<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\NotificationSent;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = AppNotification::forUser($user->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $unreadCount = AppNotification::forUser($user->id)->whereNull('read_at')->count();

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = AppNotification::forUser($request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'body'    => 'required|string|max:2000',
            'type'    => 'in:info,success,warning,alert',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $sender = $request->user();
        $type   = $validated['type'] ?? 'info';
        $data   = [
            'title'        => $validated['title'],
            'body'         => $validated['body'],
            'type'         => $type,
            'sent_by'      => $sender->id,
            'sent_by_name' => $sender->name,
        ];

        // Le modèle User ne porte PAS le global scope tenant (les identités
        // d'authentification sont globales). Le filtrage doit donc être explicite
        // ici, sinon un envoi collectif touche les utilisateurs de TOUTES les
        // entreprises — et, une fois la diffusion branchée, leur pousse le message
        // en temps réel : le canal `user.{id}` n'autorise que sur l'identifiant,
        // pas sur le tenant.
        $recipients = User::query()
            ->where('tenant_id', $sender->tenant_id)
            ->where('is_active', true);

        if (! empty($validated['user_id'])) {
            // `exists:users,id` ne suffit pas : il accepte l'identifiant d'un
            // utilisateur d'une autre entreprise. On restreint au tenant courant.
            $target = (clone $recipients)->find($validated['user_id']);

            if (! $target) {
                return response()->json([
                    'message' => 'Destinataire introuvable.',
                ], 404);
            }

            $targets = collect([$target]);
        } else {
            $targets = $recipients->get();
        }

        $created = [];

        foreach ($targets as $recipient) {
            $notification = AppNotification::create([
                'id'              => Str::uuid(),
                'type'            => $type,
                'notifiable_type' => User::class,
                'notifiable_id'   => $recipient->id,
                'data'            => $data,
            ]);

            $created[] = $notification;

            // Diffusion temps réel vers le canal privé du destinataire. L'évènement
            // implémente ShouldBroadcast : il part par la file d'attente et ne
            // ralentit donc pas la réponse HTTP.
            NotificationSent::dispatch($notification, $recipient->id);
        }

        return response()->json([
            'message' => count($created) . ' notification(s) envoyée(s).',
            'count'   => count($created),
        ], 201);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notif = AppNotification::forUser($request->user()->id)->findOrFail($id);
        $notif->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = AppNotification::forUser($request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => "{$count} notification(s) marquée(s) comme lues."]);
    }

    public function destroy(string $id): JsonResponse
    {
        AppNotification::findOrFail($id)->delete();
        return response()->json(['message' => 'Notification supprimée.']);
    }
}
