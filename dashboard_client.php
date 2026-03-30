<?php
session_start();
require_once 'db_config.php';

// Sécurité : Vérifier si l'utilisateur est connecté et est un client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];

// Initialisation des données
$voyages = [];
$mes_tickets = [];
$mes_colis = [];
$agences = [];

try {
    // 1. Récupérer les agences pour le formulaire colis
    $stmt_agences = $pdo->query("SELECT id, nom_agence FROM agences ORDER BY nom_agence ASC");
    $agences = $stmt_agences->fetchAll(PDO::FETCH_ASSOC);

    // 2. RÉCUPÉRER TOUS LES VOYAGES (CORRIGÉ POUR TOUT AFFICHER)
    // On retire les filtres de date et de places disponibles pour tout voir
    $stmt_voyages = $pdo->query("SELECT *, (places_totales - places_occupees) as libres 
                                FROM voyages_disponibles 
                                ORDER BY date_voyage DESC");
    $voyages = $stmt_voyages->fetchAll(PDO::FETCH_ASSOC);

    // 3. Récupérer l'historique des tickets du client
    $stmt_tix = $pdo->prepare("SELECT r.*, v.depart, v.destination, v.date_voyage, v.heure_depart 
                               FROM reservations r 
                               JOIN voyages_disponibles v ON r.voyage_id = v.id 
                               WHERE r.client_id = ? ORDER BY r.id DESC");
    $stmt_tix->execute([$user_id]);
    $mes_tickets = $stmt_tix->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer l'historique des colis du client
    $stmt_colis = $pdo->prepare("SELECT * FROM demandes_courrier WHERE client_id = ? ORDER BY id DESC");
    $stmt_colis->execute([$user_id]);
    $mes_colis = $stmt_colis->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}

$code_colis = "COL-" . strtoupper(substr(uniqid(), -5));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TransExpress - My Space</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; color: #0A1F33; }
        .glass-nav { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); }
        .card-trip { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-trip:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); }
        .modal-animate { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .fade-out { animation: fadeOut 0.5s ease-out forwards; }
        @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-20px); } }
    </style>
</head>
<body class="pb-24">

    <header class="p-6 flex justify-between items-center bg-white border-b sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-blue-100">
                <i class="fas fa-bus text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black italic tracking-tighter">TRANSEXPRESS</h1>
                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">Customer Dashboard</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-black"><?php echo htmlspecialchars($user_nom); ?></p>
            </div>
            <div class="w-10 h-10 bg-[#0A1F33] text-[#4DFF88] rounded-2xl flex items-center justify-center font-bold shadow-lg">
                <?php echo strtoupper(substr($user_nom, 0, 1)); ?>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto p-4">

        <?php if(isset($_GET['res_success'])): ?>
            <div id="successMessage" class="mb-6 bg-green-500 text-white p-4 rounded-[25px] font-bold flex items-center gap-3 shadow-lg modal-animate">
                <i class="fas fa-check-circle text-xl"></i> Booking successful! Your ticket is ready.
            </div>
        <?php endif; ?>

        <!-- SECTION 1 : VOYAGES DISPONIBLES -->
        <section class="mb-10">
            <h2 class="text-2xl font-black italic mb-6">Available Trips 🌍</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($voyages as $v): ?>
                    <div class="bg-white p-6 rounded-[35px] border border-gray-100 card-trip relative overflow-hidden">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Departure from <?php echo htmlspecialchars($v['depart']); ?></p>
                                <h3 class="text-lg font-black uppercase"><?php echo htmlspecialchars($v['destination']); ?></h3>
                            </div>
                            <?php if($v['libres'] > 0): ?>
                                <div class="bg-blue-50 text-blue-600 px-3 py-1 rounded-full text-[10px] font-black">
                                    <?php echo $v['libres']; ?> SEATS LEFT
                                </div>
                            <?php else: ?>
                                <div class="bg-red-50 text-red-600 px-3 py-1 rounded-full text-[10px] font-black">
                                    FULL / CLOSED
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-4 mb-6 text-gray-500">
                            <div class="text-center bg-gray-50 p-2 rounded-xl min-w-[60px]">
                                <p class="text-[10px] font-bold uppercase">Date</p>
                                <p class="text-xs font-black text-[#0A1F33]"><?php echo date('d M', strtotime($v['date_voyage'])); ?></p>
                            </div>
                            <div class="text-center bg-gray-50 p-2 rounded-xl min-w-[60px]">
                                <p class="text-[10px] font-bold uppercase">Time</p>
                                <p class="text-xs font-black text-[#0A1F33]"><?php echo substr($v['heure_depart'], 0, 5); ?></p>
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-dashed">
                            <p class="text-xl font-black text-blue-600"><?php echo number_format($v['prix'], 0); ?> <span class="text-[10px]">FBU</span></p>
                            
                            <?php if($v['libres'] > 0): ?>
                                <button onclick='openBookingModal(<?php echo htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8'); ?>)'
                                        class="bg-[#0A1F33] text-[#4DFF88] px-6 py-3 rounded-2xl font-black text-[10px] uppercase shadow-xl hover:bg-blue-950">
                                    Book Now
                                </button>
                            <?php else: ?>
                                <button disabled class="bg-gray-100 text-gray-400 px-6 py-3 rounded-2xl font-black text-[10px] uppercase cursor-not-allowed">
                                    Sold Out
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SECTION 2 : MES RÉSERVATIONS -->
        <section class="mb-10">
            <h2 class="text-xl font-black italic mb-6">My Bookings 🎟️</h2>
            <div class="space-y-3">
                <?php if(empty($mes_tickets)): ?>
                    <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-[35px] text-gray-400 font-bold text-sm">No reservations found</div>
                <?php endif; ?>
                <?php foreach($mes_tickets as $t): ?>
                    <div class="bg-white p-5 rounded-[25px] flex items-center justify-between border border-gray-50 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center shadow-inner">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase"><?php echo $t['numero_ticket'] ?? 'REF-000'; ?></p>
                                <p class="text-sm font-bold"><?php echo htmlspecialchars($t['depart']); ?> ➔ <?php echo htmlspecialchars($t['destination']); ?></p>
                                <p class="text-[9px] font-bold text-blue-500"><?php echo $t['nombre_places']; ?> seat(s)</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="text-[9px] font-black px-4 py-2 rounded-full uppercase <?php echo ($t['statut'] == 'Confirmé') ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600'; ?>">
                                <?php echo ($t['statut'] == 'Confirmé') ? 'Confirmed' : 'Pending'; ?>
                            </span>
                            <div class="flex gap-2">
                                <a href="print_ticket.php?id=<?php echo $t['id']; ?>" target="_blank" class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase hover:bg-blue-600 hover:text-white flex items-center gap-1">
                                    <i class="fas fa-print"></i> Print
                                </a>
                                <a href="annuler_demande.php?type=ticket&id=<?php echo $t['id']; ?>" onclick="return confirm('Cancel this reservation?')" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase hover:bg-red-500 hover:text-white flex items-center gap-1">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SECTION 3 : MES COLIS -->
        <section>
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-black italic">Parcel Tracking 📦</h2>
                <button onclick="openParcelModal()" class="bg-orange-500 text-white p-3 rounded-2xl font-black text-[10px] uppercase shadow-lg shadow-orange-200">Send a Parcel</button>
            </div>
            <div class="grid grid-cols-1 gap-3">
                <?php if(empty($mes_colis)): ?>
                    <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-[35px] text-gray-400 font-bold text-sm">No shipments yet</div>
                <?php endif; ?>
                <?php foreach($mes_colis as $c): ?>
                    <div class="bg-white p-5 rounded-[25px] flex items-center justify-between border border-gray-50">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase"><?php echo $c['code_suivi'] ?? 'NO-CODE'; ?></p>
                                <p class="text-sm font-bold">Recipient: <?php echo htmlspecialchars($c['destinataire'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-2 text-right">
                            <p class="text-[9px] font-black text-blue-600 uppercase"><?php echo $c['statut'] ?? 'Pending'; ?></p>
                            <div class="flex gap-2">
                                <a href="print_parcel.php?id=<?php echo $c['id']; ?>" target="_blank" class="bg-orange-50 text-orange-600 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase hover:bg-orange-600 hover:text-white flex items-center gap-1">
                                    <i class="fas fa-print"></i> Print
                                </a>
                                <a href="annuler_demande.php?type=colis&id=<?php echo $c['id']; ?>" onclick="return confirm('Cancel this shipment?')" class="bg-red-50 text-red-500 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase hover:bg-red-500 hover:text-white flex items-center gap-1">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- MODAL 1 : BOOKING -->
    <div id="modalBooking" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#0A1F33]/60 backdrop-blur-sm" onclick="closeBookingModal()"></div>
        <div class="bg-white w-full max-w-md rounded-[40px] relative z-10 p-8 modal-animate shadow-2xl">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner"><i class="fas fa-id-card"></i></div>
                <h2 class="text-2xl font-black">Book My Seat</h2>
                <p id="routeDisplay" class="text-xs font-bold text-gray-400 uppercase tracking-widest mt-1"></p>
            </div>
            <form action="traitement_reservation.php" method="POST" class="space-y-4">
                <input type="hidden" name="voyage_id" id="modalVoyageId">
                <input type="hidden" name="client_id" value="<?php echo $user_id; ?>">
                <div>
                    <label class="text-[10px] font-black uppercase text-gray-400 ml-2">Passenger Name</label>
                    <input type="text" name="nom_passager" value="<?php echo htmlspecialchars($user_nom); ?>" required class="w-full mt-1 p-4 rounded-2xl bg-gray-50 text-sm font-bold outline-none border-2 border-transparent focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-gray-400 ml-2">Seats</label>
                    <input type="number" name="nombre_places" id="modalQty" value="1" min="1" oninput="calculateTripTotal()" required class="w-full mt-1 p-4 rounded-2xl bg-gray-50 text-sm font-bold outline-none border-2 border-transparent focus:border-blue-500">
                </div>
                <div class="bg-blue-600 p-6 rounded-[30px] flex justify-between items-center text-white">
                    <div>
                        <p class="text-[9px] font-black uppercase opacity-60">Total to pay</p>
                        <p id="tripTotalDisplay" class="text-2xl font-black">0 FBU</p>
                    </div>
                    <i class="fas fa-coins text-3xl opacity-30"></i>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeBookingModal()" class="flex-1 py-4 font-black text-[10px] uppercase text-gray-400">Cancel</button>
                    <button type="submit" class="flex-[2] bg-[#0A1F33] text-[#4DFF88] py-4 rounded-2xl font-black text-[10px] uppercase">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2 : PARCEL -->
    <div id="modalParcel" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#0A1F33]/80 backdrop-blur-md" onclick="closeParcelModal()"></div>
        <div class="bg-white w-full max-w-xl rounded-[45px] relative z-10 p-8 shadow-2xl max-h-[90vh] overflow-y-auto modal-animate">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-2xl font-black">New Shipment 📦</h2>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Ref: <?php echo $code_colis; ?></p>
                </div>
                <button onclick="closeParcelModal()" class="text-gray-300 hover:text-red-500">
                    <i class="fas fa-times-circle text-2xl"></i>
                </button>
            </div>
            <form action="traitement_colis.php" method="POST" class="space-y-4">
                <input type="hidden" name="client_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="code_suivi" value="<?php echo $code_colis; ?>">
                <input type="hidden" name="prix_total" id="hiddenParcelPrice" value="5000">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2 bg-gray-50 p-4 rounded-[25px] grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black uppercase text-gray-400 ml-2">Type</label>
                            <select name="type" id="parcelType" onchange="calculateParcelPrice()" class="w-full mt-1 p-3 rounded-xl bg-white text-xs font-bold outline-none">
                                <option value="enveloppe">Envelope (5,000 FBU)</option>
                                <option value="colis">Package (2,000 FBU / kg)</option>
                            </select>
                        </div>
                        <div id="weightBox" class="opacity-30 pointer-events-none">
                            <label class="text-[10px] font-black uppercase text-gray-400 ml-2">Weight (kg)</label>
                            <input type="number" name="poids" id="parcelWeight" value="1" min="1" oninput="calculateParcelPrice()" class="w-full mt-1 p-3 rounded-xl bg-white text-xs font-bold outline-none">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-[10px] font-black text-blue-600 uppercase border-b">Sender Info</p>
                        <input type="text" name="expediteur" value="<?php echo htmlspecialchars($user_nom); ?>" required class="w-full p-3 rounded-xl bg-gray-50 text-sm font-bold" placeholder="Your Name">
                        <input type="text" name="contact_exp" placeholder="Your Phone" required class="w-full p-3 rounded-xl bg-gray-50 text-sm font-bold">
                    </div>
                    <div class="space-y-2">
                        <p class="text-[10px] font-black text-orange-600 uppercase border-b">Recipient Info</p>
                        <input type="text" name="destinataire" placeholder="Recipient Name" required class="w-full p-3 rounded-xl bg-gray-50 text-sm font-bold">
                        <input type="text" name="contact_dest" placeholder="Recipient Phone" required class="w-full p-3 rounded-xl bg-gray-50 text-sm font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase text-gray-400">Departure Agency</label>
                        <select name="agence_depart" required class="w-full mt-1 p-3 rounded-xl bg-gray-50 text-sm font-bold">
                            <option value="">Select...</option>
                            <?php foreach($agences as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nom_agence']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase text-gray-400">Destination Agency</label>
                        <select name="agence_desti" required class="w-full mt-1 p-3 rounded-xl bg-gray-50 text-sm font-bold">
                            <option value="">Select...</option>
                            <?php foreach($agences as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nom_agence']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="bg-[#0A1F33] p-6 rounded-[30px] flex justify-between items-center text-[#4DFF88]">
                    <div>
                        <p class="text-[9px] font-black uppercase">Estimated Rate</p>
                        <p id="parcelPriceDisplay" class="text-2xl font-black">5,000 FBU</p>
                    </div>
                    <i class="fas fa-truck-loading text-3xl opacity-30"></i>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-5 rounded-[25px] font-black uppercase tracking-widest hover:bg-blue-700 transition-colors">
                    Confirm Shipment
                </button>
            </form>
        </div>
    </div>

    <!-- NAVIGATION -->
    <nav class="fixed bottom-0 left-0 right-0 glass-nav border-t px-10 py-5 flex justify-around items-center rounded-t-[40px] shadow-2xl z-50">
        <a href="#" class="text-blue-600 scale-125"><i class="fas fa-home text-xl"></i></a>
        <button onclick="openParcelModal()" class="text-gray-300 hover:text-orange-500"><i class="fas fa-box text-xl"></i></button>
        <a href="logout.php" class="text-gray-300 hover:text-red-500"><i class="fas fa-power-off text-xl"></i></a>
    </nav>

    <script>
        let unitPriceTrip = 0;
        function openBookingModal(voyage) {
            unitPriceTrip = voyage.prix;
            document.getElementById('modalVoyageId').value = voyage.id;
            document.getElementById('routeDisplay').innerText = voyage.depart + " ➔ " + voyage.destination;
            document.getElementById('modalQty').max = voyage.libres;
            document.getElementById('modalQty').value = 1;
            calculateTripTotal();
            document.getElementById('modalBooking').classList.remove('hidden');
        }
        function closeBookingModal() { document.getElementById('modalBooking').classList.add('hidden'); }
        function calculateTripTotal() {
            const qty = document.getElementById('modalQty').value;
            const total = qty * unitPriceTrip;
            document.getElementById('tripTotalDisplay').innerText = total.toLocaleString() + " FBU";
        }
        function openParcelModal() { document.getElementById('modalParcel').classList.remove('hidden'); }
        function closeParcelModal() { document.getElementById('modalParcel').classList.add('hidden'); }
        function calculateParcelPrice() {
            const type = document.getElementById('parcelType').value;
            const weightBox = document.getElementById('weightBox');
            const weightInput = document.getElementById('parcelWeight');
            const display = document.getElementById('parcelPriceDisplay');
            const hidden = document.getElementById('hiddenParcelPrice');
            let price = (type === 'enveloppe') ? 5000 : weightInput.value * 2000;
            weightBox.style.opacity = (type === 'enveloppe') ? "0.3" : "1";
            weightBox.style.pointerEvents = (type === 'enveloppe') ? "none" : "auto";
            display.innerText = price.toLocaleString() + " FBU";
            hidden.value = price;
        }

        window.addEventListener('DOMContentLoaded', () => {
            const successMsg = document.getElementById('successMessage');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.classList.add('fade-out');
                    setTimeout(() => { successMsg.style.display = 'none'; }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>