<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!function_exists('login')) {
function login($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}
}

if (!function_exists('isLoggedIn')) {
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
}

if (!function_exists('isAdmin')) {
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}
}

if (!function_exists('logout')) {
function logout() {
    session_destroy();
}
}

if (!function_exists('addUser')) {
function buildFullName($parts) {
    $first = trim((string)($parts['first_name'] ?? ''));
    $middle = trim((string)($parts['middle_name'] ?? ''));
    $last = trim((string)($parts['last_name'] ?? ''));
    $suffix = trim((string)($parts['suffix'] ?? ''));

    $name = trim(implode(' ', array_filter([$first, $middle, $last], fn($v) => $v !== '')));

    if ($suffix !== '') {
        $name = trim($name . ', ' . $suffix);
    }

    return $name;
}

function addUser($data) {
    if (!isAdmin()) return false;
    $pdo = getDB();
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);

    $fullName = $data['full_name'] ?? buildFullName($data);

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, first_name, middle_name, last_name, suffix, birthdate, gender, email, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $data['username'],
        $hash,
        $data['role'] ?? 'user',
        $fullName,
        $data['first_name'] ?? null,
        $data['middle_name'] ?? null,
        $data['last_name'] ?? null,
        $data['suffix'] ?? null,
        !empty($data['birthdate']) ? $data['birthdate'] : null,
        $data['gender'] ?? null,
        $data['email'] ?? null,
        $data['phone'] ?? null,
    ]);
}

}

// Admin update user
if (!function_exists('updateUser')) {
function updateUser($id, $data) {
    if (!isAdmin()) return false;

    $pdo = getDB();

    $fields = [
        'username' => $data['username'],
        'role' => $data['role'] ?? 'user',
        'full_name' => $data['full_name'] ?? buildFullName($data),
        'first_name' => $data['first_name'] ?? null,
        'middle_name' => $data['middle_name'] ?? null,
        'last_name' => $data['last_name'] ?? null,
        'suffix' => $data['suffix'] ?? null,
        'birthdate' => !empty($data['birthdate']) ? $data['birthdate'] : null,
        'gender' => $data['gender'] ?? null,
        'email' => $data['email'],
        'phone' => $data['phone']
    ];


    // If password provided, update password_hash too
    $hasPassword = isset($data['password']) && trim((string)$data['password']) !== '';

    if ($hasPassword) {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role = ?, full_name = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, birthdate = ?, gender = ?, email = ?, phone = ? WHERE id = ?");
        return $stmt->execute([
            $fields['username'],
            $hash,
            $fields['role'],
            $fields['full_name'],
            $fields['first_name'],
            $fields['middle_name'],
            $fields['last_name'],
            $fields['suffix'],
            $fields['birthdate'],
            $fields['gender'],
            $fields['email'],
            $fields['phone'],
            $id
        ]);
    }

    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, full_name = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, birthdate = ?, gender = ?, email = ?, phone = ? WHERE id = ?");
    return $stmt->execute([
        $fields['username'],
        $fields['role'],
        $fields['full_name'],
        $fields['first_name'],
        $fields['middle_name'],
        $fields['last_name'],
        $fields['suffix'],
        $fields['birthdate'],
        $fields['gender'],
        $fields['email'],
        $fields['phone'],
        $id
    ]);

}
}

// Admin delete user
if (!function_exists('deleteUser')) {
function deleteUser($id) {
    if (!isAdmin()) return false;

    // Prevent deleting current admin account (basic safety)
    if (isset($_SESSION['user_id']) && (int)$id === (int)$_SESSION['user_id']) {
        return false;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
}
}

if (!function_exists('getCurrentUser')) {
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
}
?>


