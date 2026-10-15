<?php

use Illuminate\Support\Facades\Schedule;

/*
| Zamanlanmış görevler. Sunucuda tek cron satırı yeterlidir:
|   * * * * * cd /var/www/b2b && php artisan schedule:run >> /dev/null 2>&1
*/

// Haftalık vade hatırlatması (yalnızca hatırlatmayı açmış işletmeler).
Schedule::command('ledger:send-reminders')->weeklyOn(1, '09:00')->timezone('Europe/Istanbul')->withoutOverlapping();
