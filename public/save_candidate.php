<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'secondary_email' => $_POST['secondary_email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'mobile' => $_POST['mobile'] ?? '',
        'website' => $_POST['website'] ?? '',
        'street' => $_POST['street'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'zip' => $_POST['zip'] ?? '',
        'country' => $_POST['country'] ?? '',
        'experience' => $_POST['experience'] ?? '',
        'current_job' => $_POST['current_job'] ?? '',
        'current_salary' => $_POST['current_salary'] ?? '',
        'expected_salary' => $_POST['expected_salary'] ?? '',
        'skills' => $_POST['skills'] ?? '',
        'skype_id' => $_POST['skype_id'] ?? '',
        'twitter' => $_POST['twitter'] ?? '',
        'linkedin' => $_POST['linkedin'] ?? '',
        'facebook' => $_POST['facebook'] ?? '',
        'qualification' => $_POST['qualification'] ?? '',
        'employer' => $_POST['employer'] ?? '',
        'additional_info' => $_POST['additional_info'] ?? '',
        'status' => $_POST['status'] ?? 'New',
        'owner' => $_POST['owner'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];

    try {
        $sql = "INSERT INTO candidates (
                    first_name, last_name, email, secondary_email, phone, mobile, website,
                    street, city, state, zip, country,
                    experience, current_job, current_salary, expected_salary, skills,
                    skype_id, twitter, linkedin, facebook,
                    qualification, employer, additional_info,
                    status, owner, created_at
                ) VALUES (
                    :first_name, :last_name, :email, :secondary_email, :phone, :mobile, :website,
                    :street, :city, :state, :zip, :country,
                    :experience, :current_job, :current_salary, :expected_salary, :skills,
                    :skype_id, :twitter, :linkedin, :facebook,
                    :qualification, :employer, :additional_info,
                    :status, :owner, :created_at
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        header("Location: candidates.php?success=1");
        exit;

    } catch (PDOException $e) {
        echo "Error saving candidate: " . $e->getMessage();
    }
} else {
    echo "Invalid request.";
}
