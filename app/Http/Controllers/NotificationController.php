<?php

namespace App\Http\Controllers;

use App\Models\NcrNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = NcrNotification::with('report')->where('user_id', $request->user()->id)->latest('created_at')->paginate(20);
        return view('notifications.index', compact('notifications'));
    }
    public function read(Request $request, NcrNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->update(['is_read' => true, 'read_at' => now()]);
        return redirect()->route('notifications.index');
    }
    public function readAll(Request $request)
    {
        NcrNotification::where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
        return redirect()->route('notifications.index');
    }
}
