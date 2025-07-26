<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- DEBUG: header.php loaded -->

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
            color: #ccc;
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
        <a class="navbar-brand" href="/OT2/public/index.php">OpenTalent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between" id="mainNavbar">
            <!-- LEFT SIDE NAV LINKS -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/OT2/public/candidates.php">Candidates</a></li>
                <li class="nav-item"><a class="nav-link" href="/OT2/public/jobs.php">Jobs</a></li>
                <li class="nav-item"><a class="nav-link" href="/OT2/public/clients.php">Clients</a></li>
                <li class="nav-item"><a class="nav-link" href="/OT2/public/contacts.php">Contacts</a></li>
                <li class="nav-item"><a class="nav-link" href="/OT2/public/associate.php">Associations</a></li>
                <li class="nav-item"><a class="nav-link" href="/OT2/public/notes.php">Recent Activities</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="/OT2/public/change_password.php">Change Password</a>
                </li>
                <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="/OT2/public/admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>

            <!-- RIGHT SIDE SEARCH + PROFILE + LOGOUT -->
            <ul class="navbar-nav align-items-center">
                <form class="d-flex me-3" method="GET" action="/OT2/public/search.php" role="search">
                    <input class="form-control me-2" type="search" name="q" placeholder="Search..." aria-label="Search" required>
                    <button class="btn btn-outline-light" type="submit">Go</button>
                </form>

                <?php if (isset($_SESSION['user'])): ?>
                    <li class="nav-item nav-user-email">
                        <?= htmlspecialchars($_SESSION['user']['email']) ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="/OT2/public/profile.php">My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/OT2/public/logout.php">Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
