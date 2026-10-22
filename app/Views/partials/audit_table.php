<?php
/**
 * @var list<array<string, mixed>> $logs
 */
?>
<?php if ($logs === []): ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon('history') ?></span>
        <p>No activity recorded yet.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th scope="col">When</th>
                <th scope="col">User</th>
                <th scope="col">Action</th>
                <th scope="col">Record</th>
                <th scope="col">Details</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="nowrap muted"><?= esc(format_datetime($log['created_at'])) ?></td>
                    <td>
                        <?php if ($log['user_name'] !== null): ?>
                            <span class="person">
                                <?= avatar($log['user_name'], 'sm') ?>
                                <span class="person__name"><?= esc($log['user_name']) ?></span>
                            </span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif ?>
                    </td>
                    <td><code><?= esc($log['action']) ?></code></td>
                    <td class="nowrap">
                        <?php if ($log['entity_type'] !== null): ?>
                            <?= esc(ucfirst($log['entity_type'])) ?><?= $log['entity_id'] !== null ? ' #' . esc($log['entity_id']) : '' ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif ?>
                    </td>
                    <td class="details"><?= audit_details($log['details']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
