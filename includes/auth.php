<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

function login($username, $password, $conn) {

    $username = mysqli_real_escape_string($conn, $username);

    $query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['PASSWORD'])) {

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'   => $user['id'],
                'nama' => $user['nama_lengkap'],
                'role' => $user['role'],
            ];

            return true;
        }
    }

    return false;
}

function requireRole($role) {
    requireLogin();

    if (!isset($_SESSION['user']['role'])) {
        header("Location: login.php");
        exit;
    }

    $userRole = $_SESSION['user']['role'];

    // ✅ Jika role berbentuk array (multi-role)
    if (is_array($role)) {
        if (!in_array($userRole, $role)) {
            die("❌ Akses ditolak! Halaman ini khusus untuk role: " . implode(", ", $role));
        }
    } 
    // ✅ Jika role berbentuk string (single role)
    else {
        if ($userRole !== $role) {
            die("❌ Akses ditolak! Halaman ini khusus untuk role: $role");
        }
    }
}
