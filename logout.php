<?php
// 1. Initialiser la session
session_start();

// 2. Détruire toutes les variables de session
$_SESSION = array();

// 3. Si vous voulez détruire complètement la session, effacez également le cookie de session.
// Note : Cela détruira la session et pas seulement les données de session !
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Détruire la session côté serveur
session_destroy();

// 5. Rediriger vers la page de connexion (ou l'accueil)
header("Location: login.php");
exit();
?>