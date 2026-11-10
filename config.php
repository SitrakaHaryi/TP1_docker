<?php

$utilisateur = "root";
$mot_de_passe = "root";

// Connexion PRODUIT
try {

    $pdoProduit = new PDO(
        "mysql:host=127.0.0.1;port=3308;dbname=produit;charset=utf8mb4",
        $utilisateur,
        $mot_de_passe
    );

    $pdoProduit->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Erreur connexion PRODUIT : " . $e->getMessage());

}


// Connexion COMMANDE
try {

    $pdoCommande = new PDO(
        "mysql:host=127.0.0.1;port=3309;dbname=commande;charset=utf8mb4",
        $utilisateur,
        $mot_de_passe
    );

    $pdoCommande->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Erreur connexion COMMANDE : " . $e->getMessage());

}


// Connexion ACHAT
try {

    $pdoAchat = new PDO(
        "mysql:host=127.0.0.1;port=3307;dbname=achat;charset=utf8mb4",
        $utilisateur,
        $mot_de_passe
    );

    $pdoAchat->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Erreur connexion ACHAT : " . $e->getMessage());

}

?>