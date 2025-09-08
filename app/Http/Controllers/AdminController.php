<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        // Get site statistics
        $stats = [
            'total_users' => User::count(),
            'total_chats' => Chat::count(),
            'total_messages' => Message::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'new_chats_this_month' => Chat::whereMonth('created_at', now()->month)->count(),
            'total_courses' => Course::count(),
            'active_courses' => Course::where('is_active', true)->count(),
        ];

        // Get recent users
        $recent_users = User::latest()->take(10)->get(['id', 'name', 'email', 'created_at']);

        // Get exam types from chats
        $exam_stats = Chat::select('exam_type', DB::raw('count(*) as count'))
            ->whereNotNull('exam_type')
            ->groupBy('exam_type')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recent_users' => $recent_users,
            'exam_stats' => $exam_stats,
        ]);
    }

    public function coursesIndex()
    {
        $courses = Course::ordered()->get();

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function coursesCreate()
    {
        return Inertia::render('Admin/Courses/Create');
    }

    public function coursesStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_type' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'badge_color' => 'nullable|string|max:255',
            'full_description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        Course::create($request->all());

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course created successfully.');
    }

    public function coursesEdit(Course $course)
    {
        return Inertia::render('Admin/Courses/Edit', [
            'course' => $course,
        ]);
    }

    public function coursesUpdate(Request $request, Course $course)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_type' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'badge_color' => 'nullable|string|max:255',
            'full_description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $course->update($request->all());

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course updated successfully.');
    }

    public function coursesDestroy(Course $course)
    {
        $course->delete();

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course deleted successfully.');
    }

    public function usersIndex(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (in_array($sort, ['name', 'email', 'created_at'])) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        }

        $users = $query->paginate(20)->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toDateTimeString(),
                'subscription_status' => $user->subscriptionStatus(),
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'users' => $users,
        ]);
    }

    public function reportsIndex()
    {
        // Basic KPIs for reports page; extend as needed
        $kpis = [
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'messages_last_30d' => Message::where('created_at', '>=', now()->subDays(30))->count(),
            'chats_last_30d' => Chat::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        // Example time series for messages per day (last 14 days)
        $messageSeries = Message::selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', ">=", now()->subDays(14))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return Inertia::render('Admin/Reports/Index', [
            'kpis' => $kpis,
            'message_series' => $messageSeries,
        ]);
    }
}
