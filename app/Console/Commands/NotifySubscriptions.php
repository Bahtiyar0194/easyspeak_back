<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Раз в день: владельцам и администраторам школы — подписка скоро
 * закончится (за 7, 3, 1 день и в последний день) или уже истекла
 * (на следующий день после окончания). Каждый порог — один раз:
 * команда запускается раз в сутки и сравнивает точное число дней.
 */
class NotifySubscriptions extends Command
{
    protected $signature = 'subscriptions:notify';

    protected $description = 'Notify school managers about expiring or expired subscription';

    const EXPIRING_DAYS = [7, 3, 1, 0];

    public function handle(NotificationService $notifications)
    {
        $today = Carbon::today();

        School::whereNotNull('subscription_expiration_at')
            ->whereBetween('subscription_expiration_at', [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->addDays(max(self::EXPIRING_DAYS))->endOfDay(),
            ])
            ->chunkById(50, function ($schools) use ($notifications, $today) {
                foreach ($schools as $school) {
                    $end = Carbon::parse($school->subscription_expiration_at)->startOfDay();
                    $days = $today->diffInDays($end, false);

                    if (in_array($days, self::EXPIRING_DAYS, true)) {
                        $type = 'subscription_expiring';
                    } elseif ($days === -1) {
                        $type = 'subscription_expired';
                    } else {
                        continue;
                    }

                    $notifications->send(
                        $notifications->schoolManagerIds($school->school_id),
                        $type,
                        [
                            'days' => max(0, $days),
                            'expires_at' => $notifications->iso($school->subscription_expiration_at),
                        ],
                        '/dashboard/school'
                    );
                }
            }, 'school_id');

        return Command::SUCCESS;
    }
}
