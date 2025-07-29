<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, phone, status, created_at, owner FROM candidates ORDER BY created_at DESC");
    $candidates = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading candidates: " . $e->getMessage() . "</div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Candidates</h2>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                + Add
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="add_candidate.php">Add Manually</a></li>
                <li><a class="dropdown-item" href="parse_resume.php">Parse Resume</a></li>
                <li><a class="dropdown-item" href="bulk_upload.php">Bulk Upload Resumes</a></li>
                <li><a class="dropdown-item" href="import_candidates.php">Import from Excel</a></li>
            </ul>
        </div>
    </div>

    <?php if (count($candidates) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered draggable-table">
                <thead class="table-dark">
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Owner</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td>
                                <a href="view_candidate.php?id=<?= $candidate['id'] ?>">
                                    <?= htmlspecialchars($candidate['first_name']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($candidate['last_name']) ?></td>
                            <td><?= htmlspecialchars($candidate['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($candidate['phone'] ?? '') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($candidate['status'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($candidate['owner'] ?? '—') ?></td>
                            <td><?= date("Y-m-d", strtotime($candidate['created_at'] ?? '')) ?></td>
                            <td>
                                <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="delete_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this candidate?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No candidates found.</div>
    <?php endif; ?>
</div>

<script>
// Enable column resizing on tables with class="draggable-table"
document.querySelectorAll(".draggable-table").forEach(table => {
    const ths = table.querySelectorAll("th");

    ths.forEach(th => {
        const resizer = document.createElement("div");
        resizer.style.width = "5px";
        resizer.style.height = "100%";
        resizer.style.position = "absolute";
        resizer.style.top = 0;
        resizer.style.right = 0;
        resizer.style.cursor = "col-resize";
        resizer.style.userSelect = "none";

        resizer.addEventListener("mousedown", function (e) {
            const startX = e.pageX;
            const startWidth = th.offsetWidth;

            const onMouseMove = (e) => {
                const newWidth = startWidth + (e.pageX - startX);
                th.style.width = newWidth + "px";
            };

            const onMouseUp = () => {
                document.removeEventListener("mousemove", onMouseMove);
                document.removeEventListener("mouseup", onMouseUp);
            };

            document.addEventListener("mousemove", onMouseMove);
            document.addEventListener("mouseup", onMouseUp);
        });

        th.style.position = "relative";
        th.appendChild(resizer);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>

