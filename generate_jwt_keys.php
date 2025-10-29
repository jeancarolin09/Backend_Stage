<?php
$privateKeyPath = __DIR__ . '/private.pem';
$publicKeyPath = __DIR__ . '/public.pem';
$passphrase = 'passphrase';

// Générer la clé privée
$privateKey = openssl_pkey_new([
    "private_key_bits" => 4096,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
]);
openssl_pkey_export($privateKey, $privateKeyOut, $passphrase);
file_put_contents($privateKeyPath, $privateKeyOut);

// Générer la clé publique
$details = openssl_pkey_get_details($privateKey);
$publicKeyOut = $details["key"];
file_put_contents($publicKeyPath, $publicKeyOut);

echo "Clés générées avec succès !\n";
