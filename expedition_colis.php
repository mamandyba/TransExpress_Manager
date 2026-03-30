<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$prix_par_kg = 2500; // Tarif de base par kilo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_SESSION['user_id'];
    $destinataire = htmlspecialchars($_POST['destinataire']);
    $telephone_dest = htmlspecialchars($_POST['telephone_dest']);
    $destination = htmlspecialchars($_POST['destination']);
    $poids = floatval($_POST['poids']);
    $description = htmlspecialchars($_POST['description']);
    $prix = $poids * $prix_par_kg;
    $code_suivi = "TX-EXP-" . strtoupper(substr(md5(uniqid()), 0, 5));

    try {
        $pdo->beginTransaction();

        // 1. Créer la demande
        $stmt1 = $pdo->prepare("INSERT INTO demandes_courrier (client_id, destinataire, telephone_dest, destination, poids, description, prix_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt1->execute([$client_id, $destinataire, $telephone_dest, $destination, $poids, $description, $prix]);
        $demande_id = $pdo->lastInsertId();

        // 2. Créer le colis associé
        $stmt2 = $pdo->prepare("INSERT INTO courriers (demande_id, code_suivi, statut) VALUES (?, ?, 'En attente')");
        $stmt2->execute([$demande_id, $code_suivi,]);

        $pdo->commit();
        $message = "<div class='bg-[#4DFF88] text-[#0A1F33] p-5 rounded-[25px] text-center mb-6 font-bold shadow-lg'>Colis enregistré ! <br> <span class='text-xs opacity-70'>Suivi : $code_suivi</span></div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='bg-red-100 text-red-700 p-4 rounded-[25px] text-center mb-6'>Erreur : " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <title>Expédier un Colis - TransExpress</title>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .input-box { background: rgba(243, 244, 246, 0.6); transition: all 0.3s ease; border: 2px solid transparent; }
        .input-box:focus-within { background: white; border-color: #4DFF88; box-shadow: 0 10px 20px -10px rgba(77, 255, 136, 0.3); }
    </style>
</head>
<body class="bg-[#F8FAFC] pb-12">

    <!-- Header Courbé -->
    <div class="bg-[#0A1F33] p-10 rounded-b-[60px] shadow-xl relative overflow-hidden">
        <div class="relative z-10">
            <a href="dashboard_client.php" class="inline-flex items-center text-[#4DFF88] font-bold text-sm mb-6 bg-white/5 px-4 py-2 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" /></svg>
                Tableau de bord
            </a>
            <h1 class="text-white text-3xl font-black italic">EXPÉDIER <span class="text-[#4DFF88]">VITE.</span></h1>
            <p class="text-gray-400 text-sm mt-1">Remplissez les détails du colis ci-dessous.</p>
        </div>
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-[#4DFF88]/10 rounded-full blur-3xl"></div>
    </div>

    <main class="p-6 -mt-8 max-w-md mx-auto">
        
        <?php echo $message; ?>

        <form action="" method="POST" class="space-y-5">
            
            <!-- Section Destinataire -->
            <div class="bg-white p-6 rounded-[35px] shadow-sm border border-gray-50">
                <h3 class="text-[#0A1F33] font-black text-xs uppercase tracking-widest mb-5 flex items-center">
                    <span class="w-6 h-6 bg-[#0A1F33] text-white rounded-full flex items-center justify-center mr-2 text-[10px]">1</span>
                    Destinataire
                </h3>
                
                <div class="space-y-4">
                    <div class="input-box p-4 rounded-2xl">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Nom complet</label>
                        <input type="text" name="destinataire" required placeholder="Ex: Marc Ndikumana" class="w-full bg-transparent outline-none text-[#0A1F33] font-semibold">
                    </div>

                    <div class="input-box p-4 rounded-2xl">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Téléphone</label>
                        <input type="tel" name="telephone_dest" required placeholder="+257 -- -- --" class="w-full bg-transparent outline-none text-[#0A1F33] font-semibold">
                    </div>

                    <div class="input-box p-4 rounded-2xl">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Ville de destination</label>
                        <input type="text" name="destination" required placeholder="Ex: Gitega" class="w-full bg-transparent outline-none text-[#0A1F33] font-semibold">
                    </div>
                </div>
            </div>

            <!-- Section Colis -->
            <div class="bg-white p-6 rounded-[35px] shadow-sm border border-gray-50">
                <h3 class="text-[#0A1F33] font-black text-xs uppercase tracking-widest mb-5 flex items-center">
                    <span class="w-6 h-6 bg-[#0A1F33] text-white rounded-full flex items-center justify-center mr-2 text-[10px]">2</span>
                    Détails de l'envoi
                </h3>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="input-box p-4 rounded-2xl">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Poids (kg)</label>
                            <input type="number" step="0.1" name="poids" id="poids" required oninput="calculateTotal()" placeholder="0.5" class="w-full bg-transparent outline-none text-[#0A1F33] font-bold">
                        </div>
                        <div class="bg-[#0A1F33] p-4 rounded-2xl flex flex-col justify-center">
                            <label class="block text-[9px] font-bold text-[#4DFF88] uppercase mb-1">Total à payer</label>
                            <div class="text-white font-black text-lg"><span id="total_prix">0</span> <small class="text-[9px]">FBU</small></div>
                        </div>
                    </div>

                    <div class="input-box p-4 rounded-2xl">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Description</label>
                        <textarea name="description" rows="2" placeholder="Ex: Sac de vêtements, Documents..." class="w-full bg-transparent outline-none text-[#0A1F33] font-semibold resize-none"></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full py-6 bg-[#0A1F33] text-white font-black rounded-[30px] shadow-xl shadow-blue-900/20 hover:bg-[#162d44] transition-all uppercase tracking-[0.2em] text-xs">
                Valider l'expédition
            </button>
        </form>
    </main>

    <script>
        const rate = <?php echo $prix_par_kg; ?>;
        function calculateTotal() {
            const poids = document.getElementById('poids').value;
            const total = document.getElementById('total_prix');
            if(poids > 0) {
                total.innerText = (poids * rate).toLocaleString();
            } else {
                total.innerText = "0";
            }
        }
    </script>
</body>
</html>