<?php

use App\Controllers\AuthController;
use App\Controllers\HotelChambreController;
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
Router::get('/caisse', [PageController::class, 'caisse']);
Router::get('/recharges', [PageController::class, 'recharges']);
Router::get('/produits', [PageController::class, 'produits']);
Router::get('/utilisateurs', [PageController::class, 'utilisateurs']);
Router::get('/otp-codes', [PageController::class, 'otpCodes']);
Router::get('/mon-profil', [PageController::class, 'monProfil']);
Router::get('/historique', [PageController::class, 'historique']);

// ============================================================================
// Module Hôtel (PMS) — ajouté 2026-10-15
// Référence : docs/PLAN_HOTEL_V1.md
// ============================================================================
Router::get('/hotel', [PageController::class, 'hotel']);
Router::get('/hotel/reception', [PageController::class, 'hotelReception']);
Router::get('/hotel/etages', [PageController::class, 'hotelEtages']);
Router::get('/hotel/tableau-bord', [PageController::class, 'hotelDashboard']);
// Gestion des rôles hôtel (manager / réceptionniste / gouvernante) — ajouté 2026-10-15
Router::get('/hotel/roles', [PageController::class, 'hotelRoles']);
Router::post('/api/hotel/role/update', [PageController::class, 'apiHotelUpdateRole']);
// Calendrier + Chambres — sous-pages hôtel
Router::get('/hotel/calendrier', [PageController::class, 'hotelCalendrier']);
Router::get('/hotel/chambres', [PageController::class, 'hotelChambres']);
// Réservations hôtel — page + API JSON — ajouté 2026-10-15
Router::get('/hotel/reservations', [PageController::class, 'hotelReservations']);
Router::get('/api/hotel/reservations', [HotelReservationController::class, 'index']);
Router::get('/api/hotel/reservations/available-rooms', [HotelReservationController::class, 'availableRooms']);
Router::get('/api/hotel/reservations/[i:id]', [HotelReservationController::class, 'show']);
Router::post('/api/hotel/reservations', [HotelReservationController::class, 'store']);
Router::put('/api/hotel/reservations/[i:id]', [HotelReservationController::class, 'update']);
Router::post('/api/hotel/reservations/[i:id]/cancel', [HotelReservationController::class, 'cancel']);
Router::get('/api/hotel/guests/search', [HotelReservationController::class, 'searchGuests']);
// Chambres hôtel — page + API CRUD — ajouté 2026-10-16
Router::get('/api/hotel/chambres', [HotelChambreController::class, 'index']);
Router::get('/api/hotel/chambres/[i:id]', [HotelChambreController::class, 'show']);
Router::post('/api/hotel/chambres', [HotelChambreController::class, 'store']);
Router::put('/api/hotel/chambres/[i:id]', [HotelChambreController::class, 'update']);
Router::post('/api/hotel/chambres/[i:id]/etat', [HotelChambreController::class, 'setEtat']);
// Réception live — ajouté 2026-10-16
Router::get('/api/hotel/reception/board', [HotelReceptionController::class, 'board']);
Router::post('/api/hotel/reservations/[i:id]/check-in',  [HotelReceptionController::class, 'checkIn']);
Router::post('/api/hotel/reservations/[i:id]/check-out', [HotelReceptionController::class, 'checkOut']);
Router::post('/api/hotel/reception/rooms/[i:id]/cleaning-done', [HotelReceptionController::class, 'cleaningDone']);
Router::post('/api/hotel/reception/rooms/[i:id]/out-of-service', [HotelReceptionController::class, 'outOfService']);
// Guests hôtel — page + API CRUD — ajouté 2026-10-16
Router::get('/hotel/guests', [PageController::class, 'hotelGuests']);
Router::get('/api/hotel/guests', [HotelGuestController::class, 'index']);
Router::get('/api/hotel/guests/[i:id]', [HotelGuestController::class, 'show']);
Router::post('/api/hotel/guests', [HotelGuestController::class, 'store']);
Router::put('/api/hotel/guests/[i:id]', [HotelGuestController::class, 'update']);
// Lien client POS ↔ guest (ajouté 2026-10-17)
Router::post('/api/hotel/guests/[i:id]/link-client', [HotelGuestController::class, 'linkClient']);
Router::get('/api/hotel/clients/search', [HotelGuestController::class, 'searchClients']);
// Facturation hôtel — page + API CRUD — ajouté 2026-10-17
Router::get('/hotel/facturation', [PageController::class, 'hotelFacturation']);
Router::get('/api/hotel/factures/preview', [HotelFactureController::class, 'preview']);
Router::post('/api/hotel/factures', [HotelFactureController::class, 'store']);
Router::get('/api/hotel/factures/[i:id]', [HotelFactureController::class, 'show']);
Router::post('/api/hotel/factures/[i:id]/cancel', [HotelFactureController::class, 'cancel']);
Router::get('/categories', [PageController::class, 'categories']);
Router::get('/taxes', [PageController::class, 'taxes']);
Router::get('/parametres', [PageController::class, 'parametres']);
Router::get('/analytics', [PageController::class, 'analytics']);
Router::get('/rapports', [PageController::class, 'rapports']);
Router::get('/rapport-ticket', [PageController::class, 'rapportTicket']);
Router::get('/shops', [PageController::class, 'shops']);
Router::get('/payroll', [PageController::class, 'payroll']);
Router::get('/payroll/[:view]', [PageController::class, 'payroll']);
Router::get('/scanner', [PageController::class, 'scanner']);
Router::get('/new-scanner', [PageController::class, 'newScanner']);
Router::get('/facture/[i:id]', [\App\Controllers\InvoiceController::class, 'show']);
Router::get('/facture', [\App\Controllers\InvoiceController::class, 'showByRef']);
Router::get('/facture-client/[i:id]', [\App\Controllers\InvoiceController::class, 'publicInvoice']);

// Médias protégés (photos de profil / produits stockées hors de public/)
Router::get("/media/product/[*:filename]", [\App\Controllers\MediaController::class, 'product']);
Router::get("/media/profile/[*:filename]", [\App\Controllers\MediaController::class, 'profile']);
// Logo boutique : public (utilisé sur la facture imprimée et /facture, page sans session)
Router::get("/media/logo/[*:filename]", [\App\Controllers\MediaController::class, 'logo']);

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
$router->get('/utilisateurs', 'PageController@utilisateurs');
$router->get('/historique', 'PageController@historique');
$router->get('/parametres', 'PageController@parametres');

$router->post('/delete/user', 'UserController@delete');*/
