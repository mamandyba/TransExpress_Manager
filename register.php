<?php
require_once 'db_config.php';
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars($_POST['nom']);
    $telephone = htmlspecialchars($_POST['telephone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $check = $pdo->prepare("SELECT id FROM users WHERE telephone = ?");
    $check->execute([$telephone]);
    
    if ($check->rowCount() > 0) {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded-xl text-center mb-4 text-sm'>Ce numéro est déjà utilisé.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (nom, telephone, password, role) VALUES (?, ?, ?, 'client')");
        if ($stmt->execute([$nom, $telephone, $password])) {
            header("Location: login.php?success=1");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font pour un look plus pro -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title>Inscription - TransExpress</title>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-shadow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05); }
        .input-focus:focus { border-color: #4DFF88; box-shadow: 0 0 0 3px rgba(77, 255, 136, 0.1); }
    </style>
</head>
<body class="bg-[#F8FAFC] flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md bg-white rounded-[40px] custom-shadow p-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-[#0A1F33] rounded-2xl mb-4">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
            </div>
            <h1 class="text-[#0A1F33] text-3xl font-bold tracking-tight">Créer un compte</h1>
            <p class="text-gray-400 mt-2">Rejoignez TransExpress Manager</p>
        </div>

        <?php echo $message; ?>

        <form action="" method="POST" class="space-y-6">
            <div>
                <label class="block text-xs font-bold text-[#0A1F33] uppercase tracking-wider mb-2 ml-1">Nom complet</label>
                <input type="text" name="nom" required 
                    class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 outline-none input-focus transition-all text-[#0A1F33]" 
                    placeholder="Jean-Claude Bakame">
            </div>

            <div>
                <label class="block text-xs font-bold text-[#0A1F33] uppercase tracking-wider mb-2 ml-1">Téléphone</label>
                <input type="tel" name="telephone" required 
                    class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 outline-none input-focus transition-all text-[#0A1F33]" 
                    placeholder="+257 -- -- -- --">
            </div>

            <div class="relative">
                <label class="block text-xs font-bold text-[#0A1F33] uppercase tracking-wider mb-2 ml-1">Mot de passe</label>
                <input type="password" id="password" name="password" required 
                    class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 outline-none input-focus transition-all text-[#0A1F33]" 
                    placeholder="••••••••">
                <!-- Bouton Afficher/Masquer -->
                <button type="button" onclick="togglePassword()" class="absolute right-5 top-[46px] text-gray-400 hover:text-[#0A1F33]">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>

            <button type="submit" class="w-full py-5 bg-[#0A1F33] text-white font-bold rounded-2xl hover:bg-[#162d44] transition-all shadow-lg transform active:scale-[0.98]">
                S'INSCRIRE
            </button>
        </form>

        <div class="text-center mt-8">
            <p class="text-sm text-gray-500 font-medium">
                Déjà un compte ? <a href="login.php" class="text-[#4DFF88] font-bold hover:underline">Se connecter</a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
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