<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     *
     * Retourne :
     * - unread_total : nombre total de notifications non lues
     * - per_module   : stats par module (total / unread)
     * - items        : liste des dernières notifications (max 50)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // On limite à 50 dernières notifs
        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $unreadTotal = $notifications->whereNull('read_at')->count();

        // Regroupement par module pour les badges
        $perModule = $notifications
            ->groupBy('module')
            ->map(function ($group) {
                return [
                    'total'  => $group->count(),
                    'unread' => $group->whereNull('read_at')->count(),
                ];
            })
            ->toArray();

        return response()->json([
            'unread_total' => $unreadTotal,
            'per_module'   => $perModule,
            'items'        => $notifications->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'module'     => $n->module,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'url'        => $n->url,
                    'read'       => !is_null($n->read_at),
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * POST /api/notifications/mark-all-read
     *
     * Marque toutes les notifications du user comme lues.
     * (Tu peux la garder au cas où, même si le front ne l’utilise plus.)
     */
    public function markAllRead(Request $request)
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Notifications marquées comme lues.',
        ]);
    }

    /**
     * POST /api/notifications/mark-module
     *
     * Body JSON : { "module": "stock" }
     * Marque comme lues toutes les notifications d’un module pour ce user.
     */
    public function markByModule(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'module' => 'required|string',
        ]);

        $count = Notification::where('user_id', $user->id)
            ->where('module', $validated['module'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'status'  => 'ok',
            'updated' => $count,
        ]);
    }

    /**
     * POST /api/notifications/{notification}/read
     *
     * Marque UNE notification comme lue.
     */
    public function markOneRead(Request $request, Notification $notification)
    {
        $user = $request->user();

        // Sécurité simple : l'utilisateur ne peut manipuler que ses propres notifs
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'message' => 'Accès interdit à cette notification.',
            ], 403);
        }

        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
