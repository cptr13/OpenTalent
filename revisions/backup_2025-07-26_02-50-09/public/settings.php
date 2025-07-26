<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 mt-4">
    <h2 class="mb-4">Settings</h2>

    <div class="row g-3">
        <!-- Profile Settings -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">Profile Info</h5>
                        <p class="card-text">View and update your name, title, phone, and profile picture.</p>
                    </div>
                    <div>
                        <a href="profile.php" class="btn btn-outline-primary me-2">View Profile</a>
                        <a href="edit_profile.php" class="btn btn-outline-success">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">Security</h5>
                        <p class="card-text">Update your password to keep your account secure.</p>
                    </div>
                    <div>
                        <a href="change_password.php" class="btn btn-outline-danger">Change Password</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">Activity Log</h5>
                        <p class="card-text">See recent actions you've taken in the system.</p>
                    </div>
                    <div>
                        <a href="activity_log.php" class="btn btn-outline-secondary disabled">Coming Soon</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Picture Upload -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">Profile Photo</h5>
                        <p class="card-text">Upload and crop a profile photo for your account.</p>
                    </div>
                    <div>
                        <a href="#" class="btn btn-outline-secondary disabled">Coming Soon</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
