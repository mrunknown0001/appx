<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Carbon\Carbon;

class UpdateLastLogin
{
    /**
     * The event(s) the listener handles.
     *
     * @var array<int, class-string>
     */
    public $listen = [
        Login::class,
    ];

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $event->user->update([
            'last_login_at' => Carbon::now(),
        ]);
    }
}