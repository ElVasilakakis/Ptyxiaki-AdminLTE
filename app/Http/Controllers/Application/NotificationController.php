<?php

namespace App\Http\Controllers\Application;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user
     */
    public function index(Request $request)
    {
        $query = Notification::forUser(Auth::id())
            ->with(['sensor.device', 'device'])
            ->orderBy('created_at', 'desc');

        // Filter by read status if specified
        if ($request->has('unread_only') && $request->unread_only) {
            $query->unread();
        }

        // Limit results
        $limit = $request->get('limit', 10);
        $notifications = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'severity' => $notification->severity,
                    'severity_color' => $notification->severity_color,
                    'severity_icon' => $notification->severity_icon,
                    'is_read' => $notification->is_read,
                    'time_ago' => $notification->time_ago,
                    'created_at' => $notification->created_at->toISOString(),
                    'device_name' => $notification->device ? $notification->device->name : null,
                    'sensor_name' => $notification->sensor ? $notification->sensor->name : null,
                    'data' => $notification->data,
                ];
            }),
            'unread_count' => Notification::forUser(Auth::id())->unread()->count()
        ]);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount()
    {
        $count = Notification::forUser(Auth::id())->unread()->count();
        
        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id)
    {
        $notification = Notification::forUser(Auth::id())->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        Notification::forUser(Auth::id())->unread()->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        $notification = Notification::forUser(Auth::id())->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Clear all read notifications
     */
    public function clearRead()
    {
        Notification::forUser(Auth::id())->where('is_read', true)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Read notifications cleared'
        ]);
    }
}
