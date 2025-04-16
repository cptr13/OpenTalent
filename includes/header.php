<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OpenTalent ATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container-fluid {
            max-width: 100%;
        }
        .table {
            width: 100%;
            table-layout: auto;
        }
        .nav-user-email {
            color: #bbb;
            font-weight: 500;
            margin-right: 1rem;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">OpenTalent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="candidates.php">Candidates</a></li>
                <li class="nav-item"><a class="nav-link" href="jobs.php">Jobs</a></li>
                <li class="nav-item"><a class="nav-link" href="clients.php">Clients</a></li>
                <li class="nav-item"><a class="nav-link" href="contacts.php">Contacts</a></li>
                <li class="nav-item"><a class="nav-link" href="applications.php">Applications</a></li>
                <li class="nav-item"><a class="nav-link" href="notes.php">Recent Activities</a></li>
                <li class="nav-item"><a class="nav-link" href="change_password.php">Change Password</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['email'])): ?>
                <span class="nav-user-email"><?= htmlspecialchars($_SESSION['email']) ?></span>
            <?php endif; ?>


            <form class="d-flex me-2" method="GET" action="search.php" role="search">
                <input class="form-control me-2" type="search" name="q" placeholder="Search..." aria-label="Search" required>
                <button class="btn btn-outline-light" type="submit">Go</button>
            </form>
            
<?php if (isset($_SESSION['logged_in'])): ?>
    <ul class="navbar-nav me-3">
        <li class="nav-item">
            <a class="nav-link text-light" href="profile.php">My Profile</a>
        </li>
    </ul>
<?php endif; ?>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-danger" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
