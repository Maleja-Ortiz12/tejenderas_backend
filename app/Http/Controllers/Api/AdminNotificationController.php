<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = AdminNotification::with('order', 'adminUser')
            ->where('admin_user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($notifications);
    }

    public function markAsRead(AdminNotification $notification)
    {
        $notification->update(['is_read' => true]);

        return response()->json($notification);
    }
}
