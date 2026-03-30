<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $voyage_id = intval($_POST['voyage_id']);
    $client_id = intval($_SESSION['user_id']);
    $nb_places = intval($_POST['nombre_places']);
    
    // Génération d'un numéro de ticket unique (ex: TKT-A7B2)
    $numero_ticket = "TKT-" . strtoupper(substr(uniqid(), -4));

    try {
        $pdo->beginTransaction();

        // 1. Vérifier si les places sont encore disponibles
        $stmt = $pdo->prepare("SELECT (places_totales - places_occupees) as libres FROM voyages_disponibles WHERE id = ? FOR UPDATE");
        $stmt->execute([$voyage_id]);
        $voyage = $stmt->fetch();

        if ($voyage && $voyage['libres'] >= $nb_places) {
            
            // 2. Insérer la réservation avec statut 'Pending'
            $ins = $pdo->prepare("INSERT INTO reservations (voyage_id, client_id, numero_ticket, nombre_places, statut, date_reservation) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $ins->execute([$voyage_id, $client_id, $numero_ticket, $nb_places]);

            // 3. Diminuer le nombre de places dans la table voyages_disponibles
            $upd = $pdo->prepare("UPDATE voyages_disponibles SET places_occupees = places_occupees + ? WHERE id = ?");
            $upd->execute([$nb_places, $voyage_id]);

            $pdo->commit();
            // Redirection avec un paramètre de succès
            header("Location: dashboard_client.php?res_success=1");
            exit();
        } else {
            header("Location: dashboard_client.php?error=Plus de places disponibles");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la réservation : " . $e->getMessage());
    }
}