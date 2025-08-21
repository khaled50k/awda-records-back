<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        // Channel for admin notifications - only admins can access
        Broadcast::channel('admin.notifications', function ($user) {
            return $user->isAdmin();
        });

        // Channel for user-specific notifications - users can only access their own
        Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
            return (int) $user->user_id === (int) $userId;
        });
    }
}
