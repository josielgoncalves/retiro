<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(string $action, Model $entity, ?array $before = null, ?array $after = null, ?User $user = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id ?? Request::user()?->id,
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'before_data' => $before,
            'after_data' => $after,
            'ip_address' => Request::ip(),
        ]);
    }
}
