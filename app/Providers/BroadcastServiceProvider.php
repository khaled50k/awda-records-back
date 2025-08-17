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

        // Custom Pusher configuration to handle SSL issues
        $this->app->singleton('pusher', function ($app) {
            $config = config('broadcasting.connections.pusher');
            
            return new \Pusher\Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                array_merge($config['options'], [
                    'curl_options' => [
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_CAINFO => null,
                    ],
                    'verify' => false,
                ])
            );
        });

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
