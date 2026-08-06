<?php

namespace App\Support;

final class BranchSupport
{
    /**
     * @return array<string, array{open:string,close:string,closed:bool}>
     */
    public static function defaultHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[$day] = [
                'open' => $day === 'sunday' ? '10:00' : '09:00',
                'close' => $day === 'sunday' ? '16:00' : '18:00',
                'closed' => $day === 'sunday',
            ];
        }

        return $hours;
    }
}
