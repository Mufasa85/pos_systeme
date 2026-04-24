<?php
session_start();

// ========================================
// 🔒 RATE LIMITING - Protection contre les attaques
// ========================================

function checkRateLimit($maxRequests = 2, $timeWindow = 30)
{
    $clientIP = $_SERVER['REMOTE_ADDR'];
    $key = 'rate_limit_' . $clientIP;
    // Initialiser le tableau des requêtes si inexistant
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $currentTime = time();
    $requests = $_SESSION[$key];
    // Supprimer les requêtes expirées (plus vieilles que $timeWindow)
    $requests = array_filter($requests, function ($timestamp) use ($currentTime, $timeWindow) {
        return ($currentTime - $timestamp) < $timeWindow;
    });
    // Vérifier si le nombre de requêtes dépasse la limite
    if (count($requests) >= $maxRequests) {
        return false; // Trop de requêtes
    }
    // Enregistrer cette nouvelle requête
    $requests[] = $currentTime;
    $_SESSION[$key] = $requests;
    return true; // Requête acceptée
}
// 🛑 Vérifier le rate limit
if (!checkRateLimit(2, 30)) { // Max 2 requêtes en 30 secondes
    http_response_code(429); // Too Many Requests
?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="1">
        <title>Trop de requêtes</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
            }

            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .modal-box {
                background: white;
                border-radius: 8px;
                padding: 20px;
                max-width: 300px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                text-align: center;
            }

            .modal-title {
                font-size: 18px;
                font-weight: bold;
                color: #d32f2f;
                margin-bottom: 8px;
            }

            .modal-message {
                font-size: 13px;
                color: #666;
                margin-bottom: 12px;
            }

            .modal-reason {
                background: #fff3cd;
                border-left: 3px solid #ffc107;
                padding: 8px;
                margin-bottom: 12px;
                font-size: 11px;
                color: #856404;
                text-align: left;
                border-radius: 3px;
            }

            .timer {
                font-size: 32px;
                color: #d32f2f;
                font-weight: bold;
                margin-bottom: 3px;
                font-family: 'Courier New', monospace;
            }

            .timer-label {
                font-size: 10px;
                color: #999;
            }
        </style>
    </head>

    <body>
        <div class="modal-overlay">
            <div class="modal-box">
                <div class="modal-reason">
                    <strong>⏱Trop de requêtes, </br>Vous avez dépassé la limite.</br></strong>
                    <strong>🔐 Protection:</strong> Max 2 requêtes par 30s.
                </div>
                <!--<div class="timer">30</div>
                <div class="timer-label">Secondes avant réessai</div>-->
            </div>
        </div>
    </body>

    </html>
<?php
    exit();
}
function isBlockedForInvalidReference()
{
    $clientIP = $_SERVER['REMOTE_ADDR'];
    $blockKey = 'blocked_invalid_ref_' . $clientIP;

    if (isset($_SESSION[$blockKey])) {
        $blockTime = $_SESSION[$blockKey];
        $currentTime = time();

        // Si le blocage est encore actif (moins de 30 secondes écoulées)
        if (($currentTime - $blockTime) < 30) {
            return true;
        } else {
            // Le blocage a expiré, le supprimer
            unset($_SESSION[$blockKey]);
            return false;
        }
    }

    return false;
}
function blockSessionForInvalidReference()
{
    $clientIP = $_SERVER['REMOTE_ADDR'];
    $blockKey = 'blocked_invalid_ref_' . $clientIP;
    $_SESSION[$blockKey] = time();
}
function getRemainingBlockTime()
{
    $clientIP = $_SERVER['REMOTE_ADDR'];
    $blockKey = 'blocked_invalid_ref_' . $clientIP;
    if (isset($_SESSION[$blockKey])) {
        $currentTime = time();
        $timeElapsed = $currentTime - $_SESSION[$blockKey];
        $remaining = 30 - $timeElapsed;
        return max(0, $remaining);
    }
    return 0;
}
if (isBlockedForInvalidReference()) {
    http_response_code(403); // Forbidden
    $remainingTime = getRemainingBlockTime();
?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="1">
        <title>Accès bloqué</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
            }

            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .modal-box {
                background: white;
                border-radius: 8px;
                padding: 20px;
                max-width: 300px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                text-align: center;
                border-left: 5px solid #d32f2f;
            }

            .modal-title {
                font-size: 18px;
                font-weight: bold;
                color: #d32f2f;
                margin-bottom: 8px;
            }

            .modal-message {
                font-size: 13px;
                color: #666;
                margin-bottom: 12px;
            }

            .modal-reason {
                background: #ffebee;
                border-left: 3px solid #d32f2f;
                padding: 8px;
                margin-bottom: 12px;
                font-size: 11px;
                color: #c62828;
                text-align: left;
                border-radius: 3px;
            }

            .timer {
                font-size: 32px;
                color: #d32f2f;
                font-weight: bold;
                margin-bottom: 3px;
                font-family: 'Courier New', monospace;
            }

            .timer-label {
                font-size: 10px;
                color: #999;
            }
        </style>
    </head>

    <body>
        <div class="modal-overlay">
            <div class="modal-box">
                <!--<div class="modal-title">🚫 Accès bloqué</div>
                <div class="modal-message">Session bloquée temporairement</div>-->
                <div class="modal-reason">
                    <strong>Référence invalide détectée</strong>
                </div>
                <!--<div class="timer"><?php echo $remainingTime; ?></div>
                <div class="timer-label">Secondes avant déblocage</div>-->
            </div>
        </div>
    </body>

    </html>
    <?php
    exit();
}
// Fonction de validation de la référence
function validateReference($ref)
{
    // Vérifier que la chaîne n'est pas vide
    if (empty($ref)) {
        return false;
    }
    // La référence doit contenir exactement 34 caractères (14 date + 20 hash)
    // Format: YYYYMMDDHHmmss + hexadécimale (20 caractères)
    if (strlen($ref) !== 34) {
        return false;
    }
    // Vérifier que la chaîne contient UNIQUEMENT des caractères alphanumériques (a-z, A-Z, 0-9)
    if (!preg_match('/^[a-zA-Z0-9]{34}$/', $ref)) {
        return false;
    }
    // Vérifier les 14 premiers caractères (date: YYYYMMDDHHmmss)
    $dateStr = substr($ref, 0, 14);
    if (!preg_match('/^[0-9]{14}$/', $dateStr)) {
        return false;
    }
    // Vérifier que les 20 derniers caractères sont en hexadécimal
    $hashStr = substr($ref, 14, 20);
    if (!preg_match('/^[a-f0-9]{20}$/', $hashStr)) {
        return false;
    }
    return true; // Format valide
}
// Traiter les données reçues
$ref = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    // Nettoyer la saisie
    $ref = trim($_GET['ref']);
    // Valider le format
    if (!validateReference($ref)) {
        // Bloquer la session pour 30 secondes
        blockSessionForInvalidReference();
        http_response_code(400); // Bad Request
    ?>
        <!DOCTYPE html>
        <html lang="fr">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="refresh" content="1">
            <title>Référence invalide</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                }

                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }

                .modal-box {
                    background: white;
                    border-radius: 8px;
                    padding: 20px;
                    max-width: 300px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                    text-align: center;
                }

                .modal-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #d32f2f;
                    margin-bottom: 8px;
                }

                .modal-message {
                    font-size: 13px;
                    color: #666;
                    margin-bottom: 12px;
                }

                .modal-reason {
                    background: #fff3e0;
                    border-left: 3px solid #ff9800;
                    padding: 8px;
                    margin-bottom: 12px;
                    font-size: 11px;
                    color: #e65100;
                    text-align: left;
                    border-radius: 3px;
                }

                .timer {
                    font-size: 32px;
                    color: #d32f2f;
                    font-weight: bold;
                    margin-bottom: 3px;
                    font-family: 'Courier New', monospace;
                }

                .timer-label {
                    font-size: 10px;
                    color: #999;
                }
            </style>
        </head>

        <body>
            <div class="modal-overlay">
                <div class="modal-box">
                    <!--<div class="modal-title">⚠️ Référence invalide</div>
                    <div class="modal-message">Format incorrect</div>-->
                    <div class="modal-reason">
                        <strong>🔒 Sécurité:</strong> Session bloquée 30s
                    </div>
                    <!--<div class="timer">30</div>
                    <div class="timer-label">Secondes</div>-->
                </div>
            </div>
        </body>

        </html>
        <?php
        exit();
    } else {
        // La référence est valide, vous pouvez continuer le traitement
        //echo "✅ Référence valide : " . htmlspecialchars($ref);
        if ($type == 1) {
            require_once __DIR__ . '/../db.php';
            $sql = "SELECT * FROM facture_postpaid WHERE REF = :ref LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ref' => $ref]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                // Référence trouvée dans la base de données
                $date = $result['DATE'];
                $nom_agent = $result['NOM_AGENT'];
                $email_agent = $result['EMAIL'];
                $mois = $result['MOIS'];
                $annee = $result['ANNEE'];
                $montant = $result['MONTANT'];
                $numero_facture = $result['NUMERO_FACTURE'];
                $code = isset($result['CODE']) ? $result['CODE'] : 'N/A';
                $matricule = isset($result['MATRICULE']) ? $result['MATRICULE'] : 'N/A';
                $numero_compteur = $result['COMPTEUR'];
                $nom_cvs = isset($result['NOM_CVS']) ? $result['NOM_CVS'] : 'N/A';
                $nom_cvs_agent = isset($result['NOM_CVS_AGENT']) ? $result['NOM_CVS_AGENT'] : 'N/A';
                $kwh = isset($result['KWH']) ? $result['KWH'] : 0;
                $date_dgi = $result['DATE_DGI'];
                $code_dgi = $result['CODE_DGI'];
                $qr_code_dgi = $result['QR_CODE_DGI'];
                $count_dgi = $result['COUNT_DGI'];
                $nim_dgi = $result['NIM_DGI'];
                $json_data = $result['JSON'];
        ?>
                <!DOCTYPE html>
                <html lang="fr">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width,initial-scale=1">
                    <title>Facture OSAT Postpaid</title>
                    <style>
                        body {
                            font-family: 'Courier New', monospace;
                            width: 280px;
                            margin: auto;
                            background: #f4f4f4;
                            padding: 10px;
                        }

                        .ticket {
                            background: #fff;
                            padding: 12px;
                            border: 1px dashed #333;
                            border-radius: 6px;
                            margin-bottom: 20px;
                        }

                        .header {
                            text-align: center;
                            background: #d32f2f;
                            color: white;
                            padding: 8px;
                            border-radius: 4px;
                            margin-bottom: 10px;
                        }

                        .header h2 {
                            margin: 0;
                            font-size: 22px;
                        }

                        .header p {
                            margin: 3px 0 0 0;
                            font-size: 14px;
                        }

                        .infos {
                            font-size: 13px;
                            background: #ffebee;
                            padding: 6px;
                            border-radius: 4px;
                            margin-bottom: 8px;
                        }

                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                            font-size: 13px;
                        }

                        .label {
                            font-weight: bold;
                            color: #b71c1c;
                        }

                        .value {
                            text-align: right;
                            color: #333;
                        }

                        .separator {
                            border-top: 1px dashed #333;
                            margin: 6px 0;
                        }

                        .montant-box {
                            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
                            padding: 12px;
                            border-radius: 6px;
                            text-align: center;
                            margin-bottom: 8px;
                        }

                        .montant-label {
                            font-size: 11px;
                            color: rgba(255, 255, 255, 0.9);
                            margin-bottom: 6px;
                        }

                        .montant-value {
                            font-size: 18px;
                            font-weight: bold;
                            color: white;
                        }

                        .code-box {
                            background: linear-gradient(135deg, #f57c00 0%, #e64a19 100%);
                            padding: 16px;
                            border-radius: 6px;
                            text-align: center;
                            margin-bottom: 8px;
                        }

                        .code-label {
                            font-size: 13px;
                            color: rgba(255, 255, 255, 0.9);
                            margin-bottom: 8px;
                            letter-spacing: 1px;
                        }

                        .code-value {
                            font-size: 20px;
                            font-weight: bold;
                            color: white;
                            font-family: 'Courier New', monospace;
                            letter-spacing: 2px;
                            word-break: break-all;
                            line-height: 1.4;
                        }

                        .qr-box {
                            text-align: center;
                            padding: 10px;
                            background: #f9f9f9;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            margin-top: 8px;
                        }

                        .qr-box img {
                            width: 200px;
                            height: 200px;
                            border: 2px solid #d32f2f;
                            border-radius: 4px;
                        }

                        .dgi-info {
                            font-size: 11px;
                            background: #f0f0f0;
                            padding: 6px;
                            border-radius: 3px;
                            border-left: 3px solid #d32f2f;
                            margin-top: 8px;
                        }

                        .dgi-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                        }

                        .json-details {
                            font-size: 10px;
                            background: #fff3e0;
                            padding: 6px;
                            border-radius: 3px;
                            border-left: 3px solid #ff9800;
                            margin-top: 8px;
                            max-height: 120px;
                            overflow-y: auto;
                        }

                        .json-month {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                            padding: 2px 0;
                            border-bottom: 1px dotted #ddd;
                        }

                        .footer {
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            margin-top: 10px;
                        }

                        .btn-imprimer {
                            display: block;
                            width: 90%;
                            margin: 10px auto;
                            padding: 10px 20px;
                            background: #d32f2f;
                            color: white;
                            border: none;
                            border-radius: 5px;
                            font-weight: bold;
                            cursor: pointer;
                            font-size: 14px;
                        }

                        @media print {
                            .btn-imprimer {
                                display: none;
                            }

                            body {
                                background: white;
                            }
                        }
                    </style>
                </head>

                <body>
                    <div class="ticket">
                        <div class="header">
                            <h2>OSAT ÉNERGIE</h2>
                            <p>Facture Postpaid</p>
                        </div>

                        <!-- INFORMATIONS FACTURE -->
                        <div class="infos">
                            <div class="info-row"><span class="label">N° Facture:</span><span class="value"><?php echo htmlspecialchars($numero_facture); ?></span></div>
                            <div class="info-row"><span class="label">Date:</span><span class="value"><?php echo htmlspecialchars($date); ?></span></div>
                            <div class="info-row"><span class="label">Période:</span><span class="value"><?php echo htmlspecialchars($mois); ?>/<?php echo htmlspecialchars($annee); ?></span></div>
                        </div>

                        <!-- INFORMATIONS AGENT -->
                        <div class="infos">
                            <div class="info-row"><span class="label">Agent:</span><span class="value"><?php echo htmlspecialchars($nom_agent); ?></span></div>
                            <div class="info-row"><span class="label">Email:</span><span class="value" style="font-size: 11px;"><?php echo htmlspecialchars($email_agent); ?></span></div>
                        </div>

                        <!-- INFORMATIONS CLIENT -->
                        <div class="infos">
                            <div class="info-row"><span class="label">Matricule:</span><span class="value"><?php echo htmlspecialchars($matricule); ?></span></div>
                            <div class="info-row"><span class="label">Numéro Compteur:</span><span class="value"><?php echo htmlspecialchars($numero_compteur); ?></span></div>
                        </div>

                        <div class="separator"></div>

                        <!-- MONTANT -->
                        <div class="montant-box">
                            <div class="montant-label">MONTANT FACTURE</div>
                            <div class="montant-value"><?php echo number_format(floatval($montant), 2, ',', ' '); ?> CDF</div>
                        </div>

                        <!-- DÉTAILS TECHNIQUES -->
                        <div class="infos">
                            <div class="info-row"><span class="label">Éclairage (1%):</span><span class="value"><?php echo number_format(floatval($montant) * 0.01, 2, ',', ' '); ?></span></div>
                            <div class="info-row"><span class="label">TVA (16%):</span><span class="value"><?php echo number_format(floatval($montant) * 0.16, 2, ',', ' '); ?></span></div>
                        </div>

                        <div class="separator"></div>

                        <!-- DÉTAILS MENSUELS (JSON DATA) -->
                        <div class="code-box">
                            <div class="code-label">📅 DÉTAILS MENSUELS</div>
                            <div style="font-size: 10px; color: white; font-family: 'Courier New', monospace;">
                                <?php
                                if (!empty($json_data)) {
                                    $json_decoded = json_decode($json_data, true);
                                    if (is_array($json_decoded)) {
                                        // Vérifier s'il y a une structure "historique_calculs"
                                        if (isset($json_decoded['historique_calculs']) && is_array($json_decoded['historique_calculs']) && count($json_decoded['historique_calculs']) > 0) {
                                            // Boucler sur TOUS les calculs (pas juste le premier)
                                            foreach ($json_decoded['historique_calculs'] as $calcul) {
                                                $annee = $calcul['annee_selectionnee'] ?? '';
                                                if (isset($calcul['details_mois']) && is_array($calcul['details_mois'])) {
                                                    // Afficher le titre de l'année
                                                    echo '<div style="font-weight: bold; margin-top: 8px; margin-bottom: 6px; padding-bottom: 3px; border-bottom: 2px solid rgba(255,255,255,0.5); color: white;">📅 ' . htmlspecialchars($annee) . '</div>';

                                                    foreach ($calcul['details_mois'] as $detail) {
                                                        $mon = $detail['mois'] ?? '';
                                                        $amt = $detail['montant'] ?? 0;
                                                        echo '<div style="display: flex; justify-content: space-between; margin-bottom: 3px; padding: 2px 0 2px 8px; border-bottom: 1px dotted rgba(255,255,255,0.3);"><span>' . htmlspecialchars($mon) . '</span><span>' . number_format($amt, 2, ',', ' ') . ' CDF</span></div>';
                                                    }
                                                }
                                            }
                                        } else {
                                            echo '<div style="color: rgba(255,255,255,0.8);">Aucune donnée disponible</div>';
                                        }
                                    } else {
                                        echo '<div style="color: rgba(255,255,255,0.8);">Aucune donnée disponible</div>';
                                    }
                                } else {
                                    echo '<div style="color: rgba(255,255,255,0.8);">Aucune donnée disponible</div>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- QR CODE -->
                        <?php
                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_code_dgi) . "&ecc=M";
                        ?>
                        <div class="qr-box">
                            <img src="<?php echo $qr_url; ?>" alt="QR Code DGI">
                        </div>

                        <!-- INFORMATIONS DGI -->
                        <div class="dgi-info">
                            <div style="font-weight: bold; margin-bottom: 4px; color: #d32f2f;">📋 INFORMATIONS DGI</div>
                            <div class="dgi-row">
                                <span>Date DGI:</span>
                                <span><?php echo htmlspecialchars($date_dgi); ?></span>
                            </div>
                            <div class="dgi-row">
                                <span>Code DGI:</span>
                                <span style="font-size: 10px;"><?php echo htmlspecialchars($code_dgi); ?></span>
                            </div>
                            <div class="dgi-row">
                                <span>Count:</span>
                                <span><?php echo htmlspecialchars($count_dgi); ?></span>
                            </div>
                            <div class="dgi-row">
                                <span>NIM:</span>
                                <span><?php echo htmlspecialchars($nim_dgi); ?></span>
                            </div>
                        </div>

                        <div class="footer">
                            © <?php echo date('Y'); ?> OSAT ÉNERGIE<br>
                            Merci pour votre confiance ⚡
                        </div>
                    </div>

                    <button type="button" class="btn-imprimer" onclick="window.print()">🖨️ Imprimer</button>

                </body>

                </html>
            <?php
            }
        } else if ($type == 2) {
            require_once __DIR__ . '/../db.php';
            $sql = "SELECT * FROM facture_prepaid WHERE REF = :ref LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ref' => $ref]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                // Référence trouvée dans la base de données
                $date = $result['DATE'];
                $nom_agent = $result['NOM_AGENT'];
                $email_agent = $result['EMAIL'];
                $numero_facture = isset($result['NUMERO_FACTURE']) ? $result['NUMERO_FACTURE'] : 'N/A';
                $compteur = $result['COMPTEUR'];
                $mois = $result['MOIS'];
                $annee = $result['ANNEE'];
                $montant = $result['MONTANT'];
                $code = isset($result['CODE']) ? $result['CODE'] : 'N/A';
                $kwh = isset($result['KWH']) ? $result['KWH'] : 0;
                $nom_cvs = isset($result['NOM_CVS']) ? $result['NOM_CVS'] : 'N/A';
                $nom_cvs_agent = isset($result['NOM_CVS_AGENT']) ? $result['NOM_CVS_AGENT'] : 'N/A';
                $matricule = isset($result['MATRICULE']) ? $result['MATRICULE'] : 'N/A';

                $date_dgi = $result['DATE_DGI'];
                $code_dgi = $result['CODE_DGI'];
                $qr_code_dgi = $result['QR_CODE_DGI'];
                $count_dgi = $result['COUNT_DGI'];
                $nim_dgi = $result['NIM_DGI'];
                $json_data = $result['JSON'];
            ?>
                <!DOCTYPE html>
                <html lang="fr">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width,initial-scale=1">
                    <title>Facture OSAT</title>
                    <style>
                        body {
                            font-family: 'Courier New', monospace;
                            width: 280px;
                            margin: auto;
                            background: #f4f4f4;
                            padding: 10px;
                        }

                        .ticket {
                            background: #fff;
                            padding: 12px;
                            border: 1px dashed #333;
                            border-radius: 6px;
                            margin-bottom: 20px;
                        }

                        .header {
                            text-align: center;
                            background: #0055a4;
                            color: white;
                            padding: 8px;
                            border-radius: 4px;
                            margin-bottom: 10px;
                        }

                        .header h2 {
                            margin: 0;
                            font-size: 22px;
                        }

                        .header p {
                            margin: 3px 0 0 0;
                            font-size: 14px;
                        }

                        .infos {
                            font-size: 13px;
                            background: #e8f0fe;
                            padding: 6px;
                            border-radius: 4px;
                            margin-bottom: 8px;
                        }

                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                            font-size: 13px;
                        }

                        .label {
                            font-weight: bold;
                            color: #003366;
                        }

                        .value {
                            text-align: right;
                            color: #333;
                        }

                        .separator {
                            border-top: 1px dashed #333;
                            margin: 6px 0;
                        }

                        .montant-box {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            padding: 12px;
                            border-radius: 6px;
                            text-align: center;
                            margin-bottom: 8px;
                        }

                        .montant-label {
                            font-size: 11px;
                            color: rgba(255, 255, 255, 0.9);
                            margin-bottom: 6px;
                        }

                        .montant-value {
                            font-size: 18px;
                            font-weight: bold;
                            color: white;
                        }

                        .code-box {
                            background: linear-gradient(135deg, #f57c00 0%, #e64a19 100%);
                            padding: 16px;
                            border-radius: 6px;
                            text-align: center;
                            margin-bottom: 8px;
                        }

                        .code-label {
                            font-size: 13px;
                            color: rgba(255, 255, 255, 0.9);
                            margin-bottom: 8px;
                            letter-spacing: 1px;
                        }

                        .code-value {
                            font-size: 20px;
                            font-weight: bold;
                            color: white;
                            font-family: 'Courier New', monospace;
                            letter-spacing: 2px;
                            word-break: break-all;
                            line-height: 1.4;
                        }

                        .qr-box {
                            text-align: center;
                            padding: 10px;
                            background: #f9f9f9;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            margin-top: 8px;
                        }

                        .qr-box img {
                            width: 200px;
                            height: 200px;
                            border: 2px solid #0055a4;
                            border-radius: 4px;
                        }

                        .dgi-info {
                            font-size: 11px;
                            background: #f0f0f0;
                            padding: 6px;
                            border-radius: 3px;
                            border-left: 3px solid #0055a4;
                            margin-top: 8px;
                        }

                        .dgi-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                        }

                        .json-details {
                            font-size: 10px;
                            background: #fff3e0;
                            padding: 6px;
                            border-radius: 3px;
                            border-left: 3px solid #ff9800;
                            margin-top: 8px;
                            max-height: 120px;
                            overflow-y: auto;
                        }

                        .json-month {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 2px;
                            padding: 2px 0;
                            border-bottom: 1px dotted #ddd;
                        }

                        .footer {
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            margin-top: 10px;
                        }

                        .btn-imprimer {
                            display: block;
                            width: 90%;
                            margin: 10px auto;
                            padding: 10px 20px;
                            background: #28a745;
                            color: white;
                            border: none;
                            border-radius: 5px;
                            font-weight: bold;
                            cursor: pointer;
                            font-size: 14px;
                        }

                        @media print {
                            .btn-imprimer {
                                display: none;
                            }

                            body {
                                background: white;
                            }
                        }
                    </style>
                </head>

                <body>
                    <div class="ticket">
                        <div class="header">
                            <h2>OSAT ÉNERGIE</h2>
                            <p>Facture Prépaid</p>
                        </div>

                        <!-- INFORMATIONS FACTURE -->
                        <div class="infos">
                            <!--<div class="info-row"><span class="label">Référence:</span><span class="value"><?php echo htmlspecialchars($ref); ?></span></div>-->
                            <div class="info-row"><span class="label">Date:</span><span class="value"><?php echo htmlspecialchars($date); ?></span></div>
                            <div class="info-row"><span class="label">Période:</span><span class="value"><?php echo htmlspecialchars($mois); ?>/<?php echo htmlspecialchars($annee); ?></span></div>
                        </div>

                        <!-- INFORMATIONS AGENT -->
                        <div class="infos">
                            <div class="info-row"><span class="label">Agent:</span><span class="value"><?php echo htmlspecialchars($nom_agent); ?></span></div>
                            <div class="info-row"><span class="label">Email:</span><span class="value" style="font-size: 11px;"><?php echo htmlspecialchars($email_agent); ?></span></div>
                            <div class="info-row"><span class="label">CVS Agent:</span><span class="value"><?php echo htmlspecialchars($nom_cvs_agent); ?></span></div>
                        </div>

                        <!-- INFORMATIONS CLIENT -->
                        <div class="infos">
                            <div class="info-row"><span class="label">CVS Client:</span><span class="value"><?php echo htmlspecialchars($nom_cvs); ?></span></div>
                            <div class="info-row"><span class="label">Numéro Compteur:</span><span class="value"><?php echo htmlspecialchars($compteur); ?></span></div>
                        </div>

                        <div class="separator"></div>

                        <!-- MONTANT -->
                        <div class="montant-box">
                            <div class="montant-label">MONTANT FACTURE</div>
                            <div class="montant-value"><?php echo number_format(floatval($montant), 2, ',', ' '); ?> CDF</div>
                        </div>

                        <!-- DÉTAILS TECHNIQUES -->
                        <div class="infos">
                            <div class="info-row"><span class="label">KWH:</span><span class="value"><?php echo htmlspecialchars($kwh); ?></span></div>
                            <div class="info-row"><span class="label">Éclairage:</span><span class="value"><?php echo number_format(floatval($montant) * 0.01, 2, ',', ' '); ?></span></div>
                            <div class="info-row"><span class="label">TVA (16%):</span><span class="value"><?php echo number_format(floatval($montant) * 0.16, 2, ',', ' '); ?></span></div>
                        </div>

                        <div class="separator"></div>

                        <!-- CODE DE RECHARGE -->
                        <div class="code-box">
                            <div class="code-label">CODE DE RECHARGE</div>
                            <div class="code-value">
                                <?php
                                $code_formatted = htmlspecialchars($code);
                                if (strlen($code_formatted) > 5) {
                                    $code_formatted = implode('-', str_split($code_formatted, 5));
                                }
                                echo $code_formatted;
                                ?>
                            </div>
                        </div>

                        <!-- QR CODE -->
                        <?php
                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_code_dgi) . "&ecc=M";
                        ?>
                        <div class="qr-box">
                            <!--<p style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold; color: #0055a4;">🔲 SCAN MOI</p>-->
                            <img src="<?php echo $qr_url; ?>" alt="QR Code DGI">
                        </div>

                        <!-- INFORMATIONS DGI -->
                        <div class="dgi-info">
                            <div style="font-weight: bold; margin-bottom: 4px; color: #0055a4;">📋 INFORMATIONS DGI</div>
                            <div class="dgi-row">
                                <span>Date DGI:</span>
                                <span><?php echo htmlspecialchars($date_dgi); ?></span>
                            </div>
                            <div class="dgi-row">
                                <span>Code DGI:</span>
                                <span style="font-size: 10px;"><?php echo htmlspecialchars($code_dgi); ?></span>
                            </div>
                            <div class="dgi-row">
                                <span>Count:</span>
                                <span><?php echo htmlspecialchars($count_dgi); ?></span>
                            </div>
                            <div class="dgi-row">
                                <span>NIM:</span>
                                <span><?php echo htmlspecialchars($nim_dgi); ?></span>
                            </div>
                        </div>

                        <!-- JSON DATA -->
                        <?php if (!empty($json_data)): ?>
                            <div style="font-size: 11px; background: #f5f5f5; padding: 6px; border-radius: 3px; margin-top: 8px; border-left: 3px solid #ffc107; max-height: 100px; overflow-y: auto;" hidden>
                                <div style="font-weight: bold; margin-bottom: 4px; color: #f57c00;">📄 JSON Data</div>
                                <pre style="margin: 0; font-size: 9px; white-space: pre-wrap; word-wrap: break-word;"><?php echo htmlspecialchars(substr($json_data, 0, 200)); ?>...</pre>
                            </div>
                        <?php endif; ?>

                        <div class="footer">
                            © <?php echo date('Y'); ?> OSAT ÉNERGIE<br>
                            Merci pour votre confiance ⚡
                        </div>
                    </div>

                    <button type="button" class="btn-imprimer" onclick="window.print()">🖨️ Imprimer</button>

                </body>

                </html>
<?php
            }
        }
    }
}
// Votre code ici - $ref est maintenant sûr et validé
if (!empty($ref)) {
    // Code sécurisé - la référence a été validée 
}

?>