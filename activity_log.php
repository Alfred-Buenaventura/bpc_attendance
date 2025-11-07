<?php
require_once 'config.php';
requireAdmin(); 

$db = db();


$activityLogs = [];

$limit = 25; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1; 
$offset = ($page - 1) * $limit;


$totalResult = $db->query("SELECT COUNT(*) as total FROM activity_logs");
$totalLogs = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($totalLogs / $limit);


$logQuery = "
    SELECT al.*, u.first_name, u.last_name 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($logQuery);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$logResult = $stmt->get_result();

if ($logResult) {
    $activityLogs = $logResult->fetch_all(MYSQLI_ASSOC);
}


$pageTitle = 'Activity Log';
$pageSubtitle = 'View all system and user activities';
include 'includes/header.php';
?>

<div class="main-body">
    <div class="card">
        <div class="card-header">
            <h3>Full Activity Log</h3>
        </div>
        <div class="card-body">
            <?php if (empty($activityLogs)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 2rem;">No activity found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Details</th>
                            <th>User</th>
                            <th>Time & Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['description']) ?></td>
                                <td><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? 'System')) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div style="margin-top: 1.5rem; display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-secondary">&larr; Previous</a>
                    <?php endif; ?>

                    <span style="color: var(--gray-600); font-size: 0.9rem;">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-secondary">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
             <div style="text-align: left; margin-top: 1rem;">
                 <a href="index.php" class="btn btn-sm btn-secondary">&larr; Back to Dashboard</a>
             </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>