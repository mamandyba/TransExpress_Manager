<?php
session_start();
require_once 'db_config.php';
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On récupère le NOM au lieu du téléphone
    $nom_utilisateur = htmlspecialchars($_POST['nom_utilisateur']);
    $password = $_POST['password'];

    // Recherche par NOM dans la base de données
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nom = ?");
    $stmt->execute([$nom_utilisateur]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_role'] = $user['role'];

        // Redirection intelligente selon le rôle
        if ($user['role'] === 'client') {
            header("Location: dashboard_client.php");
        } elseif ($user['role'] === 'admin') {
            header("Location: dashboard_admin.php");
        } else {
            header("Location: dashboard_employe.php");
        }
        exit();
    } else {
        $error = "<div class='bg-red-100 text-red-700 p-3 rounded-xl text-center mb-4 text-sm font-medium'>Nom d'utilisateur ou mot de passe incorrect.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title>Connexion - TransExpress</title>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-shadow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05); }
        .input-focus:focus { border-color: #0A1F33; box-shadow: 0 0 0 3px rgba(10, 31, 51, 0.05); }
    </style>
</head>
<body class="bg-[#F8FAFC] flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md bg-white rounded-[40px] custom-shadow p-10">
        
        <!-- Logo & Header -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-[#0A1F33] rounded-2xl mb-4 shadow-lg">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="white">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>
            <h1 class="text-[#0A1F33] text-3xl font-bold tracking-tight">Bienvenue</h1>
            <p class="text-gray-400 mt-2 font-medium">Entrez vos identifiants pour continuer</p>
        </div>

        <!-- Messages d'alerte -->
        <?php 
            if(isset($_GET['success'])) echo "<div class='bg-green-100 text-green-700 p-3 rounded-xl text-center mb-4 text-sm font-medium'>Inscription réussie ! Connectez-vous.</div>";
            echo $error; 
        ?>

        <form action="" method="POST" class="space-y-6">
            <!-- Champ Nom d'utilisateur -->
            <div>
                <label class="block text-xs font-bold text-[#0A1F33] uppercase tracking-wider mb-2 ml-1">Nom d'utilisateur</label>
                <div class="relative">
                    <input type="text" name="nom_utilisateur" required 
                        class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 outline-none input-focus transition-all text-[#0A1F33]" 
                        placeholder="Votre nom complet">
                </div>
            </div>

            <!-- Champ Mot de passe -->
            <div class="relative">
                <div class="flex justify-between items-center mb-2 ml-1">
                    <label class="text-xs font-bold text-[#0A1F33] uppercase tracking-wider">Mot de passe</label>
                    <a href="#" class="text-xs text-gray-400 hover:text-[#0A1F33]">Oublié ?</a>
                </div>
                <div class="relative">
                    <input type="password" id="password_login" name="password" required 
                        class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 outline-none input-focus transition-all text-[#0A1F33]" 
                        placeholder="••••••••">
                    
                    <button type="button" onclick="togglePasswordLogin()" class="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#0A1F33]">
                        <svg id="eye-icon-login" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Bouton Connexion -->
            <button type="submit" class="w-full py-5 bg-[#0A1F33] text-white font-bold rounded-2xl hover:bg-[#162d44] transition-all shadow-lg shadow-blue-900/10 transform active:scale-[0.98]">
                SE CONNECTER
            </button>
        </form>

        <!-- Footer redirection -->
        <div class="text-center mt-10">
            <p class="text-sm text-gray-500 font-medium">
                Nouveau ici ? <br>
                <a href="register.php" class="text-[#4DFF88] font-bold hover:underline">Créer un compte client</a>
            </p>
        </div>
    </div>

    <script>
        function togglePasswordLogin() {
            const passwordInput = document.getElementById('password_login');
            const eyeIcon = document.getElementById('eye-icon-login');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.888 9.888L3 3m18 18l-6.888-6.888m4.43-4.43a10.05 10.05 0 011.542 3.318c-1.274 4.057-5.064 7-9.542 7-1.305 0-2.547-.245-3.695-.693L13.875 18.825z" />';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }
    </script>
</body>
</html>