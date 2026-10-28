<?php

declare(strict_types=1);

// Run once with: php tools/generate_kinta_offline_grant_key.php
// The private key is written only under writable/ and is never printed.
$projectRoot = dirname(__DIR__);
$keyDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'keys';
$privateKeyPath = $keyDirectory . DIRECTORY_SEPARATOR . 'kinta_offline_grant_private.pem';

if (is_file($privateKeyPath)) {
    fwrite(STDERR, "A KINTA offline-grant key already exists; refusing to replace it.\n");
    exit(1);
}
if (! extension_loaded('openssl')) {
    fwrite(STDERR, "The PHP OpenSSL extension must be enabled.\n");
    exit(1);
}

if (! is_dir($keyDirectory) && ! mkdir($keyDirectory, 0700, true) && ! is_dir($keyDirectory)) {
    fwrite(STDERR, "Could not create writable/keys.\n");
    exit(1);
}

// XAMPP's command-line PHP may not inherit the OpenSSL config path from Apache.
$defaultOpenSslConfig = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
if (! getenv('OPENSSL_CONF') && is_file($defaultOpenSslConfig)) {
    putenv('OPENSSL_CONF=' . $defaultOpenSslConfig);
}

$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
    'config' => is_file($defaultOpenSslConfig) ? $defaultOpenSslConfig : null,
]);
if ($key !== false && ! openssl_pkey_export($key, $privatePem)) {
    $key = false;
}

// Some XAMPP CLI builds cannot find their OpenSSL random-state file through
// PHP's extension. Fall back to XAMPP's bundled OpenSSL command-line tool.
if ($key === false) {
    $opensslBinary = 'C:\\xampp\\apache\\bin\\openssl.exe';
    if (is_file($opensslBinary)) {
        $command = escapeshellarg($opensslBinary)
            . ' genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out '
            . escapeshellarg($privateKeyPath)
            . ' 2>NUL';
        exec($command, $ignoredOutput, $exitCode);
        if ($exitCode === 0 && is_file($privateKeyPath)) {
            $privatePem = file_get_contents($privateKeyPath);
            $key = is_string($privatePem) ? openssl_pkey_get_private($privatePem) : false;
        }
    }
}

if ($key === false) {
    fwrite(STDERR, "Could not generate the RSA signing key. Check that PHP OpenSSL and the OpenSSL CLI are available.\n");
    exit(1);
}

if (! is_file($privateKeyPath)) {
    $written = file_put_contents($privateKeyPath, $privatePem, LOCK_EX);
    if ($written === false) {
        fwrite(STDERR, "Could not save the private key under writable/keys.\n");
        exit(1);
    }
}

$details = openssl_pkey_get_details($key);
if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
    unlink($privateKeyPath);
    fwrite(STDERR, "Could not export the public key parameters.\n");
    exit(1);
}

$base64Url = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
echo "Private key saved to writable/keys/kinta_offline_grant_private.pem (do not commit or share it).\n";
echo "Public RSA JWK values for Flutter:\n";
echo 'n=' . $base64Url($details['rsa']['n']) . "\n";
echo 'e=' . $base64Url($details['rsa']['e']) . "\n";
