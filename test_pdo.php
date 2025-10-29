<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=app", "root", "mmjc2004");
    echo "Connexion réussie à MySQL via PDO.";
} catch (PDOException $e) {
    echo "Erreur PDO : " . $e->getMessage();
}
