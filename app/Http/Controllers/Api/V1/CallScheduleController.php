<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CallSchedule;
use App\Models\User;
use App\Enums\CallStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CallScheduleController extends Controller
{
    /**
     * Get all scheduled calls for the authenticated user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $calls = $user->callSchedules()
            ->with(['admin'])
            ->orderBy('scheduled_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $calls,
        ]);
    }

    /**
     * Get a specific scheduled call
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $call = $user->callSchedules()->with(['admin'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $call,
        ]);
    }

    /**
     * Create a new call schedule
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'admin_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'scheduled_at' => 'required|date|after:now',
        ]);

        // Check if admin is available at the requested time
        if ($request->admin_id) {
            $admin = User::findOrFail($request->admin_id);

            // Check if admin is an actual admin/user with consultation permissions
            if (!$admin->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not an admin or consultation officer.',
                ], 400);
            }

            // Check for admin availability (no overlapping appointments)
            $existingCall = CallSchedule::where('admin_id', $request->admin_id)
                ->where('status', 'scheduled')
                ->where(function ($query) use ($request) {
                    $query->whereBetween('scheduled_at', [
                        $request->scheduled_at,
                        date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' +1 hour'))
                    ])
                    ->orWhereBetween(date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' -1 hour')), [
                        DB::raw('scheduled_at'),
                        date('Y-m-d H:i:s', strtotime('scheduled_at +1 hour'))
                    ]);
                })
                ->first();

            if ($existingCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin is not available at the selected time.',
                ], 400);
            }
        }

        // If no admin specified, try to find an available admin
        if (!$request->admin_id) {
            $availableAdmin = User::role(['admin', 'superadmin', 'consultation_officer'])
                ->whereDoesntHave('scheduledCalls', function ($query) use ($request) {
                    $query->where('status', 'scheduled')
                        ->where(function ($subQuery) use ($request) {
                            $subQuery->whereBetween('scheduled_at', [
                                $request->scheduled_at,
                                date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' +1 hour'))
                            ])
                            ->orWhereBetween(date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' -1 hour')), [
                                DB::raw('scheduled_at'),
                                date('Y-m-d H:i:s', strtotime('scheduled_at +1 hour'))
                            ]);
                        });
                })
                ->first();

            if (!$availableAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No admins are available at the selected time. Please select another time slot.',
                ], 400);
            }

            $request->merge(['admin_id' => $availableAdmin->id]);
        }

        $call = CallSchedule::create([
            'user_id' => $user->id,
            'admin_id' => $request->admin_id,
            'title' => $request->title,
            'description' => $request->description,
            'scheduled_at' => $request->scheduled_at,
            'status' => CallStatus::SCHEDULED,
        ]);

        // In a real implementation, you would send notification to both user and admin
        // For now, we'll just return the created call

        return response()->json([
            'success' => true,
            'message' => 'Call scheduled successfully.',
            'data' => $call->load(['admin']),
        ], 201);
    }

    /**
     * Update a scheduled call
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $call = $user->callSchedules()->findOrFail($id);

        // Only allow updates if the call is still scheduled
        if ($call->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a call that is already in progress or completed.',
            ], 400);
        }

        $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string|max:1000',
            'scheduled_at' => 'sometimes|date|after:now',
            'admin_id' => 'sometimes|exists:users,id',
        ]);

        // Check admin availability if admin is being changed
        if ($request->admin_id && $request->admin_id != $call->admin_id) {
            $admin = User::findOrFail($request->admin_id);

            if (!$admin->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not an admin or consultation officer.',
                ], 400);
            }

            // Check for admin availability
            $existingCall = CallSchedule::where('admin_id', $request->admin_id)
                ->where('id', '!=', $call->id) // Exclude current call
                ->where('status', 'scheduled')
                ->where(function ($query) use ($request) {
                    $query->whereBetween('scheduled_at', [
                        $request->scheduled_at,
                        date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' +1 hour'))
                    ])
                    ->orWhereBetween(date('Y-m-d H:i:s', strtotime($request->scheduled_at . ' -1 hour')), [
                        DB::raw('scheduled_at'),
                        date('Y-m-d H:i:s', strtotime('scheduled_at +1 hour'))
                    ]);
                })
                ->first();

            if ($existingCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin is not available at the selected time.',
                ], 400);
            }
        }

        $call->update($request->only(['title', 'description', 'scheduled_at', 'admin_id']));

        return response()->json([
            'success' => true,
            'message' => 'Call updated successfully.',
            'data' => $call->load(['admin']),
        ]);
    }

    /**
     * Cancel a scheduled call
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $call = $user->callSchedules()->findOrFail($id);

        // Only allow cancellation if the call is still scheduled
        if ($call->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a call that is already in progress or completed.',
            ], 400);
        }

        $call->update(['status' => CallStatus::CANCELLED]);

        // In a real implementation, you would send notification to admin about cancellation
        // For now, we'll just return success

        return response()->json([
            'success' => true,
            'message' => 'Call cancelled successfully.',
        ]);
    }

    /**
     * Start a scheduled call (mark as in progress)
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $call = CallSchedule::findOrFail($id);

        // Check if the authenticated user is either the customer or the assigned admin
        if ($user->id !== $call->user_id && $user->id !== $call->admin_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Only allow starting if the call is scheduled
        if ($call->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Call cannot be started.',
            ], 400);
        }

        $call->update([
            'status' => CallStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Call started successfully.',
            'data' => $call->load(['admin']),
        ]);
    }

    /**
     * End a call and mark as completed
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function end(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $call = CallSchedule::findOrFail($id);

        // Check if the authenticated user is either the customer or the assigned admin
        if ($user->id !== $call->user_id && $user->id !== $call->admin_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Only allow ending if the call is in progress
        if ($call->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Call is not currently in progress.',
            ], 400);
        }

        $request->validate([
            'notes' => 'nullable|string|max:2000',
            'duration' => 'nullable|integer|min:1', // Duration in minutes
        ]);

        // Calculate duration if not provided
        $duration = $request->duration;
        if (!$duration && $call->started_at) {
            $duration = now()->diffInMinutes($call->started_at);
        }

        $call->update([
            'status' => CallStatus::COMPLETED,
            'ended_at' => now(),
            'duration' => $duration,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Call completed successfully.',
            'data' => $call->load(['admin']),
        ]);
    }

    /**
     * Get upcoming calls for the authenticated user
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upcoming(Request $request): JsonResponse
    {
        $user = $request->user();
        $calls = $user->callSchedules()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->with(['admin'])
            ->orderBy('scheduled_at', 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $calls,
        ]);
    }

    /**
     * Get available time slots for admin
     *
     * @param int $adminId
     * @param string $date
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function availableTimeSlots(int $adminId, string $date, Request $request): JsonResponse
    {
        $admin = User::findOrFail($adminId);

        if (!$admin->hasRole(['admin', 'superadmin', 'consultation_officer'])) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an admin or consultation officer.',
            ], 400);
        }

        // Assuming business hours from 9 AM to 5 PM
        $startTime = strtotime($date . ' 09:00:00');
        $endTime = strtotime($date . ' 17:00:00');

        // Get all scheduled calls for this admin on this date
        $bookedSlots = CallSchedule::where('admin_id', $adminId)
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        // Generate available time slots (30-minute intervals)
        $availableSlots = [];
        $current = $startTime;

        while ($current < $endTime) {
            $slotTime = date('Y-m-d H:i:s', $current);
            $slotEnd = date('Y-m-d H:i:s', $current + (30 * 60)); // 30 minutes later

            $isBooked = false;
            foreach ($bookedSlots as $booked) {
                $bookedStart = strtotime($booked->scheduled_at);
                $bookedEnd = $bookedStart + ($booked->duration ? $booked->duration * 60 : 3600); // Default to 1 hour if duration not set

                // Check if the slot overlaps with any existing booking
                if (!(strtotime($slotEnd) <= $bookedStart || $current >= $bookedEnd)) {
                    $isBooked = true;
                    break;
                }
            }

            if (!$isBooked) {
                $availableSlots[] = $slotTime;
            }

            $current += (30 * 60); // Move to next 30-minute slot
        }

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => $admin,
                'date' => $date,
                'available_slots' => $availableSlots,
            ],
        ]);
    }
}
