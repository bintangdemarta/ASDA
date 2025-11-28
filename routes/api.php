<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function ()
{
    // Guest Route
    Route::middleware('guest')->group(function ()
    {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [RegisteredUserController::class, 'store']);

        // TNI-specific routes
        Route::post('tni/register', [TniAuthController::class, 'register']);
        Route::post('tni/verify-details', [TniAuthController::class, 'verifyTniDetails']);
        Route::get('tni/check-member-number/{memberNumber}', [TniAuthController::class, 'checkMemberNumber']);
    });

    // Api Route with token
    Route::middleware('auth:api')->get('/user', function (Request $request)
    {
        return new UserResource($request->user());
    });

    // Api Route with sanctum
    Route::middleware(['auth:sanctum', 'verified'])->group(function ()
    {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);

        // Customer dashboard routes
        Route::prefix('customer')->group(function ()
        {
            Route::get('dashboard', [CustomerDashboardController::class, 'index']);
            Route::get('policies', [CustomerDashboardController::class, 'policies']);
            Route::get('claims', [CustomerDashboardController::class, 'claims']);
            Route::get('conversations', [CustomerDashboardController::class, 'conversations']);
            Route::get('calls', [CustomerDashboardController::class, 'calls']);
            Route::get('policies/{id}', [CustomerDashboardController::class, 'policyDetail']);
            Route::get('claims/{id}', [CustomerDashboardController::class, 'claimDetail']);
        });

        // AI chat routes
        Route::prefix('ai')->group(function ()
        {
            Route::post('chat', [AiChatController::class, 'chat']);
            Route::get('history', [AiChatController::class, 'history']);
        });

        // Claim status routes
        Route::prefix('claims')->group(function ()
        {
            Route::get('/', [ClaimStatusController::class, 'index']);
            Route::post('/', [ClaimStatusController::class, 'store']);
            Route::get('/{id}', [ClaimStatusController::class, 'show']);
            Route::put('/{id}/status', [ClaimStatusController::class, 'updateStatus']);
            Route::get('/search/{claimNumber}', [ClaimStatusController::class, 'searchByClaimNumber']);
            Route::get('/{id}/timeline', [ClaimStatusController::class, 'timeline']);
        });

        // Call scheduling routes
        Route::prefix('calls')->group(function ()
        {
            Route::get('/', [CallScheduleController::class, 'index']);
            Route::post('/', [CallScheduleController::class, 'store']);
            Route::get('/{id}', [CallScheduleController::class, 'show']);
            Route::put('/{id}', [CallScheduleController::class, 'update']);
            Route::delete('/{id}', [CallScheduleController::class, 'cancel']);
            Route::post('/{id}/start', [CallScheduleController::class, 'start']);
            Route::post('/{id}/end', [CallScheduleController::class, 'end']);
            Route::get('/upcoming', [CallScheduleController::class, 'upcoming']);
            Route::get('/admin/{adminId}/available-slots/{date}', [CallScheduleController::class, 'availableTimeSlots']);
        });

        // Admin management routes
        Route::prefix('admin')->group(function ()
        {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('claims', [AdminController::class, 'getAllClaims']);
            Route::get('conversations/escalated', [AdminController::class, 'getEscalatedConversations']);
            Route::get('calls', [AdminController::class, 'getScheduledCallsForAdmin']);
            Route::post('conversations/{id}/assign', [AdminController::class, 'assignConversation']);
            Route::put('conversations/{id}/resolve', [AdminController::class, 'updateConversationResolution']);
            Route::get('reports', [AdminController::class, 'getReports']);
            Route::get('users', [AdminController::class, 'getUsers']);
        });

        // Notification routes
        Route::prefix('notifications')->group(function ()
        {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'delete']);
            Route::post('/send', [NotificationController::class, 'sendNotification']);
        });

        // Security routes
        Route::prefix('security')->group(function ()
        {
            Route::get('/', [SecurityController::class, 'securityInfo']);
            Route::post('/password/change', [SecurityController::class, 'changePassword']);
            Route::post('/2fa/enable', [SecurityController::class, 'enableTwoFactorAuth']);
            Route::post('/2fa/disable', [SecurityController::class, 'disableTwoFactorAuth']);
            Route::get('/activity', [SecurityController::class, 'accountActivity']);
            Route::get('/compliance', [SecurityController::class, 'complianceStatus']);
            Route::get('/audit', [SecurityController::class, 'securityAudit']);
        });
    });
});
