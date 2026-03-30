<?php
session_start();
require_once 'db_config.php';

// 1. SECURITY: ACCESS CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employe') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 2. FETCH EMPLOYEE AND BRANCH INFO
    $stmt_user = $pdo->prepare("SELECT a.nom_agence, u.id_agence FROM users u LEFT JOIN agences a ON u.id_agence = a.id WHERE u.id = ?");
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch();
    
    $current_branch_name = $user_info['nom_agence'] ?? 'Unknown Branch';
    $current_branch_id = $user_info['id_agence'];

    $other_branches = $pdo->query("SELECT id, nom_agence FROM agences WHERE id != '$current_branch_id'")->fetchAll();

    // 3. ACTION HANDLING
    if (isset($_POST['action_colis'])) {
        $action = $_POST['action_colis'];
        $colis_id = $_POST['colis_id'];
        $pdo->prepare("UPDATE demandes_courrier SET statut = ? WHERE id = ?")->execute([$action, $colis_id]);
        header("Location: dashboard_employe.php?msg=Parcel updated: " . $action);
        exit();
    }

    if (isset($_POST['delete_colis'])) {
        $pdo->prepare("DELETE FROM demandes_courrier WHERE id = ?")->execute([$_POST['colis_id']]);
        header("Location: dashboard_employe.php?msg=Entry deleted");
        exit();
    }

    if (isset($_POST['action_ticket'])) {
        $res_id = $_POST['res_id'];
        $action = $_POST['action_ticket'];
        if ($action === 'Rejected') {
            $stmt_info = $pdo->prepare("SELECT voyage_id, nombre_places FROM reservations WHERE id = ?");
            $stmt_info->execute([$res_id]);
            $res = $stmt_info->fetch();
            if ($res) {
                $pdo->prepare("UPDATE voyages_disponibles SET places_occupees = places_occupees - ? WHERE id = ?")
                    ->execute([$res['nombre_places'], $res['voyage_id']]);
            }
        }
        $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?")->execute([$action, $res_id]);
        header("Location: dashboard_employe.php?msg=Ticket status updated");
        exit();
    }

    if (isset($_POST['add_trip'])) {
        $stmt = $pdo->prepare("INSERT INTO voyages_disponibles (depart, destination, heure_depart, date_voyage, places_totales, places_occupees, prix) VALUES (?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([$current_branch_name, $_POST['dest'], $_POST['time'], $_POST['date'], $_POST['seats'], $_POST['price']]);
        header("Location: dashboard_employe.php?msg=Trip published");
        exit();
    }

    // 4. DATA LOADING (FILTERED)
    
    // NEW REQUESTS (Outgoing): Show only if Pending or Accepted.
    $stmt_new = $pdo->prepare("SELECT d.*, a.nom_agence as ag_dest FROM demandes_courrier d JOIN agences a ON d.agence_destination_id = a.id WHERE d.agence_depart_id = ? AND (d.statut = 'Pending' OR d.statut = 'Accepted' OR d.statut IS NULL OR d.statut = '')");
    $stmt_new->execute([$current_branch_id]);
    $parcels_new = $stmt_new->fetchAll(PDO::FETCH_ASSOC);

    // INCOMING
    $stmt_inc = $pdo->prepare("SELECT d.*, a.nom_agence as ag_dep FROM demandes_courrier d JOIN agences a ON d.agence_depart_id = a.id WHERE d.agence_destination_id = ? AND d.statut = 'In Transit'");
    $stmt_inc->execute([$current_branch_id]);
    $parcels_incoming = $stmt_inc->fetchAll(PDO::FETCH_ASSOC);

    // HISTORY
    $stmt_hist = $pdo->prepare("SELECT d.*, a1.nom_agence as ag_dep, a2.nom_agence as ag_dest FROM demandes_courrier d JOIN agences a1 ON d.agence_depart_id = a1.id JOIN agences a2 ON d.agence_destination_id = a2.id WHERE d.agence_depart_id = ? OR d.agence_destination_id = ? ORDER BY d.id DESC");
    $stmt_hist->execute([$current_branch_id, $current_branch_id]);
    $parcel_history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

    // TRIPS AND RESERVATIONS
    $trips = $pdo->prepare("SELECT *, (places_totales - places_occupees) as available FROM voyages_disponibles WHERE depart = ? AND date_voyage >= CURDATE() ORDER BY date_voyage ASC");
    $trips->execute([$current_branch_name]);
    $trips = $trips->fetchAll(PDO::FETCH_ASSOC);

    $reservations = $pdo->prepare("SELECT r.*, v.destination, u.nom as client_name, u.telephone as client_tel FROM reservations r JOIN voyages_disponibles v ON r.voyage_id = v.id JOIN users u ON r.client_id = u.id WHERE v.depart = ? ORDER BY r.id DESC");
    $reservations->execute([$current_branch_name]);
    $reservations = $reservations->fetchAll(PDO::FETCH_ASSOC);

    $clients = $pdo->query("SELECT * FROM users WHERE role = 'client' ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Employee - TransExpress</title>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F4F7FE; color: #0A1F33; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
        .sidebar-btn.active { background: rgba(77, 255, 136, 0.1); border-left: 4px solid #4DFF88; color: #4DFF88; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-72 bg-[#0A1F33] sticky top-0 h-screen p-6 text-white hidden lg:flex flex-col shadow-2xl">
        <div class="mb-10 px-2">
            <h1 class="text-2xl font-black italic text-[#4DFF88]">TRANS<span class="text-white">EXPRESS</span></h1>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Manager Space</p>
        </div>
        <div class="bg-white/5 p-4 rounded-2xl mb-8 border border-white/10">
            <p class="text-[10px] text-gray-400 uppercase font-bold">Current Branch</p>
            <p class="text-sm font-bold truncate"><i class="fas fa-building text-[#4DFF88] mr-2"></i><?php echo htmlspecialchars($current_branch_name); ?></p>
        </div>
        <nav class="flex-1 space-y-1">
            <button onclick="showTab('planning')" id="btn-planning" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-bus"></i> Trips</button>
            <button onclick="showTab('tickets')" id="btn-tickets" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-users"></i> Passengers</button>
            <button onclick="showTab('clients')" id="btn-clients" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-address-book"></i> Clients</button>
            <div class="pt-6 pb-2 px-4 text-[10px] font-black uppercase text-gray-500 tracking-widest">Parcel Flow</div>
            <button onclick="showTab('colis_demandes')" id="btn-colis_demandes" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-plus-circle"></i> To Process</button>
            <button onclick="showTab('colis_entrants')" id="btn-colis_entrants" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-box-open"></i> To Deliver</button>
            <button onclick="showTab('historique')" id="btn-historique" class="sidebar-btn w-full flex items-center gap-4 p-4 rounded-xl font-bold text-sm text-left"><i class="fas fa-history"></i> History</button>
        </nav>
        <a href="logout.php" class="p-4 text-red-400 font-bold text-sm flex items-center gap-3 hover:bg-red-500/10 rounded-xl transition-all"><i class="fas fa-power-off"></i> Logout</a>
    </aside>

    <main class="flex-1 p-8 lg:p-12 overflow-y-auto">
        
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 bg-[#4DFF88]/20 border border-[#4DFF88] text-[#0A1F33] p-4 rounded-2xl font-bold text-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-green-600"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <!-- SECTION: PLANNING -->
        <section id="planning" class="tab-content active">
            <h2 class="text-3xl font-black italic mb-8 uppercase">Trip Planning</h2>
            <div class="bg-white p-8 rounded-[35px] shadow-sm border border-gray-100 mb-10">
                <h3 class="font-black text-sm uppercase mb-6 tracking-widest text-blue-600">Register New Departure</h3>
                <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <select name="dest" class="bg-gray-50 p-4 rounded-2xl text-sm border-none font-bold" required>
                        <option value="">Destination...</option>
                        <?php foreach($other_branches as $ag): ?>
                            <option value="<?php echo $ag['nom_agence']; ?>"><?php echo $ag['nom_agence']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="time" name="time" class="bg-gray-50 p-4 rounded-2xl text-sm border-none" required>
                    <input type="date" name="date" class="bg-gray-50 p-4 rounded-2xl text-sm border-none" required>
                    <input type="number" name="seats" placeholder="Seats" class="bg-gray-50 p-4 rounded-2xl text-sm border-none" required>
                    <input type="number" name="price" placeholder="Price FBU" class="bg-gray-50 p-4 rounded-2xl text-sm border-none" required>
                    <button type="submit" name="add_trip" class="bg-[#0A1F33] text-white rounded-2xl font-black text-[10px] uppercase">Publish</button>
                </form>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach($trips as $v): ?>
                    <div class="bg-white p-6 rounded-[35px] shadow-sm border border-gray-100">
                        <div class="flex justify-between mb-4">
                            <span class="bg-blue-100 text-blue-600 px-4 py-1 rounded-full text-[10px] font-black uppercase"><?php echo $v['heure_depart']; ?></span>
                            <span class="text-[10px] font-bold text-gray-400"><?php echo $v['date_voyage']; ?></span>
                        </div>
                        <h4 class="text-xl font-black mb-1"><?php echo $v['destination']; ?></h4>
                        <p class="text-sm font-bold text-blue-600 mb-4"><?php echo number_format($v['prix'], 0, ',', ' '); ?> FBU</p>
                        <div class="pt-4 border-t border-dashed flex justify-between items-center">
                            <span class="text-xs font-bold text-gray-500 italic"><?php echo $v['available']; ?> seats left</span>
                            <i class="fas fa-bus text-gray-200"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SECTION: PASSENGERS -->
        <section id="tickets" class="tab-content">
            <h2 class="text-3xl font-black italic mb-8 uppercase">Passenger Reservations</h2>
            <div class="bg-white rounded-[35px] overflow-hidden shadow-sm border border-gray-100">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-6 text-[10px] font-black uppercase text-gray-400">Client</th>
                            <th class="p-6 text-[10px] font-black uppercase text-gray-400">Destination</th>
                            <th class="p-6 text-[10px] font-black uppercase text-gray-400">Seats</th>
                            <th class="p-6 text-[10px] font-black uppercase text-gray-400">Status</th>
                            <th class="p-6 text-[10px] font-black uppercase text-gray-400 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach($reservations as $r): ?>
                        <tr>
                            <td class="p-6">
                                <p class="font-black text-sm"><?php echo htmlspecialchars($r['client_name']); ?></p>
                                <p class="text-[10px] text-gray-400"><?php echo $r['client_tel']; ?></p>
                            </td>
                            <td class="p-6 font-bold text-sm"><?php echo $r['destination']; ?></td>
                            <td class="p-6 text-sm font-black"><?php echo $r['nombre_places']; ?></td>
                            <td class="p-6">
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $r['statut']=='Confirmed'?'bg-green-100 text-green-600':'bg-orange-100 text-orange-600'; ?>">
                                    <?php echo $r['statut'] ?? 'Pending'; ?>
                                </span>
                            </td>
                            <td class="p-6 text-right">
                                <form action="" method="POST" class="flex justify-end gap-2">
                                    <input type="hidden" name="res_id" value="<?php echo $r['id']; ?>">
                                    <button name="action_ticket" value="Confirmed" class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center"><i class="fas fa-check text-[10px]"></i></button>
                                    <button name="action_ticket" value="Rejected" class="w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center"><i class="fas fa-times text-[10px]"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- SECTION: PARCELS TO PROCESS -->
        <section id="colis_demandes" class="tab-content">
            <h2 class="text-3xl font-black italic mb-8 uppercase tracking-tighter">Parcels at Counter (Outgoing)</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach($parcels_new as $c): ?>
                    <div class="bg-white p-6 rounded-[35px] border-b-4 <?php echo ($c['statut'] == 'Accepted') ? 'border-green-500' : 'border-blue-500'; ?> shadow-sm">
                        <div class="flex justify-between mb-6">
                            <span class="text-[10px] font-black bg-gray-100 px-3 py-1 rounded-full uppercase italic">Ref: <?php echo $c['numero_unique']; ?></span>
                            <span class="font-black text-blue-600"><?php echo $c['poids']; ?> KG</span>
                        </div>
                        <div class="space-y-3 mb-8">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipient</p>
                            <p class="font-black"><?php echo htmlspecialchars($c['destinataire']); ?></p>
                            <p class="text-xs font-bold text-gray-500"><i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>To: <?php echo $c['ag_dest']; ?></p>
                            
                            <?php if($c['statut'] == 'Accepted'): ?>
                                <p class="text-green-600 font-black text-xs uppercase italic"><i class="fas fa-check-double mr-1"></i> Parcel Accepted at Counter</p>
                            <?php endif; ?>
                        </div>

                        <form action="" method="POST" class="flex flex-col gap-2">
                            <input type="hidden" name="colis_id" value="<?php echo $c['id']; ?>">
                            
                            <?php if($c['statut'] == 'Accepted'): ?>
                                <button name="action_colis" value="In Transit" class="w-full bg-[#0A1F33] text-[#4DFF88] py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:scale-105 transition-transform">
                                    <i class="fas fa-shipping-fast mr-2"></i>Ship to <?php echo $c['ag_dest']; ?>
                                </button>
                            <?php else: ?>
                                <div class="grid grid-cols-2 gap-2">
                                    <button name="action_colis" value="Accepted" class="bg-green-500 text-white py-3 rounded-xl font-black text-[10px] uppercase">Accept</button>
                                    <button name="action_colis" value="Rejected" class="bg-red-500 text-white py-3 rounded-xl font-black text-[10px] uppercase">Reject</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endforeach; ?>
                
                <?php if(empty($parcels_new)): ?>
                    <p class="text-gray-400 font-bold italic">No parcels pending processing.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION: PARCELS TO DELIVER -->
        <section id="colis_entrants" class="tab-content">
            <h2 class="text-3xl font-black italic mb-8 text-orange-600 uppercase">Incoming Arrivals</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach($parcels_incoming as $c): ?>
                    <div class="bg-white p-8 rounded-[40px] shadow-sm flex justify-between items-center border border-gray-100">
                        <div>
                            <p class="text-[10px] font-black text-orange-500 uppercase mb-2">Origin: <?php echo $c['ag_dep']; ?></p>
                            <h4 class="text-xl font-black mb-1"><?php echo htmlspecialchars($c['destinataire']); ?></h4>
                            <p class="text-sm font-bold text-gray-400">Ref: <span class="text-[#0A1F33]"><?php echo $c['numero_unique']; ?></span></p>
                        </div>
                        <form action="" method="POST">
                            <input type="hidden" name="colis_id" value="<?php echo $c['id']; ?>">
                            <button name="action_colis" value="Delivered" class="bg-green-500 text-white px-8 py-4 rounded-2xl font-black text-xs">DELIVER</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SECTION: HISTORY -->
        <section id="historique" class="tab-content">
            <h2 class="text-3xl font-black italic mb-8 uppercase">General History</h2>
            <div class="bg-white rounded-[35px] overflow-hidden shadow-sm">
                <table class="w-full text-left">
                    <thead class="bg-[#0A1F33] text-white">
                        <tr>
                            <th class="p-6 text-[10px] uppercase font-black">Reference</th>
                            <th class="p-6 text-[10px] uppercase font-black">Route</th>
                            <th class="p-6 text-[10px] uppercase font-black">Recipient</th>
                            <th class="p-6 text-[10px] uppercase font-black">Status</th>
                            <th class="p-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($parcel_history as $h): ?>
                        <tr>
                            <td class="p-6 font-black"><?php echo $h['numero_unique']; ?></td>
                            <td class="p-6 text-xs font-bold"><?php echo $h['ag_dep']; ?> <i class="fas fa-arrow-right mx-1 text-gray-300"></i> <?php echo $h['ag_dest']; ?></td>
                            <td class="p-6 font-bold"><?php echo htmlspecialchars($h['destinataire']); ?></td>
                            <td class="p-6">
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase 
                                    <?php 
                                        if($h['statut']=='Delivered') echo 'bg-green-100 text-green-600';
                                        elseif($h['statut']=='In Transit') echo 'bg-blue-100 text-blue-600';
                                        elseif($h['statut']=='Accepted') echo 'bg-orange-100 text-orange-600';
                                        else echo 'bg-gray-100 text-gray-500';
                                    ?>">
                                    <?php echo $h['statut'] ?? 'Pending'; ?>
                                </span>
                            </td>
                            <td class="p-6 text-right">
                                <form action="" method="POST" onsubmit="return confirm('Delete this record?')">
                                    <input type="hidden" name="colis_id" value="<?php echo $h['id']; ?>">
                                    <button name="delete_colis" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- SECTION: CLIENTS -->
        <section id="clients" class="tab-content">
            <h2 class="text-3xl font-black italic mb-8 uppercase">Client Directory</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <?php foreach($clients as $cl): ?>
                <div class="bg-white p-6 rounded-[30px] shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600 font-black">
                        <?php echo strtoupper(substr($cl['nom'], 0, 1)); ?>
                    </div>
                    <div>
                        <p class="font-black text-sm"><?php echo htmlspecialchars($cl['nom']); ?></p>
                        <p class="text-[10px] text-gray-400 font-bold"><?php echo $cl['telephone']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <script>
        function showTab(id) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            const btn = document.getElementById('btn-' + id);
            if(btn) btn.classList.add('active');
            localStorage.setItem('activeEmpTab', id);
        }
        window.onload = () => {
            const saved = localStorage.getItem('activeEmpTab') || 'planning';
            showTab(saved);
        };
    </script>
</body>
</html>