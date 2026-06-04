<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$fac_id = (int)($_GET['id'] ?? 0);
$name = trim($_GET['name'] ?? '');

$result = null;

// Primary Action: If a valid unique matched ID is provided, look it up strictly
if ($fac_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            u.fullname,
            u.email,
            u.profile_picture,
            f.department,
            f.fb_link
        FROM users u
        INNER JOIN faculties f ON u.user_id = f.user_id
        WHERE f.faculty_id = ? AND u.role = 'faculty'
        LIMIT 1
    ");
    $stmt->bind_param("i", $fac_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fallback Action: If no ID was provided, or the ID was zero/not found, fall back to String Matching
if (!$result && $name !== '') {
    
    // Normalize: collapse multiple spaces and remove common OCR artifacts
    $normalized = preg_replace('/\s+/', ' ', $name);

    // Primary search: full name LIKE match (with TRIM on DB side to catch stored whitespace)
    $stmt = $conn->prepare("
        SELECT
            u.fullname,
            u.email,
            u.profile_picture,
            f.department,
            f.fb_link
        FROM users u
        INNER JOIN faculties f ON u.user_id = f.user_id
        WHERE u.role = 'faculty'
          AND TRIM(u.fullname) LIKE ?
        LIMIT 1
    ");

    $search = '%' . $normalized . '%';
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fallback 1: try each individual word (catches OCR partial matches)
    if (!$result) {
        $parts = array_filter(explode(' ', $normalized), fn($p) => strlen($p) >= 3);

        foreach ($parts as $part) {
            $stmt2 = $conn->prepare("
                SELECT
                    u.fullname,
                    u.email,
                    u.profile_picture,
                    f.department,
                    f.fb_link
                FROM users u
                INNER JOIN faculties f ON u.user_id = f.user_id
                WHERE u.role = 'faculty'
                  AND TRIM(u.fullname) LIKE ?
                LIMIT 1
            ");
            $fallback = '%' . $part . '%';
            $stmt2->bind_param("s", $fallback);
            $stmt2->execute();
            $result = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            if ($result) {
                break;
            }
        }
    }

    // Fallback 2: strip punctuation and try again with normalized name
    if (!$result) {
        $stripped = preg_replace('/[^a-zA-Z\s]/', '', $normalized);
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped));

        if ($stripped !== '') {
            $stmt3 = $conn->prepare("
                SELECT
                    u.fullname,
                    u.email,
                    u.profile_picture,
                    f.department,
                    f.fb_link
                FROM users u
                INNER JOIN faculties f ON u.user_id = f.user_id
                WHERE u.role = 'faculty'
                  AND TRIM(REGEXP_REPLACE(u.fullname, '[^a-zA-Z ]', '')) LIKE ?
                LIMIT 1
            ");
            $strippedSearch = '%' . $stripped . '%';
            $stmt3->bind_param("s", $strippedSearch);
            $stmt3->execute();
            $result = $stmt3->get_result()->fetch_assoc();
            $stmt3->close();
        }
    }
}

// Final assertion structure output
if (!$result) {
    echo json_encode(['error' => 'Faculty not found']);
    exit();
}

echo json_encode([
    'fullname'        => $result['fullname'],
    'email'           => $result['email'],
    'profile_picture' => $result['profile_picture'] ?? null,
    'department'      => $result['department']       ?? '',
    'fb_link'         => $result['fb_link']          ?? '',
]);