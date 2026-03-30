<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";

// Simuler des tarifs (à remplacer par une table 'tarifs' dans votre BDD si besoin)
$tarifs = [
    "Bujumbura - Gitega" => 15000,
    "Bujumbura - Ngozi" => 18000,
    "Bujumbura - Rumonge" => 12000,
    "Gitega - Muyinga" => 10000
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passager_id = $_SESSION['user_id'];
    $trajet = $_POST['trajet'];
    $date_voyage = $_POST['date_voyage'];
    $siege = $_POST['siege'];
    $prix = $tarifs[$trajet] ?? 0;
    $numero_ticket = "TX-" . strtoupper(substr(md5(uniqid()), 0, 6));

    $stmt = $pdo->prepare("INSERT INTO tickets (passager_id, numero_ticket, trajet, date_voyage, siege, prix, statut_ticket) VALUES (?, ?, ?, ?, ?, ?, 'confirmé')");
    
    if ($stmt->execute([$passager_id, $numero_ticket, $trajet, $date_voyage, $siege, $prix])) {
        $message = "<div class='bg-[#4DFF88]/20 text-[#0A1F33] p-4 rounded-2xl text-center mb-6 font-bold border border-[#4DFF88]'>Ticket réservé avec succès ! ID: $numero_ticket</div>";
    } else {
        $message = "<div class='bg-red-100 text-red-700 p-4 rounded-2xl text-center mb-6'>Erreur lors de la réservation.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>Réserver un Ticket - TransExpress</title>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .input-card { transition: all 0.3s ease; border: 2px solid transparent; }
        .input-card:focus-within { border-color: #4DFF88; background: white; }
    </style>
</head>
<body class="bg-[#F8FAFC] pb-10">

    <!-- Header -->
    <div class="bg-[#0A1F33] p-8 rounded-b-[50px] shadow-2xl">
        <a href="dashboard_client.php" class="text-white/50 hover:text-white transition-colors flex items-center gap-2 text-sm mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Retour
        </a>
        <h1 class="text-white text-3xl font-extrabold">Réserver un <span class="text-[#4DFF88]">voyage</span></h1>
        <p class="text-gray-400 mt-2">Choisissez votre destination et votre siège.</p>
    </div>

    <main class="p-6 -mt-10 max-w-md mx-auto">
        
        <?php echo $message; ?>

        <form action="" method="POST" class="space-y-6">
            
            <!-- Choix du trajet -->
            <div class="bg-gray-100/50 p-1 rounded-[30px] input-card">
                <div class="p-5">
                    <label class="block text-[10px] font-extrabold text-[#0A1F33] uppercase tracking-[0.2em] mb-3">Itinéraire</label>
                    <select name="trajet" id="trajet" required onchange="updatePrice()" class="w-full bg-transparent text-[#0A1F33] font-bold outline-none appearance-none cursor-pointer">
                        <option value="" disabled selected>Où allez-vous ?</option>
                        <?php foreach($tarifs as $ville => $p): ?>
                            <option value="<?php echo $ville; ?>"><?php echo $ville; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Date du voyage -->
            <div class="bg-gray-100/50 p-1 rounded-[30px] input-card">
                <div class="p-5">
                    <label class="block text-[10px] font-extrabold text-[#0A1F33] uppercase tracking-[0.2em] mb-3">Date de départ</label>
                    <input type="date" name="date_voyage" required min="<?php echo date('Y-m-d'); ?>" class="w-full bg-transparent text-[#0A1F33] font-bold outline-none cursor-pointer">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Choix du siège -->
                <div class="bg-gray-100/50 p-1 rounded-[30px] input-card">
                    <div class="p-5">
                        <label class="block text-[10px] font-extrabold text-[#0A1F33] uppercase tracking-[0.2em] mb-3">Siège</label>
                        <input type="number" name="siege" placeholder="01" min="1" max="32" required class="w-full bg-transparent text-[#0A1F33] font-bold outline-none">
                    </div>
                </div>

                <!-- Affichage du prix -->
                <div class="bg-[#0A1F33] p-1 rounded-[30px] shadow-lg shadow-blue-900/20">
                    <div class="p-5">
                        <label class="block text-[10px] font-extrabold text-[#4DFF88] uppercase tracking-[0.2em] mb-3">Prix Total</label>
                        <div class="text-white font-black text-xl"><span id="prix_display">0</span> <small class="text-[10px]">FBU</small></div>
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full py-6 bg-[#4DFF88] text-[#0A1F33] font-black rounded-[30px] shadow-xl shadow-green-500/20 hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-widest text-sm">
                    CONFIRMER LA RÉSERVATION
                </button>
            </div>

        </form>

        <div class="mt-8 bg-white border border-gray-100 p-6 rounded-[35px] flex items-start gap-4 shadow-sm">
            <div class="bg-yellow-100 p-3 rounded-2xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
                <h4 class="text-[#0A1F33] font-bold text-sm">Information</h4>
                <p class="text-gray-400 text-xs mt-1 leading-relaxed">Veuillez vous présenter à l'agence 30 minutes avant le départ avec votre code de réservation.</p>
            </div>
        </div>
    </main>

    <script>
        const tarifs = <?php echo json_encode($tarifs); ?>;
        
        function updatePrice() {
            const trajetSelect = document.getElementById('trajet');
            const prixDisplay = document.getElementById('prix_display');
            const selectedTrajet = trajetSelect.value;
            
            if (tarifs[selectedTrajet]) {
                prixDisplay.innerText = tarifs[selectedTrajet].toLocaleString();
            } else {
                prixDisplay.innerText = "0";
            }
        }
    </script>

</body>
</html>