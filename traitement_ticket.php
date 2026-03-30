<?php
session_start();
require_once 'db_config.php';

// 1. VÉRIFICATION DE CONNEXION
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_voyage'])) {
    
    $id_voyage = $_POST['id_voyage'];
    $id_client = $_SESSION['user_id'];
    $prix = $_POST['prix'];
    $depart = $_POST['depart'];
    $destination = $_POST['destination'];
    $date_achat = date('Y-m-d H:i:s');
    $numero_ticket = "TICK-" . strtoupper(substr(uniqid(), -6)); // Génère un numéro unique

    try {
        // DÉBUT DE LA TRANSACTION (Pour la sécurité des données)
        $pdo->beginTransaction();

        // 2. VÉRIFIER LA DISPONIBILITÉ RÉELLE
        $stmt_check = $pdo->prepare("SELECT places_totales, places_occupees FROM voyages_disponibles WHERE id = ? FOR UPDATE");
        $stmt_check->execute([$id_voyage]);
        $voyage = $stmt_check->fetch();

        if ($voyage && ($voyage['places_totales'] - $voyage['places_occupees']) > 0) {
            
            // 3. INSÉRER LE TICKET
            $stmt_ticket = $pdo->prepare("INSERT INTO tickets (id_client, depart, destination, prix, date_achat, numero_ticket, statut_ticket) VALUES (?, ?, ?, ?, ?, ?, 'Payé')");
            $stmt_ticket->execute([$id_client, $depart, $destination, $prix, $date_achat, $numero_ticket]);

            // 4. METTRE À JOUR LE NOMBRE DE PLACES DANS LE PLANNING
            $stmt_update = $pdo->prepare("UPDATE voyages_disponibles SET places_occupees = places_occupees + 1 WHERE id = ?");
            $stmt_update->execute([$id_voyage]);

            // VALIDER TOUTES LES OPÉRATIONS
            $pdo->commit();

            // REDIRECTION AVEC SUCCÈS
            header("Location: dashboard_client.php?success=Votre ticket $numero_ticket a été réservé !");
            exit();

        } else {
            // PLUS DE PLACES DISPONIBLES
            $pdo->rollBack();
            header("Location: dashboard_client.php?error=Désolé, ce voyage est désormais complet.");
            exit();
        }

    } catch (PDOException $e) {
        // EN CAS D'ERREUR, ANNULER TOUT
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: dashboard_client.php?error=Erreur système : " . $e->getMessage());
        exit();
    }

} else {
    // ACCÈS DIRECT INTERDIT
    header("Location: dashboard_client.php");
    exit();
}