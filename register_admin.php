<?php
session_start();
require_once 'db_config.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars($_POST['nom']);
    $email = htmlspecialchars($_POST['email']);
    // On hache le mot de passe pour la sécurité
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'admin'; 

    try {
        // 1. Vérifier si l'email existe déjà
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if ($check->rowCount() > 0) {
            $message = "<div class='bg-red-500/10 text-red-500 p-4 rounded-2xl mb-6 text-sm font-bold border border-red-500/20'>Cet email est déjà utilisé.</div>";
        } else {
            // 2. Insertion (Correction du nom de colonne : password au lieu de mot_de_passe)
            $stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$nom, $email, $hashed_password, $role])) {
                $message = "<div class='bg-[#4DFF88]/20 text-[#4DFF88] p-4 rounded-2xl mb-6 text-sm font-bold border border-[#4DFF88]/30 text-center'>
                                Compte ADMIN créé avec succès ! <br>
                                <a href='login.php' class='underline font-black text-white'>Se connecter ici</a>
                            </div>";
            }
        }
    } catch (PDOException $e) {
        // En cas d'erreur, on affiche le message technique pour deboguer
        $message = "<div class='bg-red-500/10 text-red-500 p-4 rounded-2xl mb-6 text-xs border border-red-500/20'>Erreur SQL : " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <title>Créer un Admin - TransExpress</title>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0A1F33; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.08); }
        input:focus { border-color: #4DFF88 !important; box-shadow: 0 0 20px rgba(77, 255, 136, 0.1); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">

    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-block bg-[#4DFF88] p-3 rounded-2xl mb-4 shadow-lg shadow-[#4DFF88]/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#0A1F33]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-white text-3xl font-black tracking-tighter italic uppercase">Admin<span class="text-[#4DFF88]">Access</span></h1>
            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-[0.3em] mt-2">Enregistrement du personnel</p>
        </div>

        <div class="glass p-8 rounded-[45px] shadow-2xl relative overflow-hidden">
            <!-- Décoration subtile -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-[#4DFF88]/5 rounded-full blur-3xl"></div>

            <?php echo $message; ?>

            <form action="" method="POST" class="space-y-5 relative z-10">
                <!-- Nom -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Nom du gestionnaire</label>
                    <input type="text" name="nom" required placeholder="Ex: Admin_TX" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl text-white outline-none transition-all placeholder:text-gray-700 font-semibold">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Email Professionnel</label>
                    <input type="email" name="email" required placeholder="admin@transexpress.com" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl text-white outline-none transition-all placeholder:text-gray-700 font-semibold">
                </div>

                <!-- Mot de passe -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Clé d'accès</label>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl text-white outline-none transition-all placeholder:text-gray-700 font-semibold">
                </div>

                <div class="py-2">
                    <button type="submit" class="w-full py-5 bg-[#4DFF88] text-[#0A1F33] font-black rounded-2xl shadow-xl shadow-[#4DFF88]/10 hover:bg-[#3ee67a] active:scale-95 transition-all uppercase tracking-widest text-xs">
                        Générer le compte
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center mt-8">
            <a href="login.php" class="text-gray-600 text-[10px] font-black hover:text-[#4DFF88] transition-colors uppercase tracking-[0.2em] italic">
                ← Retour à la connexion
            </a>
        </p>
    </div>

</body>
</html> 