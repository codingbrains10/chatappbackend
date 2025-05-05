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
        Broadcast::routes(['middleware' => ['auth:api']]);
        Broadcast::channel('calls.{userId}', function ($user, $userId) {
            return (int) $user->id === (int) $userId;
        });

        require base_path('routes/channels.php');
    }
}
