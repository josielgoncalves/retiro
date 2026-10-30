<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return true;
    }

    public function managePayments(User $user, Enrollment $enrollment): bool
    {
        return true;
    }

    public function manageAccessLink(User $user, Enrollment $enrollment): bool
    {
        return true;
    }
}
