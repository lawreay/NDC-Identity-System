<?php
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/SettingsRepository.php';
require_once __DIR__ . '/app/StudentRepository.php';

echo "Starting seeding student ID assets and sample data...\n";

try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    echo "Unable to connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

$uploadRoot = __DIR__ . '/public/uploads';
$settingsUpload = $uploadRoot . '/settings';
$photosUpload = $uploadRoot . '/student_photos';

@mkdir($settingsUpload, 0777, true);
@mkdir($photosUpload, 0777, true);

// Create simple SVG logo
$logoPath = $settingsUpload . '/school_logo.svg';
$logoSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="120">
  <rect width="100%" height="100%" fill="#0b5ed7"/>
  <text x="50%" y="50%" font-family="Arial, Helvetica, sans-serif" font-size="32" fill="#fff" text-anchor="middle" dominant-baseline="middle">NDC</text>
</svg>
SVG;
file_put_contents($logoPath, $logoSvg);
$logoWebPath = 'uploads/settings/school_logo.svg';

// Create simple SVG signature placeholder
$signaturePath = $settingsUpload . '/authorized_signature.svg';
$signatureSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="80">
  <rect width="100%" height="100%" fill="#ffffff"/>
  <text x="10" y="50" font-family="Cursive, Arial" font-size="28" fill="#111">Authorized</text>
</svg>
SVG;
file_put_contents($signaturePath, $signatureSvg);
$signatureWebPath = 'uploads/settings/authorized_signature.svg';

// Create sample student photo SVGs
$sampleStudents = [
    ['first_name' => 'Moses', 'last_name' => 'Banda', 'student_number' => 'BND001'],
    ['first_name' => 'Aisha', 'last_name' => 'Phiri', 'student_number' => 'PHR002'],
    ['first_name' => 'John', 'last_name' => 'Doe', 'student_number' => 'DOE003'],
];

foreach ($sampleStudents as $i => $s) {
    $index = $i + 1;
    $file = $photosUpload . "/student_{$index}.svg";
    $initials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='400' height='500'><rect width='100%' height='100%' fill='#f3f4f6'/><text x='50%' y='50%' font-family='Arial' font-size='120' fill='#111' text-anchor='middle' dominant-baseline='middle'>{$initials}</text></svg>";
    file_put_contents($file, $svg);
    $sampleStudents[$i]['photo_path'] = 'uploads/student_photos/student_' . $index . '.svg';
}

// Save organization settings
$settingsRepo = new SettingsRepository($pdo);
$settings = [
    'organization_name' => 'NDC',
    'organization_address' => 'Ntcheu, Malawi',
    'organization_phone' => '+265 999 000 000',
    'organization_email' => 'info@ndc.edu',
    'organization_website' => 'https://ndc.edu',
    'organization_logo_path' => $logoWebPath,
    'principal_signature_name' => 'Registrar',
    'principal_signature_path' => $signatureWebPath,
];

try {
    $settingsRepo->save($settings);
    echo "Saved organization settings.\n";
} catch (Throwable $e) {
    echo "Unable to save settings: " . $e->getMessage() . "\n";
}

// Insert sample students if table exists
$studentRepo = new StudentRepository($pdo);

// Check if students table exists
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'students'")->fetchColumn();
} catch (Throwable $e) {
    $tableCheck = false;
}

if (!$tableCheck) {
    echo "Students table not found. Please create the 'students' table before running this seeder.\n";
    echo "You can create a minimal table with columns used by the system (id, student_number, photo_path, first_name, last_name, gender, date_of_birth, district, traditional_authority, village, phone_number, qualification, program, class_level, billing_category, status).\n";
    exit(0);
}

foreach ($sampleStudents as $s) {
    // check if a student with same student_number exists
    $stmt = $pdo->prepare('SELECT id FROM students WHERE student_number = :sn LIMIT 1');
    $stmt->execute([':sn' => $s['student_number']]);
    $exists = $stmt->fetchColumn();
    if ($exists) {
        echo "Student {$s['student_number']} already exists, skipping.\n";
        continue;
    }

    $insert = $pdo->prepare(
        'INSERT INTO students (student_number, photo_path, first_name, last_name, gender, date_of_birth, district, traditional_authority, village, phone_number, qualification, program, class_level, billing_category, status) VALUES (:student_number, :photo_path, :first_name, :last_name, :gender, :dob, :district, :ta, :village, :phone, :qualification, :program, :class_level, :billing_category, :status)'
    );

    $insert->execute([
        ':student_number' => $s['student_number'],
        ':photo_path' => $s['photo_path'],
        ':first_name' => $s['first_name'],
        ':last_name' => $s['last_name'],
        ':gender' => 'Not specified',
        ':dob' => null,
        ':district' => '',
        ':ta' => '',
        ':village' => '',
        ':phone' => '',
        ':qualification' => 'Certificate',
        ':program' => 'General Studies',
        ':class_level' => 'Level 1',
        ':billing_category' => 'Default',
        ':status' => 'Active',
    ]);

    echo "Inserted student {$s['student_number']}.\n";
}

echo "Seeding complete.\n";
exit(0);
