<?php
session_start();
require_once 'db_config.php'; // Votre fichier de connexion PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'register') {
        $nom = htmlspecialchars($_POST['nom']);
        $tel = htmlspecialchars($_POST['telephone']);
        $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (nom, telephone, password, role) VALUES (?, ?, ?, 'client')");
        if ($stmt->execute([$nom, $tel, $pass])) {
            header("Location: login.php?msg=Compte créé");
        }
    } 
    
    elseif ($action === 'login') {
        $tel = htmlspecialchars($_POST['telephone']);
        $pass = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE telephone = ?");
        $stmt->execute([$tel]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_nom'] = $user['nom'];

            // Redirection selon le rôle
            if ($user['role'] === 'client') {
                header("Location: dashboard_client.php");
            } else {
                header("Location: dashboard_employe.php");
            }
        } else {
            header("Location: login.php?error=Identifiants incorrects");
        }
    }
}