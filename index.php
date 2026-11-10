<?php

require_once "config.php";

$message = "";
$typeMessage = "";

// Fonction pour sécuriser l'affichage
function securiser($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

// Fonction pour afficher les prix
function prix($valeur) {
    return number_format((float)$valeur, 0, ',', ' ') . " Ar";
}


// ==========================================
// TRAITEMENT DES FORMULAIRES
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    try {

        // ==================================
        // 1. AJOUTER UN PRODUIT
        // ==================================

        if ($action === "ajouter_produit") {

            $designation = trim($_POST["designation"]);
            $prixProduit = (float)$_POST["prix"];
            $quantite = (int)$_POST["quantite"];

            if ($designation === "" || $prixProduit <= 0 || $quantite < 0) {
                throw new Exception("Veuillez remplir correctement les informations du produit.");
            }

            $sql = "INSERT INTO produits (designation, prix, Quantite)
                    VALUES (:designation, :prix, :quantite)";

            $stmt = $pdoProduit->prepare($sql);

            $stmt->execute([
                ":designation" => $designation,
                ":prix" => $prixProduit,
                ":quantite" => $quantite
            ]);

            $message = "Produit ajouté avec succès !";
            $typeMessage = "success";
        }


        // ==================================
        // 2. AJOUTER UNE COMMANDE
        // ==================================

        elseif ($action === "ajouter_commande") {

            $idProduit = (int)$_POST["id_produit"];
            $quantite = (int)$_POST["quantite"];
            $date = $_POST["date_commande"];
            $statut = $_POST["statut"];

            if ($idProduit <= 0 || $quantite <= 0 || empty($date)) {
                throw new Exception("Veuillez remplir tous les champs de la commande.");
            }

            // Récupérer le produit depuis la base PRODUIT
            $sql = "SELECT * FROM produits
                    WHERE id_produit = :id";

            $stmt = $pdoProduit->prepare($sql);
            $stmt->execute([
                ":id" => $idProduit
            ]);

            $produit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produit) {
                throw new Exception("Produit introuvable.");
            }

            // Vérifier le stock disponible
            if ($produit["Quantite"] < $quantite) {
                throw new Exception("Stock insuffisant pour ce produit.");
            }

            // Enregistrer la commande dans la base COMMANDE
            $sql = "INSERT INTO commandes
                    (id_produit, designation, quantite,
                     prix_unitaire, date_commande, statut)
                    VALUES
                    (:id_produit, :designation, :quantite,
                     :prix, :date, :statut)";

            $stmt = $pdoCommande->prepare($sql);

            $stmt->execute([
                ":id_produit" => $idProduit,
                ":designation" => $produit["designation"],
                ":quantite" => $quantite,
                ":prix" => $produit["prix"],
                ":date" => $date,
                ":statut" => $statut
            ]);

            // Diminuer le stock dans la base PRODUIT
            $sql = "UPDATE produits
                    SET Quantite = Quantite - :quantite
                    WHERE id_produit = :id";

            $stmt = $pdoProduit->prepare($sql);

            $stmt->execute([
                ":quantite" => $quantite,
                ":id" => $idProduit
            ]);

            $message = "Commande enregistrée avec succès !";
            $typeMessage = "success";
        }


        // ==================================
        // 3. ENREGISTRER UN ACHAT
        // ==================================

        elseif ($action === "ajouter_achat") {

            // Récupérer uniquement l'identifiant de la commande
            $idCommande = (int)($_POST["id_commande"] ?? 0);

            if ($idCommande <= 0) {
                throw new Exception("Veuillez sélectionner une commande.");
            }

            // Récupérer les informations de la commande
            $sql = "SELECT *
                    FROM commandes
                    WHERE id_commande = :id";

            $stmt = $pdoCommande->prepare($sql);

            $stmt->execute([
                ":id" => $idCommande
            ]);

            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$commande) {
                throw new Exception("Commande introuvable.");
            }

            // Vérifier si cette commande a déjà été achetée
            $sql = "SELECT COUNT(*)
                    FROM achats
                    WHERE id_commande = :id";

            $stmt = $pdoAchat->prepare($sql);

            $stmt->execute([
                ":id" => $idCommande
            ]);

            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette commande a déjà été enregistrée comme achat.");
            }

            // Récupérer automatiquement les informations de la commande
            $idProduit = (int)$commande["id_produit"];
            $quantite = (int)$commande["quantite"];
            $prixUnitaire = (float)$commande["prix_unitaire"];

            if ($idProduit <= 0 || $quantite <= 0 || $prixUnitaire <= 0) {
                throw new Exception("Les informations de cette commande sont incorrectes.");
            }

            // Récupérer le produit depuis la base PRODUIT
            $sql = "SELECT *
                    FROM produits
                    WHERE id_produit = :id";

            $stmt = $pdoProduit->prepare($sql);

            $stmt->execute([
                ":id" => $idProduit
            ]);

            $produit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produit) {
                throw new Exception("Le produit associé à cette commande est introuvable.");
            }

            // Récupérer automatiquement la désignation du produit
            $designation = $produit["designation"];

            // Récupérer automatiquement la date du jour
            $date = date("Y-m-d");

            // Calculer automatiquement le montant
            $montant = $quantite * $prixUnitaire;

            // Enregistrer l'achat dans la base ACHAT
            // La colonne correcte est designation et non nom_produit.
            $sql = "INSERT INTO achats
                    (id_commande, id_produit, designation,
                     quantite, prix_unitaire, date_achat, montant)
                    VALUES
                    (:id_commande, :id_produit, :designation,
                     :quantite, :prix, :date, :montant)";

            $stmt = $pdoAchat->prepare($sql);

            $stmt->execute([
                ":id_commande" => $idCommande,
                ":id_produit" => $idProduit,
                ":designation" => $designation,
                ":quantite" => $quantite,
                ":prix" => $prixUnitaire,
                ":date" => $date,
                ":montant" => $montant
            ]);

            // Augmenter le stock du produit
            $sql = "UPDATE produits
                    SET Quantite = Quantite + :quantite
                    WHERE id_produit = :id";

            $stmt = $pdoProduit->prepare($sql);

            $stmt->execute([
                ":quantite" => $quantite,
                ":id" => $idProduit
            ]);

            $message = "Achat enregistré avec succès !";
            $typeMessage = "success";
        }

    } catch (Exception $e) {

        $message = $e->getMessage();
        $typeMessage = "error";
    }
}


// ==========================================
// RÉCUPÉRATION DES DONNÉES
// ==========================================

// Liste des produits
$produits = $pdoProduit
    ->query("SELECT * FROM produits ORDER BY id_produit ASC")
    ->fetchAll(PDO::FETCH_ASSOC);


// Liste des commandes
$commandes = $pdoCommande
    ->query("SELECT * FROM commandes ORDER BY id_commande DESC")
    ->fetchAll(PDO::FETCH_ASSOC);


// Liste des achats
$achats = $pdoAchat
    ->query("SELECT * FROM achats ORDER BY id_achat DESC")
    ->fetchAll(PDO::FETCH_ASSOC);


// Total des achats
$totalAchats = $pdoAchat
    ->query("SELECT COALESCE(SUM(montant), 0) FROM achats")
    ->fetchColumn();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des services Docker</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="container">


    <!-- ================================= -->
    <!-- MESSAGE DE NOTIFICATION -->
    <!-- ================================= -->

    <?php if ($message !== ""): ?>

        <div class="message <?= securiser($typeMessage) ?>">
            <?= securiser($message) ?>
        </div>

    <?php endif; ?>


    <!-- ================================= -->
    <!-- 1. GESTION DES PRODUITS -->
    <!-- ================================= -->

    <section class="card">

        <h2>Gestion des produits</h2>

        <form method="POST" class="formulaire">

            <input type="hidden" name="action" value="ajouter_produit">

            <input
                type="text"
                name="designation"
                placeholder="Nom du produit"
                required
            >

            <input
                type="number"
                name="prix"
                placeholder="Prix (Ar)"
                min="1"
                step="0.01"
                required
            >

            <input
                type="number"
                name="quantite"
                placeholder="Quantité"
                min="0"
                required
            >

            <button type="submit">Ajouter un produit</button>

        </form>


        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Prix (Ar)</th>
                        <th>Quantité</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($produits as $p): ?>

                        <tr>

                            <td><?= securiser($p["id_produit"]) ?></td>

                            <td><?= securiser($p["designation"]) ?></td>

                            <td><?= prix($p["prix"]) ?></td>

                            <td><?= securiser($p["Quantite"]) ?></td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if (empty($produits)): ?>

                        <tr>
                            <td colspan="4" class="vide">
                                Aucun produit enregistré.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>



    <!-- ================================= -->
    <!-- 2. GESTION DES COMMANDES -->
    <!-- ================================= -->

    <section class="card">

        <h2>Gestion des commandes</h2>

        <form method="POST" class="formulaire">

            <input type="hidden" name="action" value="ajouter_commande">

            <select name="id_produit" required>

                <option value="">Sélectionner un produit</option>

                <?php foreach ($produits as $p): ?>

                    <option value="<?= $p["id_produit"] ?>">

                        <?= securiser($p["designation"]) ?>
                        (Stock : <?= securiser($p["Quantite"]) ?>)

                    </option>

                <?php endforeach; ?>

            </select>


            <input
                type="number"
                name="quantite"
                placeholder="Quantité"
                min="1"
                required
            >


            <input
                type="date"
                name="date_commande"
                value="<?= date('Y-m-d') ?>"
                required
            >


            <select name="statut">

                <option value="en attente">En attente</option>

                <option value="achetée">Achetée</option>

            </select>


            <button type="submit">Ajouter une commande</button>

        </form>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Prix unitaire (Ar)</th>
                        <th>Montant (Ar)</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($commandes as $c): ?>

                        <tr>

                            <td><?= securiser($c["id_commande"]) ?></td>

                            <td><?= securiser($c["designation"]) ?></td>

                            <td><?= securiser($c["quantite"]) ?></td>

                            <td><?= prix($c["prix_unitaire"]) ?></td>

                            <td>
                                <?= prix($c["quantite"] * $c["prix_unitaire"]) ?>
                            </td>

                            <td><?= securiser($c["date_commande"]) ?></td>

                            <td>

                                <span class="statut">
                                    <?= securiser($c["statut"]) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (empty($commandes)): ?>

                        <tr>
                            <td colspan="7" class="vide">
                                Aucune commande enregistrée.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>



    <!-- ================================= -->
    <!-- 3. GESTION DES ACHATS -->
    <!-- ================================= -->

    <section class="card">

        <h2>Gestion des achats</h2>


        <form method="POST" class="formulaire achat-form">

            <input type="hidden" name="action" value="ajouter_achat">

            <!-- Sélectionner uniquement une commande -->

            <select name="id_commande" required>

                <option value="">Sélectionner une commande</option>

                <?php foreach ($commandes as $c): ?>

                    <option value="<?= securiser($c["id_commande"]) ?>">

                        Commande #<?= securiser($c["id_commande"]) ?>
                        - <?= securiser($c["designation"]) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <button type="submit">Enregistrer l'achat</button>

        </form>


        <p class="total">

            Montant total :

            <strong><?= prix($totalAchats) ?></strong>

        </p>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Commande</th>
                        <th>Date d'achat</th>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Prix unitaire (Ar)</th>
                        <th>Montant (Ar)</th>
                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($achats as $a): ?>

                        <tr>

                            <td><?= securiser($a["id_achat"]) ?></td>

                            <td>

                                <?= $a["id_commande"] !== null
                                    ? "#" . securiser($a["id_commande"])
                                    : "-" ?>

                            </td>

                            <td><?= securiser($a["date_achat"]) ?></td>

                            <td><?= securiser($a["designation"]) ?></td>

                            <td><?= securiser($a["quantite"]) ?></td>

                            <td><?= prix($a["prix_unitaire"]) ?></td>

                            <td><?= prix($a["montant"]) ?></td>

                        </tr>

                    <?php endforeach; ?>


                    <?php if (empty($achats)): ?>

                        <tr>
                            <td colspan="7" class="vide">
                                Aucun achat enregistré.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

</body>
</html>