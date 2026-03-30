<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];

    try {
        // Début de la transaction pour garantir l'intégrité des données
        $pdo->beginTransaction();

        if ($type === 'ticket') {
            // 1. Récupérer les infos du ticket (voyage_id et nombre de places) avant de le supprimer
            $stmt_info = $pdo->prepare("SELECT voyage_id, nombre_places FROM reservations WHERE id = ? AND client_id = ?");
            $stmt_info->execute([$id, $user_id]);
            $reservation = $stmt_info->fetch();

            if ($reservation) {
                $voyage_id = $reservation['voyage_id'];
                $nb_places = $reservation['nombre_places'];

                // 2. Supprimer la réservation
                $stmt_del = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
                $stmt_del->execute([$id]);

                // 3. MISE À JOUR : Remettre les places dans le stock du voyage
                // On diminue 'places_occupees' pour que les places redeviennent 'libres'
                $stmt_upd = $pdo->prepare("UPDATE voyages_disponibles 
                                         SET places_occupees = places_occupees - ? 
                                         WHERE id = ?");
                $stmt_upd->execute([$nb_places, $voyage_id]);
            }
            
        } elseif ($type === 'colis') {
            // Annulation simple pour les colis
            $stmt = $pdo->prepare("DELETE c FROM courriers c 
                                 JOIN demandes_courrier d ON c.demande_id = d.id 
                                 WHERE c.id = ? AND d.client_id = ?");
            $stmt->execute([$id, $user_id]);
        }

        // Valider tous les changements
        $pdo->commit();
        header("Location: dashboard_client.php?msg=Annulation réussie");
        exit();

    } catch (PDOException $e) {
        // En cas d'erreur, on annule tout ce qui a été fait dans la transaction
        $pdo->rollBack();
        header("Location: dashboard_client.php?error=Erreur lors de l'annulation");
        exit();
    }
} else {
    header("Location: dashboard_client.php");
    exit();
}