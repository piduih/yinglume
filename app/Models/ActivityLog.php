<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * ActivityLog Model
 * Audit logging for compliance and security.
 */
class ActivityLog
{
    public static function log(string $action, string $model = '', int $modelId = 0, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            $db = Database::getInstance();
            $db->insert(
                "INSERT INTO activity_logs (tenant_id, user_id, action, model, model_id, old_values, new_values, ip_address, user_agent, created_at)
                 VALUES (:tid, :uid, :act, :mod, :mid, :old, :new, :ip, :ua, :ca)",
                [
                    ':tid' => Auth::tenantId() ?: null,
                    ':uid' => Auth::id() ?: null,
                    ':act' => $action,
                    ':mod' => $model,
                    ':mid' => $modelId ?: null,
                    ':old' => $oldValues ? json_encode($oldValues) : null,
                    ':new' => $newValues ? json_encode($newValues) : null,
                    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    ':ca'  => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            // Silently fail logging so business operations are not blocked
        }
    }
}
