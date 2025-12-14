<?php
/**
 * Cryptography Functions (SECURE VERSION)
 * RSA 2048-bit & SHA-256 Implementation with Passphrase
 */

/**
 * Generate RSA Key Pair (Public & Private Key)
 * PERBAIKAN: Sekarang menerima $passphrase untuk mengenkripsi Private Key
 */
function generateKeyPair($passphrase) {
    // Validasi passphrase wajib ada
    if (empty($passphrase)) {
        return false;
    }

    $opensslConfig = getOpenSSLConfig();
    
    $config = array(
        "digest_alg" => "sha256",
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    );
    
    if ($opensslConfig !== null) {
        $config['config'] = $opensslConfig;
    }
    
    // Generate key resource
    $res = openssl_pkey_new($config);
    
    if ($res === false) {
        error_log("Failed to generate RSA key pair: " . openssl_error_string());
        return false;
    }
    
    // Export private key
    $privateKey = '';
    $export_config = $opensslConfig ? array('config' => $opensslConfig) : array();
    
    // PERBAIKAN PENTING: Menggunakan $passphrase untuk mengenkripsi kunci
    // Kunci yang dihasilkan sekarang HANYA bisa dibuka dengan password ini
    $export_success = openssl_pkey_export($res, $privateKey, $passphrase, $export_config);
    
    if (!$export_success) {
        error_log("Failed to export private key: " . openssl_error_string());
        openssl_pkey_free($res);
        return false;
    }
    
    // Get public key
    $publicKeyDetails = openssl_pkey_get_details($res);
    $publicKey = $publicKeyDetails["key"];
    
    openssl_pkey_free($res);
    
    return array(
        'public_key' => $publicKey,
        'private_key' => $privateKey // String ini sekarang terenkripsi (aman disimpan di DB)
    );
}

/**
 * Get OpenSSL Config Path
 */
function getOpenSSLConfig() {
    $possible_paths = array(
        'C:/xampp/apache/bin/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/Program Files/xampp/apache/bin/openssl.cnf',
        'C:/Program Files/Common Files/SSL/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf',
        '/etc/ssl/openssl.cnf',
        '/usr/local/ssl/openssl.cnf',
    );
    
    $env_config = getenv('OPENSSL_CONF');
    if ($env_config && file_exists($env_config)) {
        return $env_config;
    }
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            putenv("OPENSSL_CONF=" . $path);
            return $path;
        }
    }
    
    if (defined('OPENSSL_CONF')) {
        return OPENSSL_CONF;
    }
    
    return null;
}

/**
 * Hash Document Template
 */
function hashDocumentTemplate($data, $filePath = null) {
    $template = '';
    
    $template .= (isset($data['jenis_dokumen']) ? $data['jenis_dokumen'] : '') . '|';
    $template .= (isset($data['tanggal_mulai']) ? $data['tanggal_mulai'] : '') . '|';
    $template .= (isset($data['nama_pengaju']) ? $data['nama_pengaju'] : '') . '|';

    if ($filePath && file_exists($filePath)) {
        if (function_exists('extractSpecificData')) {
            $pdfData = extractSpecificData($filePath, $data['jenis_dokumen']);
            
            if (!empty($pdfData)) {
                foreach ($pdfData as $key => $val) {
                    $template .= $val . '|'; 
                }
            } else {
                $template .= hash_file('sha256', $filePath) . '|';
            }
        } else {
             $template .= hash_file('sha256', $filePath) . '|';
        }
    }
    
    return hash('sha256', $template);
}

/**
 * Sign Document (Updated with Passphrase)
 * PERBAIKAN: Menggunakan passphrase untuk membuka kunci sebelum tanda tangan
 */
function signDocument($documentHash, $privateKey, $passphrase) {
    // PERBAIKAN: Mencoba membuka kunci dengan password
    // Jika password salah, $privateKeyResource akan bernilai false
    $privateKeyResource = openssl_pkey_get_private($privateKey, $passphrase);
    
    if ($privateKeyResource === false) {
        error_log("Failed to load private key. Password mungkin salah.");
        return false;
    }
    
    $signature = '';
    $success = openssl_sign($documentHash, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
    
    openssl_pkey_free($privateKeyResource);
    
    if (!$success) {
        return false;
    }
    
    return base64_encode($signature);
}

/**
 * Verify Digital Signature
 */
function verifySignature($documentHash, $signature, $publicKey) {
    $publicKeyResource = openssl_pkey_get_public($publicKey);
    
    if ($publicKeyResource === false) {
        return false;
    }
    
    $signatureBinary = base64_decode($signature);
    
    if ($signatureBinary === false) {
        openssl_pkey_free($publicKeyResource);
        return false;
    }
    
    $result = openssl_verify($documentHash, $signatureBinary, $publicKeyResource, OPENSSL_ALGO_SHA256);
    
    openssl_pkey_free($publicKeyResource);
    
    if ($result === -1) {
        return false;
    }
    
    return $result === 1;
}

/**
 * Save Key Pair
 */
function saveKeyPair($conn, $user_id, $publicKey, $privateKey) {
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("UPDATE keypairs SET status = 'revoked' WHERE user_id = ? AND status = 'active'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO keypairs (user_id, public_key, private_key, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("iss", $user_id, $publicKey, $privateKey);
        $success = $stmt->execute();
        $stmt->close();
        
        if (!$success) {
            throw new Exception("Failed to insert new keypair");
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error saving keypair: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Active Key Pair
 */
function getActiveKeyPair($conn, $user_id) {
    $stmt = $conn->prepare("SELECT public_key, private_key FROM keypairs WHERE user_id = ? AND status = 'active' ORDER BY generated_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $keypair = $result->fetch_assoc();
        $stmt->close();
        return $keypair;
    }
    
    $stmt->close();
    return null;
}

function isOpenSSLAvailable() {
    return function_exists('openssl_pkey_new');
}

function getKeyInfo($publicKey) {
    $keyResource = openssl_pkey_get_public($publicKey);
    if ($keyResource === false) return false;
    $keyDetails = openssl_pkey_get_details($keyResource);
    openssl_pkey_free($keyResource);
    return $keyDetails;
}

/**
 * Validate Key Pair
 * PERBAIKAN: Sekarang butuh passphrase untuk test signing
 */
function validateKeyPair($publicKey, $privateKey, $passphrase) {
    $testData = "test_validation_" . time();
    
    // PERBAIKAN: Masukkan passphrase ke fungsi signDocument
    $signature = signDocument(hash('sha256', $testData), $privateKey, $passphrase);
    
    if ($signature === false) {
        return false;
    }
    
    return verifySignature(hash('sha256', $testData), $signature, $publicKey);
}
?>