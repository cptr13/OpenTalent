<?php
require_once '../config/database.php';

$candidate_id = 7;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_content'])) {
    $note = trim($_POST['note_content']);
    if (!empty($note)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (candidate_id, content, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$candidate_id, $note]);
        } catch (PDOException $e) {
            echo "Error saving note: " . $e->getMessage();
        }
    }
}

// Get candidate
try {
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    $candidate = $stmt->fetch();

    echo "<h2>Candidate: " . htmlspecialchars($candidate['name'] ?? 'Not found') . "</h2>";
} catch (PDOException $e) {
    echo "DB Error (Candidate): " . $e->getMessage();
}

// Add Note Form
?>
<h3>Add Note</h3>
<form method="POST">
    <textarea name="note_content" rows="4" cols="50" placeholder="Write your note..."></textarea><br><br>
    <button type="submit">Save Note</button>
</form>

<?php
// Fetch notes
try {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE candidate_id = ? ORDER BY created_at DESC");
    $stmt->execute([$candidate_id]);
    $notes = $stmt->fetchAll();

    echo "<h3>Notes</h3>";
    if ($notes) {
        echo "<ul>";
        foreach ($notes as $note) {
            echo "<li>" . htmlspecialchars($note['content']) . " <small>(" . $note['created_at'] . ")</small></li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No notes found.</p>";
    }
} catch (PDOException $e) {
    echo "DB Error (Notes): " . $e->getMessage();
}
?>
