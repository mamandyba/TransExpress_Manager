<?php
session_start();
require_once 'db_config.php';

if (!isset($_GET['id'])) {
    die("ID du ticket manquant.");
}

$id = $_GET['id'];

try {
    // 1. On récupère les infos de réservation et de voyage
    // Ajout d'une jointure optionnelle avec 'utilisateurs' au cas où nom_passager est vide
    $stmt = $pdo->prepare("SELECT r.*, v.depart, v.destination, v.date_voyage, v.heure_depart, v.prix, u.nom as nom_client
                           FROM reservations r 
                           JOIN voyages_disponibles v ON r.voyage_id = v.id 
                           LEFT JOIN users u ON r.client_id = u.id
                           WHERE r.id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();

    if (!$t) {
        die("Ticket non trouvé.");
    }

    // 2. Logique de détermination du nom à afficher
    // Si 'nom_passager' est vide dans la table reservations, on prend le nom du compte client
    $affichage_nom = !empty($t['nom_passager']) ? $t['nom_passager'] : ($t['nom_client'] ?? 'Passager TransExpress');

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket_<?php echo $t['numero_ticket']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --blue: #0A1F33; --accent: #2563eb; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; padding: 40px 20px; color: var(--blue); }
        
        .ticket {
            max-width: 380px;
            margin: auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(10, 31, 51, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .ticket-header {
            background: var(--blue);
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }

        /* Effet de découpe de ticket */
        .cut-out {
            position: relative;
            height: 20px;
            background: white;
            margin: 0 -10px;
        }
        .cut-out::before, .cut-out::after {
            content: ""; position: absolute; width: 20px; height: 20px; 
            background: #f8fafc; border-radius: 50%; top: -10px;
        }
        .cut-out::before { left: -10px; }
        .cut-out::after { right: -10px; }

        .ticket-body { padding: 25px; }

        .row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
        .label { font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .value { font-size: 14px; font-weight: 700; }

        .route {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f0f7ff;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .total-price {
            background: var(--blue);
            color: #4DFF88;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
        }

        .qr-area { text-align: center; margin-top: 20px; opacity: 0.8; }
        
        .btn-group { text-align: center; margin-top: 30px; }
        .btn { padding: 12px 25px; border-radius: 10px; font-weight: bold; text-decoration: none; cursor: pointer; border: none; font-size: 14px; transition: 0.3s; }
        .btn-print { background: var(--blue); color: white; margin-right: 10px; }
        .btn-back { background: #e2e8f0; color: #475569; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .ticket { box-shadow: none; border: 1px solid #000; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="ticket">
        <div class="ticket-header">
            <h2 style="margin:0; font-weight: 900; letter-spacing: -1px;">TRANSEXPRESS</h2>
            <p style="margin:5px 0 0; font-size: 10px; opacity: 0.8; font-weight: bold; letter-spacing: 2px;">BOARDING PASS</p>
        </div>

        <div class="cut-out"></div>

        <div class="ticket-body">
            <div class="row" style="border:none;">
                <span class="label">Ticket No</span>
                <span class="value" style="color: var(--accent);"><?php echo $t['numero_ticket']; ?></span>
            </div>

            <div class="route">
                <div>
                    <p class="label">Départ</p>
                    <p class="value"><?php echo strtoupper($t['depart']); ?></p>
                </div>
                <i class="fas fa-bus-alt" style="color: var(--accent);"></i>
                <div>
                    <p class="label">Destination</p>
                    <p class="value"><?php echo strtoupper($t['destination']); ?></p>
                </div>
            </div>

            <div class="row">
                <span class="label"><i class="far fa-user mr-1"></i> Passager</span>
                <span class="value"><?php echo htmlspecialchars($affichage_nom); ?></span>
            </div>

            <div class="row">
                <span class="label"><i class="far fa-calendar-alt mr-1"></i> Date</span>
                <span class="value"><?php echo date('d M Y', strtotime($t['date_voyage'])); ?></span>
            </div>

            <div class="row">
                <span class="label"><i class="far fa-clock mr-1"></i> Heure</span>
                <span class="value"><?php echo substr($t['heure_depart'], 0, 5); ?></span>
            </div>

            <div class="row">
                <span class="label"><i class="fas fa-chair mr-1"></i> Sièges</span>
                <span class="value"><?php echo $t['nombre_places']; ?> Adulte(s)</span>
            </div>

            <div class="total-price">
                <p style="margin:0; font-size: 10px; font-weight: bold; color: white; opacity: 0.7;">TOTAL PAYÉ</p>
                <p style="margin:5px 0 0; font-size: 22px; font-weight: 900;"><?php echo number_format($t['prix'] * $t['nombre_places'], 0, '.', ','); ?> FBU</p>
            </div>

            <div class="qr-area">
                <i class="fas fa-qrcode fa-4x"></i>
                <p style="font-size: 8px; margin-top: 10px; font-weight: bold;">SCANNEZ POUR VÉRIFICATION</p>
            </div>
        </div>
    </div>

    <div class="btn-group no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <a href="dashboard_client.php" class="btn btn-back">Retour</a>
    </div>

</body>
</html>