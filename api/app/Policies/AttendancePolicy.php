<?php

namespace App\Policies;

use App\Models\AttendanceLog;
use App\Models\Employee;

class AttendancePolicy
{
    public function checkIn(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function checkOut(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function viewForEmployee(Employee $actor, Employee $target): bool
    {
        return $actor->isManager() || $actor->id === $target->id;
    }

    public function update(Employee $actor, AttendanceLog $attendanceLog): bool
    {
        return $actor->isManager() && (string) $actor->company_id === (string) $attendanceLog->company_id;
    }
}
