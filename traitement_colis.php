<?php
session_start();
require_once 'db_config.php';

// On vérifie que les données arrivent bien par le formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Récupération des données du formulaire 
        // Note : On s'adapte aux noms de colonnes de ta table existante
        $client_id             = $_POST['client_id'] ?? null;
        $code_suivi            = $_POST['code_suivi'] ?? 'COL-' . strtoupper(substr(uniqid(), -5));
        $numero_unique         = $code_suivi; // On utilise le même pour numero_unique
        $type                  = $_POST['type'] ?? 'enveloppe';
        $poids                 = !empty($_POST['poids']) ? $_POST['poids'] : 0;
        $expediteur            = $_POST['expediteur'] ?? 'Anonyme';
        $contact_expediteur    = $_POST['contact_exp'] ?? ''; // name du form -> colonne table
        $destinataire          = $_POST['destinataire'] ?? '';
        $contact_destinataire  = $_POST['contact_dest'] ?? ''; // name du form -> colonne table
        $agence_depart_id      = $_POST['agence_depart'] ?? null;
        $agence_destination_id = $_POST['agence_desti'] ?? null;
        $prix_total            = $_POST['prix_total'] ?? 0;
        $statut                = 'En attente';
        $date_envoi_prevue     = date('Y-m-d'); // Par défaut aujourd'hui

        // 2. Vérification de sécurité
        if (!$client_id || !$agence_depart_id || !$agence_destination_id) {
            die("Erreur : Données manquantes (Agences ou Client).");
        }

        // 3. La requête SQL avec TES noms de colonnes exacts
        $sql = "INSERT INTO demandes_courrier (
                    client_id, 
                    code_suivi, 
                    numero_unique,
                    type, 
                    poids, 
                    expediteur, 
                    contact_expediteur, 
                    destinataire, 
                    contact_destinataire, 
                    agence_depart_id, 
                    agence_destination_id, 
                    date_envoi_prevue,
                    prix_total, 
                    statut
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        
        // 4. Exécution de l'insertion
        $success = $stmt->execute([
            $client_id, 
            $code_suivi, 
            $numero_unique,
            $type, 
            $poids,
            $expediteur, 
            $contact_expediteur, 
            $destinataire, 
            $contact_destinataire, 
            $agence_depart_id, 
            $agence_destination_id, 
            $date_envoi_prevue,
            $prix_total, 
            $statut
        ]);

        if ($success) {
            header("Location: dashboard_client.php?parcel_success=1");
            exit();
        }

    } catch (PDOException $e) {
        // En cas d'erreur, on affiche le message précis pour débugger
        die("Erreur SQL : " . $e->getMessage());
    }
} else {
    header("Location: dashboard_client.php");
    exit();
}