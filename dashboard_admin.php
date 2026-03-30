<?php
session_start();
require_once 'db_config.php';

// 1. SECURITY: Admin Verification
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['user_nom'] ?? 'Administrator';
$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : null;
$tab_active = $_GET['tab'] ?? 'stats'; 

try {
    // --- DELETE ACTIONS ---
    if (isset($_GET['delete_user'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete_user']]);
        header("Location: dashboard_admin.php?tab=".$_GET['tab']."&msg=User deleted successfully");
        exit();
    }

    if (isset($_GET['delete_agence'])) {
        $pdo->prepare("DELETE FROM agences WHERE id = ?")->execute([$_GET['delete_agence']]);
        header("Location: dashboard_admin.php?tab=agences&msg=Agency deleted successfully");
        exit();
    }

    if (isset($_GET['delete_voyage'])) {
        $pdo->prepare("DELETE FROM voyages_disponibles WHERE id = ?")->execute([$_GET['delete_voyage']]);
        header("Location: dashboard_admin.php?tab=voyages&msg=Trip deleted successfully");
        exit();
    }

    if (isset($_GET['toggle_status'])) {
        $pdo->prepare("UPDATE users SET is_blocked = NOT is_blocked WHERE id = ?")->execute([$_GET['toggle_status']]);
        header("Location: dashboard_admin.php?tab=clients&msg=Status updated");
        exit();
    }

    // --- ADD ACTIONS ---
    if (isset($_POST['add_employe'])) {
        $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nom, password, role, id_agence) VALUES (?, ?, 'employe', ?)");
        $stmt->execute([$_POST['nom'], $pwd, $_POST['id_agence']]);
        header("Location: dashboard_admin.php?tab=employes&msg=New employee added");
        exit();
    }

    if (isset($_POST['add_agence'])) {
        $stmt = $pdo->prepare("INSERT INTO agences (nom_agence, ville) VALUES (?, ?)");
        $stmt->execute([$_POST['nom_agence'], $_POST['ville']]);
        header("Location: dashboard_admin.php?tab=agences&msg=New agency created");
        exit();
    }

    if (isset($_POST['add_voyage'])) {
        $stmt = $pdo->prepare("INSERT INTO voyages_disponibles (depart, destination, date_voyage, heure_depart, prix, places_totales, places_occupees, statut) VALUES (?, ?, ?, ?, ?, ?, 0, 'Open')");     
        $stmt->execute([$_POST['v_dep'], $_POST['v_dest'], $_POST['v_date'], $_POST['v_heure'], $_POST['v_prix'], $_POST['v_places']]);
        header("Location: dashboard_admin.php?tab=voyages&msg=Trip added successfully");
        exit();
    }

    // --- UPDATE ACTIONS ---
    if (isset($_POST['update_employe'])) {
        if (!empty($_POST['password'])) {
            $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET nom = ?, id_agence = ?, password = ? WHERE id = ?");
            $stmt->execute([$_POST['nom'], $_POST['id_agence'], $pwd, $_POST['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nom = ?, id_agence = ? WHERE id = ?");
            $stmt->execute([$_POST['nom'], $_POST['id_agence'], $_POST['id']]);
        }
        header("Location: dashboard_admin.php?tab=employes&msg=Employee updated");
        exit();
    }

    if (isset($_POST['update_agence'])) {
        $pdo->prepare("UPDATE agences SET nom_agence = ?, ville = ? WHERE id = ?")
            ->execute([$_POST['nom_agence'], $_POST['ville'], $_POST['id']]);
        header("Location: dashboard_admin.php?tab=agences&msg=Agency updated");
        exit();
    }

    // --- DATA RETRIEVAL ---
    $agences = $pdo->query("SELECT * FROM agences ORDER BY nom_agence ASC")->fetchAll(PDO::FETCH_ASSOC);
    $employes = $pdo->query("SELECT u.*, a.nom_agence FROM users u LEFT JOIN agences a ON u.id_agence = a.id WHERE u.role = 'employe' ORDER BY u.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $clients = $pdo->query("SELECT * FROM users WHERE role = 'client' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $voyages = $pdo->query("SELECT * FROM voyages_disponibles ORDER BY date_voyage DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // --- STATISTICS ---
    $count_clients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
    $count_tickets = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut IN ('Confirmé', 'Accepté')")->fetchColumn();
    $count_colis = $pdo->query("SELECT COUNT(*) FROM demandes_courrier")->fetchColumn();

    $rev_t = $pdo->query("SELECT SUM(r.nombre_places * v.prix) as total FROM reservations r JOIN voyages_disponibles v ON r.voyage_id = v.id WHERE r.statut IN ('Confirmé', 'Accepté')")->fetch();
    $rev_c = $pdo->query("SELECT SUM(prix_total) as total FROM demandes_courrier WHERE statut != 'Refusé'")->fetch();
    
    $revenu_total = ($rev_t['total'] ?? 0) + ($rev_c['total'] ?? 0);

    $courriers = $pdo->query("SELECT d.*, a1.nom_agence as nom_dep, a2.nom_agence as nom_dest 
                              FROM demandes_courrier d 
                              LEFT JOIN agences a1 ON d.agence_depart_id = a1.id 
                              LEFT JOIN agences a2 ON d.agence_destination_id = a2.id 
                              ORDER BY d.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>TransExpress Admin Dashboard</title>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F4F7FE; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .sidebar-btn.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #4DFF88; color: #4DFF88; }
        #alert-msg { transition: opacity 0.5s ease-out; }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-72 bg-[#0A1F33] sticky top-0 h-screen p-6 text-white hidden lg:block shadow-2xl">
        <div class="mb-10 text-center text-2xl font-black italic text-[#4DFF88]">TRANS<span class="text-white">EXPRESS</span></div>
        <nav class="space-y-2">
            <button onclick="showTab('stats')" id="btn-stats" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-chart-line w-5"></i> Dashboard</button>
            <button onclick="showTab('voyages')" id="btn-voyages" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-bus w-5"></i> Tickets</button>
            <button onclick="showTab('employes')" id="btn-employes" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-user-tie w-5"></i> Staff</button>
            <button onclick="showTab('clients')" id="btn-clients" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-users w-5"></i> Clients</button>
            <button onclick="showTab('agences')" id="btn-agences" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-map-marked-alt w-5"></i> Agencies</button>
            <button onclick="showTab('courriers')" id="btn-courriers" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-box-open w-5"></i> Shipments</button>
            <a href="logout.php" class="block p-4 text-red-400 font-bold text-sm mt-10"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="flex-1 p-8 lg:p-12">
        <!-- HEADER WITH USER NAME -->
        <div class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-sm font-bold text-gray-400 uppercase tracking-widest">Welcome back,</h1>
                <p class="text-xl font-black text-[#0A1F33]"><?php echo htmlspecialchars($admin_name); ?> <span class="text-green-500">●</span></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-black text-gray-800">Admin Mode</p>
                    <p class="text-[10px] text-gray-400 font-bold italic">System Control</p>
                </div>
                <div class="h-12 w-12 bg-white rounded-2xl flex items-center justify-center shadow-sm border border-gray-100 text-[#0A1F33]">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>

        <?php if($msg): ?> 
            <div id="alert-msg" class="mb-6 p-4 bg-green-500 text-white rounded-2xl font-bold italic shadow-lg">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $msg; ?>
            </div> 
        <?php endif; ?>

        <!-- SECTION: DASHBOARD -->
        <section id="stats" class="tab-content active">
            <h2 class="text-4xl font-black text-[#0A1F33] mb-8 italic">Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-6 rounded-[30px] shadow-sm border border-gray-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Revenue</p>
                        <h3 class="text-2xl font-black text-gray-800"><?php echo number_format($revenu_total, 0, '.', ' '); ?> <span class="text-xs text-green-500">FBU</span></h3>
                    </div>
                    <div class="h-12 w-12 bg-green-50 rounded-2xl flex items-center justify-center text-green-500"><i class="fas fa-wallet fa-lg"></i></div>
                </div>
                <div class="bg-white p-6 rounded-[30px] shadow-sm border border-gray-100 flex items-center justify-between">
                    <div><p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Clients</p>
                    <h3 class="text-2xl font-black text-gray-800"><?php echo $count_clients; ?></h3></div>
                    <div class="h-12 w-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-500"><i class="fas fa-users fa-lg"></i></div>
                </div>
                <div class="bg-white p-6 rounded-[30px] shadow-sm border border-gray-100 flex items-center justify-between">
                    <div><p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Tickets Sold</p>
                    <h3 class="text-2xl font-black text-gray-800"><?php echo $count_tickets; ?></h3></div>
                    <div class="h-12 w-12 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-500"><i class="fas fa-ticket-alt fa-lg"></i></div>
                </div>
                <div class="bg-white p-6 rounded-[30px] shadow-sm border border-gray-100 flex items-center justify-between">
                    <div><p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Shipments</p>
                    <h3 class="text-2xl font-black text-gray-800"><?php echo $count_colis; ?></h3></div>
                    <div class="h-12 w-12 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-500"><i class="fas fa-box fa-lg"></i></div>
                </div>
            </div>
        </section>

        <!-- SECTION: VOYAGES -->
        <section id="voyages" class="tab-content">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-black text-[#0A1F33]">Trip Management</h2>
                <!-- <button onclick="toggleModal('modalVoyage')" class="bg-indigo-600 text-white px-6 py-4 rounded-2xl font-bold text-xs shadow-lg hover:bg-indigo-700 uppercase">Create Trip</button> -->
            </div>
            <div class="bg-white rounded-[30px] overflow-hidden shadow-sm border border-gray-100">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-[10px] font-black uppercase text-gray-400">
                        <tr><th class="p-6">Route</th><th class="p-6">Date & Time</th><th class="p-6">Price</th><th class="p-6 text-center">Action</th></tr>
                    </thead>
                    <tbody class="divide-y text-sm">
                        <?php foreach($voyages as $v): ?>
                        <tr>
                            <td class="p-6 font-bold uppercase italic"><?php echo htmlspecialchars($v['depart']); ?> → <?php echo htmlspecialchars($v['destination']); ?></td>
                            <td class="p-6"><?php echo date('M d, Y', strtotime($v['date_voyage'])); ?> at <?php echo $v['heure_depart']; ?></td>
                            <td class="p-6 font-bold text-green-600"><?php echo number_format($v['prix'], 0, '.', ' '); ?> FBU</td>
                            <!-- <td class="p-6"> -->
                               <!-- <?php echo ($v['places_totales'] - $v['places_occupees']); ?> / <?php echo $v['places_totales']; ?> -->
                            <!-- </td> -->
                            <td class="p-6 text-center">
                                <a href="?delete_voyage=<?php echo $v['id']; ?>" onclick="return confirm('Delete this trip?')" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- SECTION: STAFF -->
        <section id="employes" class="tab-content">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-black text-[#0A1F33]">Staff Management</h2>
                <button onclick="toggleModal('modalEmp')" class="bg-blue-600 text-white px-6 py-4 rounded-2xl font-bold text-xs shadow-lg hover:bg-blue-700 uppercase">Add Staff</button>
            </div>
            <div class="bg-white rounded-[30px] overflow-hidden shadow-sm border border-gray-100">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-[10px] font-black uppercase text-gray-400">
                        <tr><th class="p-6">Name</th><th class="p-6">Agency</th><th class="p-6 text-center">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y text-sm italic">
                        <?php foreach($employes as $e): ?>
                        <tr>
                            <td class="p-6 font-bold"><?php echo htmlspecialchars($e['nom']); ?></td>
                            <td class="p-6 font-bold text-blue-600"><?php echo htmlspecialchars($e['nom_agence'] ?? 'Not assigned'); ?></td>
                            <td class="p-6 text-center space-x-3">
                                <button onclick="openEditEmp('<?php echo $e['id']; ?>', '<?php echo addslashes($e['nom']); ?>', '<?php echo $e['id_agence']; ?>')" class="text-blue-500"><i class="fas fa-edit"></i></button>
                                <a href="?delete_user=<?php echo $e['id']; ?>&tab=employes" onclick="return confirm('Delete user?')" class="text-red-400"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- SECTION: CLIENTS -->
        <section id="clients" class="tab-content">
            <h2 class="text-3xl font-black text-[#0A1F33] mb-8 italic">Client Directory</h2>
            <div class="bg-white rounded-[30px] overflow-hidden shadow-sm border border-gray-100">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-black uppercase text-gray-400">
                        <tr><th class="p-6">Client Name</th><th class="p-6">Status</th><th class="p-6 text-center">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($clients as $c): ?>
                        <tr>
                            <td class="p-6 font-bold"><?php echo htmlspecialchars($c['nom']); ?></td>
                            <td class="p-6">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo $c['is_blocked'] ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'; ?>">
                                    <?php echo $c['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                </span>
                            </td>
                            <td class="p-6 text-center space-x-4 italic">
                                <a href="?toggle_status=<?php echo $c['id']; ?>" class="font-bold underline <?php echo $c['is_blocked'] ? 'text-green-500' : 'text-orange-500'; ?> text-xs">
                                    <?php echo $c['is_blocked'] ? 'Unblock' : 'Block'; ?>
                                </a>
                                <a href="?delete_user=<?php echo $c['id']; ?>&tab=clients" class="text-red-400" onclick="return confirm('Permanently delete this client?')"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- SECTION: AGENCIES -->
        <section id="agences" class="tab-content">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-black text-[#0A1F33]">Agencies</h2>
                <button onclick="toggleModal('modalAg')" class="bg-orange-500 text-white px-6 py-4 rounded-2xl font-bold text-xs shadow-lg uppercase hover:bg-orange-600">New Agency</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($agences as $ag): ?>
                <div class="bg-white p-6 rounded-[25px] border flex justify-between items-center">
                    <div>
                        <h4 class="font-black text-gray-800 uppercase italic"><?php echo htmlspecialchars($ag['nom_agence']); ?></h4>
                        <p class="text-xs font-bold text-orange-500"><?php echo htmlspecialchars($ag['ville']); ?></p>
                    </div>
                    <div class="flex gap-4">
                        <button onclick="openEditAg('<?php echo $ag['id']; ?>', '<?php echo addslashes($ag['nom_agence']); ?>', '<?php echo addslashes($ag['ville']); ?>')" class="text-blue-500"><i class="fas fa-edit"></i></button>
                        <a href="?delete_agence=<?php echo $ag['id']; ?>" class="text-red-400" onclick="return confirm('Delete agency?')"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SECTION: SHIPMENTS -->
        <section id="courriers" class="tab-content">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-black text-[#0A1F33]">Shipment Tracking</h2>
            </div>
            <div class="bg-white rounded-[30px] overflow-hidden shadow-sm border">
                <table class="w-full text-left text-sm italic">
                    <thead class="bg-gray-50 text-[10px] font-black uppercase text-gray-400">
                        <tr><th class="p-6">Route</th><th class="p-6">Recipient</th><th class="p-6">Tracking Code</th><th class="p-6 text-center">Status</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($courriers as $c): ?>
                        <tr>
                            <td class="p-6 font-bold"><?php echo htmlspecialchars($c['nom_dep']); ?> → <?php echo htmlspecialchars($c['nom_dest']); ?></td>
                            <td class="p-6 font-bold"><?php echo htmlspecialchars($c['destinataire']); ?></td>
                            <td class="p-6 font-mono text-blue-600 font-bold"><?php echo htmlspecialchars($c['numero_unique']); ?></td>
                            <td class="p-6 text-center">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo ($c['statut'] == 'Livré') ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600'; ?>">
                                    <?php echo htmlspecialchars($c['statut']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- MODAL: ADD TRIP -->
    <div id="modalVoyage" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-[#0A1F33]/60 backdrop-blur-md">
        <div class="bg-white w-full max-w-lg rounded-[35px] p-10 shadow-2xl">
            <h3 class="text-2xl font-black mb-6 italic text-indigo-600">Schedule a Trip</h3>
            <form method="POST" class="grid grid-cols-2 gap-4">
                <input type="text" name="v_dep" placeholder="Departure City" required class="col-span-1 bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="text" name="v_dest" placeholder="Destination City" required class="col-span-1 bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="date" name="v_date" required class="col-span-1 bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="time" name="v_heure" required class="col-span-1 bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="number" name="v_prix" placeholder="Price (FBU)" required class="col-span-1 bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="number" name="v_places" placeholder="Max Seats" required class="col-span-1 bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <div class="col-span-2 flex gap-4 pt-4">
                    <button type="button" onclick="toggleModal('modalVoyage')" class="flex-1 font-bold text-gray-400 text-xs uppercase">Cancel</button>
                    <button type="submit" name="add_voyage" class="flex-1 bg-indigo-600 text-white font-bold py-4 rounded-2xl shadow-xl uppercase">Publish</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD STAFF -->
    <div id="modalEmp" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-[#0A1F33]/60 backdrop-blur-md">
        <div class="bg-white w-full max-w-md rounded-[35px] p-10 shadow-2xl">
            <h3 class="text-2xl font-black mb-6 italic text-blue-600">New Staff Member</h3>
            <form method="POST" class="space-y-4">
                <input type="text" name="nom" placeholder="Full Name" required class="w-full bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="password" name="password" placeholder="Password" required class="w-full bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <select name="id_agence" required class="w-full bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                    <option value="">Select Agency...</option>
                    <?php foreach($agences as $ag): ?><option value="<?php echo $ag['id']; ?>"><?php echo htmlspecialchars($ag['nom_agence']); ?></option><?php endforeach; ?>
                </select>
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="toggleModal('modalEmp')" class="flex-1 font-bold text-gray-400 text-xs uppercase">Cancel</button>
                    <button type="submit" name="add_employe" class="flex-1 bg-blue-600 text-white font-bold py-4 rounded-2xl shadow-xl uppercase">Save Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD AGENCY -->
    <div id="modalAg" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-[#0A1F33]/60 backdrop-blur-md">
        <div class="bg-white w-full max-w-md rounded-[35px] p-10">
            <h3 class="text-2xl font-black mb-6 italic text-orange-600">New Agency</h3>
            <form method="POST" class="space-y-4">
                <input type="text" name="nom_agence" placeholder="Agency Name" required class="w-full bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <input type="text" name="ville" placeholder="City / Province" required class="w-full bg-gray-50 rounded-2xl p-4 border outline-none font-semibold">
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="toggleModal('modalAg')" class="flex-1 font-bold text-gray-400 text-xs uppercase">Cancel</button>
                    <button type="submit" name="add_agence" class="flex-1 bg-orange-500 text-white font-bold py-4 rounded-2xl shadow-xl uppercase">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT STAFF -->
    <div id="modalEditEmp" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-[#0A1F33]/60 backdrop-blur-sm">
        <div class="bg-white w-full max-w-md rounded-3xl p-8">
            <h3 class="text-2xl font-black mb-6 italic">Edit Staff</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="id" id="edit_emp_id">
                <input type="text" name="nom" id="edit_emp_nom" required class="w-full p-4 bg-gray-50 rounded-xl border outline-none">
                <input type="password" name="password" placeholder="New Password (Optional)" class="w-full p-4 bg-gray-50 rounded-xl border outline-none">
                <select name="id_agence" id="edit_emp_agence" class="w-full p-4 bg-gray-50 rounded-xl border outline-none">
                    <?php foreach($agences as $ag): ?><option value="<?php echo $ag['id']; ?>"><?php echo htmlspecialchars($ag['nom_agence']); ?></option><?php endforeach; ?>
                </select>
                <div class="flex gap-3"><button type="button" onclick="toggleModal('modalEditEmp')" class="flex-1 text-gray-400 font-bold uppercase text-xs">Back</button>
                <button type="submit" name="update_employe" class="flex-1 bg-blue-600 text-white font-bold py-4 rounded-xl uppercase">Update</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT AGENCY -->
    <div id="modalEditAg" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-[#0A1F33]/60 backdrop-blur-sm">
        <div class="bg-white w-full max-w-md rounded-3xl p-8 shadow-2xl">
            <h3 class="text-2xl font-black mb-6 italic">Edit Agency</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="id" id="edit_ag_id">
                <input type="text" name="nom_agence" id="edit_ag_nom" required class="w-full p-4 bg-gray-50 rounded-xl border outline-none">
                <input type="text" name="ville" id="edit_ag_ville" required class="w-full p-4 bg-gray-50 rounded-xl border outline-none">
                <div class="flex gap-3"><button type="button" onclick="toggleModal('modalEditAg')" class="flex-1 text-gray-400 font-bold text-xs uppercase">Back</button>
                <button type="submit" name="update_agence" class="flex-1 bg-orange-500 text-white font-bold py-4 rounded-xl shadow-lg uppercase">Modify</button></div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching function
        function showTab(id) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
            
            const target = document.getElementById(id);
            if(target) target.classList.add('active');
            
            const btn = document.getElementById('btn-' + id);
            if(btn) btn.classList.add('active');
            
            const url = new URL(window.location);
            url.searchParams.set('tab', id);
            window.history.pushState({}, '', url);
        }

        function toggleModal(id) { document.getElementById(id).classList.toggle('hidden'); }

        function openEditEmp(id, nom, id_agence) {
            document.getElementById('edit_emp_id').value = id;
            document.getElementById('edit_emp_nom').value = nom;
            document.getElementById('edit_emp_agence').value = id_agence;
            toggleModal('modalEditEmp');
        }

        function openEditAg(id, nom, ville) {
            document.getElementById('edit_ag_id').value = id;
            document.getElementById('edit_ag_nom').value = nom;
            document.getElementById('edit_ag_ville').value = ville;
            toggleModal('modalEditAg');
        }

        // Auto-hide alert messages after 5 seconds
        window.onload = () => {
            const alertMsg = document.getElementById('alert-msg');
            if (alertMsg) {
                setTimeout(() => {
                    alertMsg.style.opacity = '0';
                    setTimeout(() => alertMsg.remove(), 500); // Remove from DOM after fade
                }, 5000);
            }

            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'stats';
            showTab(activeTab);
        }
    </script>
</body>
</html>