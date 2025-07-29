<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';

$jobs = [];

try {
    $stmt = $pdo->query("
        SELECT jobs.id, jobs.title, jobs.location, jobs.status, jobs.created_at, clients.id AS client_id, clients.name AS client_name
        FROM jobs
        LEFT JOIN clients ON jobs.client_id = clients.id
        ORDER BY jobs.created_at DESC
    ");
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading jobs: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Jobs</h2>
    <a href="add_job.php" class="btn btn-primary">+ Add Job</a>
</div>

<div style="overflow-x: auto;">
    <table class="table table-striped table-bordered resizable" id="resizableJobs">
        <thead class="table-dark">
            <tr>
                <th>Title</th>
                <th>Company</th>
                <th>Location</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($jobs)): ?>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td><a href="view_job.php?id=<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></a></td>
                        <td>
                            <?php if (!empty($job['client_id'])): ?>
                                <a href="view_client.php?id=<?= $job['client_id'] ?>">
                                    <?= htmlspecialchars($job['client_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($job['location']) ?></td>
                        <td><?= htmlspecialchars($job['status']) ?></td>
                        <td><?= htmlspecialchars($job['created_at']) ?></td>
                        <td>
                            <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">No jobs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    th {
        position: relative;
    }
    th .resizer {
        position: absolute;
        right: 0;
        top: 0;
        width: 5px;
        height: 100%;
        cursor: col-resize;
        user-select: none;
        z-index: 1;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('resizableJobs');
        const cols = table.querySelectorAll('th');

        cols.forEach(th => {
            const resizer = document.createElement('div');
            resizer.classList.add('resizer');
            th.appendChild(resizer);
            resizer.addEventListener('mousedown', initResize);
        });

        let startX, startWidth, currentCol;

        function initResize(e) {
            currentCol = e.target.parentElement;
            startX = e.clientX;
            startWidth = currentCol.offsetWidth;
            document.addEventListener('mousemove', resizeColumn);
            document.addEventListener('mouseup', stopResize);
        }

        function resizeColumn(e) {
            const width = startWidth + (e.clientX - startX);
            currentCol.style.width = width + 'px';
        }

        function stopResize() {
            document.removeEventListener('mousemove', resizeColumn);
            document.removeEventListener('mouseup', stopResize);
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
