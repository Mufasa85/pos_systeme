<?php

use App\Controllers\AuthController;
use App\Controllers\HotelChambreController;
use App\Controllers\HotelDashboardController;
use App\Controllers\HotelEtagesController;
use App\Controllers\HotelExtrasController;
use App\Controllers\HotelFactureController;
use App\Controllers\HotelGuestController;
use App\Controllers\HotelReceptionController;
use App\Controllers\HotelReservationController;
use App\Controllers\PageController;
use App\Core\Router;

Router::get('/', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::get('/logout', [AuthController::class, 'logout']);
Router::get('/verify-otp', [AuthController::class, 'showVerifyOtp']);
Router::get('/forgot-password', [AuthController::class, 'showForgotPassword']);
Router::get('/reset-password', [AuthController::class, 'showResetPassword']);

Router::get('/dashboard', [PageController::class, 'dashboard']);
// Politique d'accès aux pages (super_admin) — le 3e argument est la clé de
// page : la route est bloquée si la page est masquée pour l'utilisateur.
// Voir docs/superpowers/specs/2026-11-18-shop-page-access-policy-design.md
Router::get('/caisse', [PageController::class, 'caisse'], 'caisse');
Router::get('/recharges', [PageController::class, 'recharges'], 'recharges');
Router::get('/produits', [PageController::class, 'produits'], 'produits');
// Alertes de péremption — liste complète paginée (le dashboard n'affiche
// que les 20 plus urgentes). Reprend le droit d'accès de « produits ».
Router::get('/alertes-lots', [PageController::class, 'alertesLots'], 'produits');
// Page des lots — liste paginée (keyset) de tous les lots de la boutique.
Router::get('/produits/lots', [PageController::class, 'produitsLots'], 'produits');
Router::get('/utilisateurs', [PageController::class, 'utilisateurs'], 'utilisateurs');
Router::get('/otp-codes', [PageController::class, 'otpCodes']);
Router::get('/mon-profil', [PageController::class, 'monProfil']);
Router::get('/historique', [PageController::class, 'historique'], 'historique');

// ============================================================================
// Module Hôtel (PMS) — ajouté 2026-10-15
// Référence : docs/PLAN_HOTEL_V1.md
// ============================================================================
Router::get('/hotel', [PageController::class, 'hotel']);
Router::get('/hotel/reception', [PageController::class, 'hotelReception'], 'hotel_reception');
Router::get('/hotel/etages', [PageController::class, 'hotelEtages'], 'hotel_etages');
Router::get('/hotel/tableau-bord', [PageController::class, 'hotelDashboard'], 'hotel_tableau_bord');
// API tableau de bord hôtelier (KPI + séjours en cours + housekeeping) — Sprint 4
Router::get('/api/hotel/dashboard', [HotelDashboardController::class, 'index'], 'hotel_tableau_bord');
// Gestion des rôles hôtel (manager / réceptionniste / gouvernante) — ajouté 2026-10-15
Router::get('/hotel/roles', [PageController::class, 'hotelRoles'], 'hotel_roles');
Router::post('/api/hotel/role/update', [PageController::class, 'apiHotelUpdateRole'], 'hotel_roles');
// Droit d'accès à la page /caisse (POS) par utilisateur — ajouté 2026-11-14
// Colonne `utilisateurs.acces_caisse` (migration 2026_11_14_user_caisse_access.sql).
// Le manager retire ainsi la gouvernante (ou la réceptionniste) du poste de vente.
//
// ⚠️ HORS POLITIQUE : cette API sert à RÉGLER les droits `acces_*`.
// La bloquer par la politique hôtel empêcherait le manager d'administrer
// les droits et rendrait la politique ingérable.
Router::post('/api/hotel/caisse-access/update', [PageController::class, 'apiHotelUpdateCaisseAccess']);
// Calendrier + Chambres — sous-pages hôtel
Router::get('/hotel/calendrier', [PageController::class, 'hotelCalendrier'], 'hotel_calendrier');
Router::get('/api/hotel/calendrier', [PageController::class, 'apiHotelCalendrier'], 'hotel_calendrier');
Router::get('/hotel/chambres', [PageController::class, 'hotelChambres'], 'hotel_chambres');
// Réservations hôtel — page + API JSON — ajouté 2026-10-15
Router::get('/hotel/reservations', [PageController::class, 'hotelReservations'], 'hotel_reservations');
Router::get('/api/hotel/reservations', [HotelReservationController::class, 'index'], 'hotel_reservations');
Router::get('/api/hotel/reservations/available-rooms', [HotelReservationController::class, 'availableRooms'], 'hotel_reservations');
Router::get('/api/hotel/reservations/[i:id]', [HotelReservationController::class, 'show'], 'hotel_reservations');
Router::post('/api/hotel/reservations', [HotelReservationController::class, 'store'], 'hotel_reservations');
Router::put('/api/hotel/reservations/[i:id]', [HotelReservationController::class, 'update'], 'hotel_reservations');
Router::post('/api/hotel/reservations/[i:id]/cancel', [HotelReservationController::class, 'cancel'], 'hotel_reservations');
// LECTURE PARTAGÉE : sert les réservations ET la page clients.
// Autorisée si AU MOINS UNE des deux pages lectrices est accessible.
Router::get('/api/hotel/guests/search', [HotelReservationController::class, 'searchGuests'], 'hotel_reservations|hotel_guests', 'shared_read');
// Chambres hôtel — page + API CRUD — ajouté 2026-10-16
Router::get('/api/hotel/chambres', [HotelChambreController::class, 'index'], 'hotel_chambres');
Router::get('/api/hotel/chambres/[i:id]', [HotelChambreController::class, 'show'], 'hotel_chambres');
Router::post('/api/hotel/chambres', [HotelChambreController::class, 'store'], 'hotel_chambres');
Router::put('/api/hotel/chambres/[i:id]', [HotelChambreController::class, 'update'], 'hotel_chambres');
Router::post('/api/hotel/chambres/[i:id]/etat', [HotelChambreController::class, 'setEtat'], 'hotel_chambres');
// Réception live — ajouté 2026-10-16
Router::get('/api/hotel/reception/board', [HotelReceptionController::class, 'board'], 'hotel_reception');
Router::post('/api/hotel/reservations/[i:id]/check-in', [HotelReceptionController::class, 'checkIn'], 'hotel_reception');
Router::post('/api/hotel/reservations/[i:id]/check-out', [HotelReceptionController::class, 'checkOut'], 'hotel_reception');
Router::post('/api/hotel/reception/rooms/[i:id]/cleaning-done', [HotelReceptionController::class, 'cleaningDone'], 'hotel_reception');
Router::post('/api/hotel/reception/rooms/[i:id]/out-of-service', [HotelReceptionController::class, 'outOfService'], 'hotel_reception');
// Gouvernante — vue mobile /hotel/etages (Sprint 3)
Router::get('/api/hotel/etages/board', [HotelEtagesController::class, 'board'], 'hotel_etages');
Router::post('/api/hotel/housekeeping/[i:id]/claim', [HotelEtagesController::class, 'claim'], 'hotel_etages');
Router::post('/api/hotel/housekeeping/[i:id]/done', [HotelEtagesController::class, 'done'], 'hotel_etages');
Router::post('/api/hotel/housekeeping/[i:id]/issue', [HotelEtagesController::class, 'issue'], 'hotel_etages');
// Guests hôtel — page + API CRUD — ajouté 2026-10-16
Router::get('/hotel/guests', [PageController::class, 'hotelGuests'], 'hotel_guests');
Router::get('/api/hotel/guests', [HotelGuestController::class, 'index'], 'hotel_guests');
Router::get('/api/hotel/guests/[i:id]', [HotelGuestController::class, 'show'], 'hotel_guests');
Router::post('/api/hotel/guests', [HotelGuestController::class, 'store'], 'hotel_guests');
Router::put('/api/hotel/guests/[i:id]', [HotelGuestController::class, 'update'], 'hotel_guests');
// Liaison client POS ↔ guest (ajouté 2026-10-17)
Router::post('/api/hotel/guests/[i:id]/link-client', [HotelGuestController::class, 'linkClient'], 'hotel_guests');
// LECTURE PARTAGÉE : sert les réservations ET la page clients.
Router::get('/api/hotel/clients/search', [HotelGuestController::class, 'searchClients'], 'hotel_reservations|hotel_guests', 'shared_read');
// Pont caisse POS ↔ hôtel : séjours en_cours pour le picker d'imputation d'un extra (Sprint 5 — ajouté 2026-10-18)
// LECTURE PARTAGÉE : sert la facturation hôtel ET le picker Extras de la caisse.
Router::get('/api/hotel/sejours/en-cours', [HotelExtrasController::class, 'enCours'], 'caisse|hotel_facturation', 'shared_read');
// Imputation immédiate du panier sur un séjour (clic sur "Extras" → sélection séjour).
// Crée les lignes dans `hotel_extras` à la sélection, pas seulement à la validation finale.
// Accès contrôlé par la configuration par boutique `shops.acces_extras`
// (cochée par le super_admin dans /shops) — et NON par le feature flag API « hotel ».
Router::post('/api/hotel/sejours/[i:id]/extras-from-cart', [HotelExtrasController::class, 'extrasFromCart']);
// Facturation hôtel — page + API CRUD — ajouté 2026-10-17
Router::get('/hotel/facturation', [PageController::class, 'hotelFacturation'], 'hotel_facturation');
Router::get('/api/hotel/factures/preview', [HotelFactureController::class, 'preview'], 'hotel_facturation');
Router::post('/api/hotel/factures', [HotelFactureController::class, 'store'], 'hotel_facturation');
Router::get('/api/hotel/factures/[i:id]', [HotelFactureController::class, 'show'], 'hotel_facturation');
// Reçu de séjour (impression au check-in) — ajouté 2026-10-30
// Réutilise l'agrégat de la preview (nuits + extras + acomptes) mais n'écrit
// rien en base et ne génère AUCUNE facture (pas de DGI, pas de n° fiscal).
Router::get('/api/hotel/sejours/[i:id]/recu', [HotelFactureController::class, 'recu']);
// BUG FIX 2026-10-20 (round 6) : route /api/hotel/factures/[i:id]/cancel
// supprimée — l'annulation de facture n'est plus autorisée depuis l'UI de
// /hotel/facturation. La méthode HotelFactureController::cancel() est
// également supprimée.
Router::get('/categories', [PageController::class, 'categories'], 'categories');
Router::get('/taxes', [PageController::class, 'taxes'], 'taxes');
Router::get('/parametres', [PageController::class, 'parametres'], 'parametres');
Router::get('/analytics', [PageController::class, 'analytics'], 'analytics');
Router::get('/rapports', [PageController::class, 'rapports'], 'rapports');
Router::get('/rapport-ticket', [PageController::class, 'rapportTicket'], 'rapports');
Router::get('/shops', [PageController::class, 'shops']);
// Écran « Pages bloquées » par UTILISATEUR (super_admin).
// C'est l'outil qui administre la politique : la route ne porte PAS de clé
// de page, sinon la politique pourrait interdire à son propre administrateur
// de la modifier. L'accès est garanti par le rôle (super_admin), contrôlé
// côté serveur dans PageController::pagesBloquees().
Router::get('/pages-bloquees', [PageController::class, 'pagesBloquees']);
Router::get('/payroll', [PageController::class, 'payroll'], 'paie');
Router::get('/payroll/[:view]', [PageController::class, 'payroll'], 'paie');
// ============================================================================
// Module Pressing — suivi des commandes de pressing
// Référence : docs/PLAN_PRESSING.md
// ============================================================================
// L'accès est contrôlé par le flag API `pressing` (comme /recharges et
// /hotel) : voir Controller::requireShopPressingAccess().
Router::get('/pressing', [PageController::class, 'pressing'], 'pressing');
Router::get('/scanner', [PageController::class, 'scanner']);
Router::get('/new-scanner', [PageController::class, 'newScanner']);
Router::get('/facture/[i:id]', [\App\Controllers\InvoiceController::class, 'show']);
Router::get('/facture', [\App\Controllers\InvoiceController::class, 'showByRef']);
Router::get('/facture-client/[i:id]', [\App\Controllers\InvoiceController::class, 'publicInvoice']);

// Médias protégés (photos de profil / produits stockées hors de public/)
Router::get('/media/product/[*:filename]', [\App\Controllers\MediaController::class, 'product']);
Router::get('/media/profile/[*:filename]', [\App\Controllers\MediaController::class, 'profile']);
// Logo boutique : public (utilisé sur la facture imprimée et /facture, page sans session)
Router::get('/media/logo/[*:filename]', [\App\Controllers\MediaController::class, 'logo']);

// API routes for invoice actions
Router::post('/api/facture/[i:id]/send', [\App\Controllers\InvoiceController::class, 'sendInvoice']);
Router::get('/api/facture/[i:id]/pdf', [\App\Controllers\InvoiceController::class, 'downloadPdf']);

// API route for client search
Router::get('/api/client/search', [\App\Controllers\ClientController::class, 'searchByNumero']);
Router::get('/api/client/types', [\App\Controllers\ClientController::class, 'getTypes']);

/*
$router->get('/', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

$router->get('/dashboard', 'PageController@dashboard');
$router->get('/caisse', 'PageController@caisse');
$router->get('/produits', 'PageController@produits');
$router->get('/alertes-lots', 'PageController@alertesLots');
$router->get('/utilisateurs', 'PageController@utilisateurs');
$router->get('/historique', 'PageController@historique');
$router->get('/parametres', 'PageController@parametres');

$router->post('/delete/user', 'UserController@delete');*/
