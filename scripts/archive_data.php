<?php

/**
 * Script d'archivage mensuel des ventes
 *
 * Ce script déplace les ventes > 3 mois vers les tables d'archive
 * et purge les audit_logs > 6 mois.
 *
 * Usage: php scripts/archive_data.php
 * Cron:  0 2 1 * * php /path/to/scripts/archive_data.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$db = \App\Core\Database::getInstance()->getConnection();

$archiveMonths = 3;
$purgeAuditMonths = 6;
$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$archiveMonths} months"));
$auditCutoffDate = date('Y-m-d H:i:s', strtotime("-{$purgeAuditMonths} months"));

echo '[' . date('Y-m-d H:i:s') . "] Début de l'archivage...\n";
echo "  → Archivage des ventes antérieures au {$cutoffDate}\n";
echo "  → Purge des audit_logs antérieurs au {$auditCutoffDate}\n\n";

try {
    $db->beginTransaction();

    // 1. Compter les ventes à archiver
    $stmt = $db->prepare('SELECT COUNT(*) as total FROM ventes WHERE date < ?');
    $stmt->execute([$cutoffDate]);
    $count = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    echo "  Ventes à archiver : {$count}\n";

    if ($count > 0) {
        // 2. Copier les ventes vers ventes_archive
        $sql = 'INSERT INTO ventes_archive 
                    (id, numero_facture, client_id, sous_total_ht, tva, total, payments, 
                     vendeur_id, shop_id, date, dateDGI, qrCode, codeDEFDGI, counters, nim, comment, service)
                SELECT id, numero_facture, client_id, sous_total_ht, tva, total, payments,
                       vendeur_id, shop_id, date, dateDGI, qrCode, codeDEFDGI, counters, nim, comment, service
                FROM ventes WHERE date < ?';
        $stmt = $db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        $archivedSales = $stmt->rowCount();
        echo "  ✓ {$archivedSales} ventes archivées\n";

        // 3. Copier les détails de vente vers details_vente_archive
        $sql = 'INSERT INTO details_vente_archive 
                    (id, vente_id, produit_id, quantite, prix, remise_type, remise_value, 
                     taxe_specifique_type, taxe_specifique_value)
                SELECT dv.id, dv.vente_id, dv.produit_id, dv.quantite, dv.prix, 
                       dv.remise_type, dv.remise_value, dv.taxe_specifique_type, dv.taxe_specifique_value
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                WHERE v.date < ?';
        $stmt = $db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        $archivedDetails = $stmt->rowCount();
        echo "  ✓ {$archivedDetails} détails de vente archivés\n";

        // 4. Supprimer les détails de vente originaux
        $sql = 'DELETE dv FROM details_vente dv 
                INNER JOIN ventes v ON dv.vente_id = v.id 
                WHERE v.date < ?';
        $stmt = $db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        echo "  ✓ Détails originaux supprimés\n";

        // 5. Supprimer les ventes originales
        $sql = 'DELETE FROM ventes WHERE date < ?';
        $stmt = $db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        echo "  ✓ Ventes originales supprimées\n";
    } else {
        echo "  → Rien à archiver.\n";
    }

    // 6. Purger les audit_logs anciens
    $stmt = $db->prepare('SELECT COUNT(*) as total FROM audit_logs WHERE created_at < ?');
    $stmt->execute([$auditCutoffDate]);
    $auditCount = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

    if ($auditCount > 0) {
        $stmt = $db->prepare('DELETE FROM audit_logs WHERE created_at < ?');
        $stmt->execute([$auditCutoffDate]);
        echo "\n  ✓ {$auditCount} entrées audit_logs purgées (> {$purgeAuditMonths} mois)\n";
    } else {
        echo "\n  → Aucun audit_log à purger.\n";
    }

    // 7. Purger les login_attempts anciens (> 30 jours)
    $stmt = $db->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $stmt->execute();
    $loginPurged = $stmt->rowCount();
    if ($loginPurged > 0) {
        echo "  ✓ {$loginPurged} tentatives de connexion purgées (> 30 jours)\n";
    }

    // 8. Purger les OTP expirés
    $stmt = $db->prepare('DELETE FROM otp_codes WHERE expires_at < NOW()');
    $stmt->execute();
    $otpPurged = $stmt->rowCount();
    if ($otpPurged > 0) {
        echo "  ✓ {$otpPurged} codes OTP expirés purgés\n";
    }

    // 9. Purger les password_resets expirés
    $stmt = $db->prepare('DELETE FROM password_resets WHERE expires_at < NOW()');
    $stmt->execute();
    $resetPurged = $stmt->rowCount();
    if ($resetPurged > 0) {
        echo "  ✓ {$resetPurged} tokens de réinitialisation expirés purgés\n";
    }

    $db->commit();
    echo "\n[" . date('Y-m-d H:i:s') . "] ✅ Archivage terminé avec succès !\n";

} catch (\Exception $e) {
    $db->rollBack();
    echo "\n[" . date('Y-m-d H:i:s') . '] ❌ ERREUR : ' . $e->getMessage() . "\n";
    error_log('[ARCHIVE] Error: ' . $e->getMessage());
    exit(1);
}
