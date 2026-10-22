<?php

use App\Models\AuditLogModel;

if (! function_exists('audit_log')) {
    /**
     * Records an important action in the audit trail (FR-13).
     *
     * @param string               $action  Dotted action name, e.g. "user.created".
     * @param array<string, mixed> $details Extra context. Never include passwords.
     * @param int|null             $userId  Acting user; defaults to the signed-in user.
     */
    function audit_log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
        ?int $userId = null,
    ): void {
        $request = service('request');

        model(AuditLogModel::class)->insert([
            'user_id'     => $userId ?? service('auth')->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip_address'  => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
        ]);
    }
}
