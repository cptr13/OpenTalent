<?php
session_start();

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$success = [];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $file = $_FILES['excel_file']['tmp_name'];
        if (!is_uploaded_file($file)) {
            throw new RuntimeException('No file uploaded or upload failed.');
        }

        $spreadsheet = IOFactory::load($file);
        $sheet       = $spreadsheet->getActiveSheet();
        // Keep empty cells; 1-based rows; columns keyed A,B,...
        $rows        = $sheet->toArray(null, true, true, true);

        if (!$rows || count($rows) < 2) {
            throw new RuntimeException('The file appears to be empty.');
        }

        // ---- Header normalization -------------------------------------------------
        // Map many possible header labels to canonical keys we use in $row[]
        $aliases = [
          // person (explicit)
          'first name'        => 'first_name',
          'firstname'         => 'first_name',
          'last name'         => 'last_name',
          'lastname'          => 'last_name',
          'email'             => 'email',
          'email address'     => 'email',
          'phone'             => 'phone',
          'mobile'            => 'phone_mobile',
          'mobile phone'      => 'phone_mobile',
          'title'             => 'title',
          'job title'         => 'title',
          'department'        => 'department',
          'linkedin'          => 'linkedin',
          'linkedin url'      => 'linkedin',

          // person (our CSV underscore + space variants)
          'contact_name'      => 'contact_name',
          'contact name'      => 'contact_name',
          'contact_title'     => 'title',
          'contact title'     => 'title',
          'contact_email'     => 'email',
          'contact email'     => 'email',
          'contact_phone'     => 'phone',
          'contact phone'     => 'phone',

          // company/client
          'company'                 => 'company',
          'company name'            => 'company',
          'client'                  => 'company',
          'client name'             => 'company',
          'client_name'             => 'company',
          'name'                    => 'company',
          'company phone'           => 'company_phone',
          'company phone number'    => 'company_phone',
          'company telephone'       => 'company_phone',
          'website'                 => 'company_url',
          'url'                     => 'company_url',
          'company url'             => 'company_url',
          'location'                => 'company_location',
          'city/state'              => 'company_location',
          'industry'                => 'company_industry',
          'account manager'         => 'account_manager',

          // NEW: allow various about-like headers to map to company_about
          'about'                   => 'company_about',
          'about company'           => 'company_about',
          'company about'           => 'company_about',
          'company snapshot'        => 'company_about',
        ];

        // Build canonical header array (convert underscores to spaces first)
        $rawHeaders = array_values($rows[1] ?? $rows[0]);
        $headerRow  = array_map(function($h) {
            $k = strtolower(trim((string)$h));
            $k = str_replace('_', ' ', $k);
            return $k;
        }, $rawHeaders);

        $headers = [];
        foreach ($headerRow as $h) {
            $headers[] = $aliases[$h] ?? $h; // fallback: keep normalized header
        }

        // ---- Prepare statements ---------------------------------------------------
        $findClient = $pdo->prepare("SELECT id FROM clients WHERE name = ?");
        $insertClient = $pdo->prepare("
            INSERT INTO clients (name, phone, location, industry, url, about, created_at)
            VALUES (:name, :phone, :location, :industry, :url, :about, NOW())
        ");
        $updateClient = $pdo->prepare("
            UPDATE clients
               SET phone    = :phone,
                   location = :location,
                   industry = :industry,
                   url      = :url,
                   about    = :about,
                   updated_at = NOW()
             WHERE id = :id
        ");

        $insertContact = $pdo->prepare("
            INSERT INTO contacts (
                client_id, first_name, last_name, full_name, email, phone, phone_mobile,
                title, department, linkedin, contact_owner, is_primary_contact, created_at
            ) VALUES (
                :client_id, :first_name, :last_name, :full_name, :email, :phone, :phone_mobile,
                :title, :department, :linkedin, :contact_owner, :is_primary_contact, NOW()
            )
        ");

        // ---- Process data rows ----------------------------------------------------
        $rowCount = count($rows);
        for ($i = 2; $i <= $rowCount; $i++) {
            $raw = $rows[$i] ?? null;
            if (!$raw) continue;

            $values = array_values($raw);
            $row = [];
            foreach ($headers as $idx => $key) {
                $row[$key] = $values[$idx] ?? null;
            }

            // Extract canonical fields (trim everything)
            $first_name       = trim((string)($row['first_name'] ?? ''));
            $last_name        = trim((string)($row['last_name']  ?? ''));
            $email            = trim((string)($row['email']      ?? ''));
            $phone            = trim((string)($row['phone']      ?? ''));
            $phone_mobile     = trim((string)($row['phone_mobile'] ?? ''));
            $title            = trim((string)($row['title']      ?? ''));
            $department       = trim((string)($row['department'] ?? ''));
            $linkedin         = trim((string)($row['linkedin']   ?? ''));

            $company          = trim((string)($row['company']            ?? ''));
            $company_phone    = trim((string)($row['company_phone']      ?? ''));
            $company_url      = trim((string)($row['company_url']        ?? ''));
            $company_location = trim((string)($row['company_location']   ?? ''));
            $company_industry = trim((string)($row['company_industry']   ?? ''));
            $account_manager  = trim((string)($row['account_manager']    ?? ''));

            // NEW: company about text (supports both mapped alias and raw 'about')
            $company_about    = trim((string)($row['company_about'] ?? ($row['about'] ?? '')));

            // If only a single contact_name was provided, split into first/last
            if (empty($first_name) && empty($last_name) && !empty($row['contact_name'])) {
                $cn = trim((string)$row['contact_name']);
                if ($cn !== '') {
                    $parts = preg_split('/\s+/', $cn);
                    if (count($parts) === 1) {
                        $first_name = $parts[0];
                    } else {
                        $last_name  = array_pop($parts);
                        $first_name = implode(' ', $parts);
                    }
                }
            }

            if ($company === '') {
                $errors[] = "Row $i error: Missing company name.";
                continue;
            }

            try {
                // --- Find or create client --------------------------------------
                $findClient->execute([$company]);
                $client = $findClient->fetch(PDO::FETCH_ASSOC);

                if (!$client) {
                    $insertClient->execute([
                        ':name'     => $company,
                        ':phone'    => $company_phone ?: null,
                        ':location' => $company_location ?: null,
                        ':industry' => $company_industry ?: null,
                        ':url'      => $company_url ?: null,
                        ':about'    => $company_about ?: null,
                    ]);
                    $client_id = (int)$pdo->lastInsertId();
                    $success[] = "Client created: $company";
                } else {
                    $client_id = (int)$client['id'];
                    // Light update with provided non-empty values (still passing null for blanks)
                    $updateClient->execute([
                        ':phone'    => $company_phone    ?: null,
                        ':location' => $company_location ?: null,
                        ':industry' => $company_industry ?: null,
                        ':url'      => $company_url      ?: null,
                        ':about'    => $company_about    ?: null,
                        ':id'       => $client_id,
                    ]);
                    $success[] = "Client exists: $company";
                }

                // --- Insert contact (if meaningful person info exists) ----------
                if ($client_id && ($first_name || $last_name || $email || $linkedin || $phone || $phone_mobile)) {
                    $full_name     = trim("$first_name $last_name");
                    $contact_owner = $_SESSION['user']['full_name'] ?? 'System';

                    $insertContact->execute([
                        ':client_id'          => $client_id,
                        ':first_name'         => $first_name ?: null,
                        ':last_name'          => $last_name ?: null,
                        ':full_name'          => $full_name ?: null,
                        ':email'              => $email ?: null,
                        ':phone'              => $phone ?: null,
                        ':phone_mobile'       => $phone_mobile ?: null,
                        ':title'              => $title ?: null,
                        ':department'         => $department ?: null,
                        ':linkedin'           => $linkedin ?: null,
                        ':contact_owner'      => $contact_owner ?: null,
                        ':is_primary_contact' => 0,
                    ]);

                    $success[] = "Contact added: " . ($full_name ?: '[Unnamed]');
                } elseif (!$client_id) {
                    $errors[] = "Row $i error: Could not determine client ID for $company.";
                }
            } catch (Throwable $e) {
                $errors[] = "Row $i error: " . $e->getMessage();
            }
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2>Import Clients & Contacts</h2>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="input-group">
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <button type="submit" class="btn btn-success">Upload and Import</button>
        </div>
    </form>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Success:</strong>
            <ul><?php foreach ($success as $msg): ?><li><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Errors:</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
