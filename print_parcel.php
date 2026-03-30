<?php
require_once 'db_config.php';

if (!isset($_GET['id'])) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>❌ Erreur</h2>ID du colis manquant.</div>");
}

$id = $_GET['id'];

try {
    // On récupère tout depuis demandes_courrier qui est la table source
    // On récupère aussi les noms des agences pour un reçu complet
    $stmt = $pdo->prepare("SELECT d.*, 
                            a1.nom_agence as agence_depart, 
                            a2.nom_agence as agence_dest
                           FROM demandes_courrier d
                           LEFT JOIN agences a1 ON d.agence_depart_id = a1.id
                           LEFT JOIN agences a2 ON d.agence_destination_id = a2.id
                           WHERE d.id = ?");
    $stmt->execute([$id]);
    $c = $stmt->fetch();

    if(!$c) die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>📦 Introuvable</h2>Ce colis n'existe pas dans notre système.</div>");

} catch (PDOException $e) {
    die("Erreur technique : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recu_<?php echo $c['code_suivi']; ?></title>
    <!-- On utilise FontAwesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #f97316; --dark: #0f172a; --gray: #64748b; }
        body { font-family: 'Inter', system-ui, sans-serif; padding: 20px; background-color: #f1f5f9; color: var(--dark); }
        
        .receipt-container {
            max-width: 450px;
            margin: auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }

        /* Décoration de ticket (petits cercles sur les côtés) */
        .receipt-container::before, .receipt-container::after {
            content: ""; position: absolute; width: 20px; height: 20px; 
            background: #f1f5f9; border-radius: 50%; top: 50%;
        }
        .receipt-container::before { left: -10px; }
        .receipt-container::after { right: -10px; }

        .header {
            background: var(--primary);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header i { font-size: 40px; margin-bottom: 10px; }

        .content { padding: 30px; }

        .tracking-box {
            text-align: center;
            background: #fff7ed;
            border: 1px dashed var(--primary);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .row b { color: var(--gray); font-weight: 500; text-transform: uppercase; font-size: 11px; }
        .row span { font-weight: 700; }

        .price-tag {
            margin-top: 20px;
            padding: 20px;
            background: var(--dark);
            color: white;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .qr-mock {
            margin: 20px auto;
            width: 80px;
            height: 80px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: var(--dark);
        }

        .btn-container { text-align: center; margin-top: 20px; }
        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-print { background: var(--primary); color: white; }
        .btn-back { background: #cbd5e1; color: var(--dark); }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; border: 1px solid #eee; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="header">
            <i class="fas fa-truck-fast"></i>
            <h2 style="margin:0; text-transform: uppercase; letter-spacing: 1px;">TransExpress</h2>
            <p style="margin:5px 0 0; font-size: 12px; opacity: 0.9;">Logistics & Forwarding Ltd.</p>
        </div>

        <div class="content">
            <div class="tracking-box">
                <p style="margin:0; font-size: 10px; color: var(--primary); font-weight: bold;">ID DE SUIVI UNIQUE</p>
                <h3 style="margin:5px 0 0; color: var(--dark);"><?php echo $c['code_suivi']; ?></h3>
            </div>

            <div class="row"><b>Date</b> <span><?php echo date('d/m/Y H:i'); ?></span></div>
            <div class="row"><b>Expéditeur</b> <span><?php echo htmlspecialchars($c['expediteur']); ?></span></div>
            <div class="row"><b>Destinataire</b> <span><?php echo htmlspecialchars($c['destinataire']); ?></span></div>
            <div class="row"><b>Trajet</b> <span><?php echo $c['agence_depart'] ?? 'N/A'; ?> ➔ <?php echo $c['agence_dest'] ?? 'N/A'; ?></span></div>
            <div class="row"><b>Type / Poids</b> <span><?php echo ucfirst($c['type']); ?> (<?php echo $c['poids']; ?> kg)</span></div>
            
            <div class="price-tag">
                <span style="font-size: 12px; opacity: 0.8;">NET À PAYER</span>
                <span style="font-size: 20px; font-weight: 800; color: #4ade80;"><?php echo number_format($c['prix_total'], 0, '.', ','); ?> FBU</span>
            </div>

            <div class="qr-mock">
                <i class="fas fa-qrcode"></i>
            </div>

            <p style="text-align: center; font-size: 10px; color: var(--gray); line-height: 1.4;">
                Ce document fait office de preuve de dépôt.<br>
                Le destinataire doit présenter une pièce d'identité.<br>
                <b>Merci de votre confiance !</b>
            </p>
        </div>
    </div>

    <div class="btn-container no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Imprimer le reçu
        </button>
        <a href="dashboard_client.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

</body>
</html>