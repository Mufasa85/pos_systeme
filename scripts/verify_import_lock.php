<?php
/**
 * Vérifie le verrou des mesures de performance.
 *
 * Ce test teste le MODULE (`scripts/import_lock.php`), pas le script de
 * mesure complet : lancer deux imports de 50 000 produits pour vérifier
 * un verrou prendrait 8 minutes.
 *
 * ⚠️ Pourquoi ce verrou existe : deux mesures parallèles sur la même
 *    base jetable se marchent dessus (l'une fait `DROP DATABASE` sous
 *    les pieds de l'autre) et la survivante rapporte des milliers
 *    d'erreurs fantômes. Ça m'a fait perdre du temps à « corriger » un
 *    code qui était déjà bon.
 */

require_once __DIR__ . '/import_lock.php';

$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    printf(
        "  [%s] %s%s\n",
        $ok ? 'OK' : 'KO',
        $label,
        $detail !== '' ? " — {$detail}" : ''
    );
}

$db = 'pos_lock_test_' . getmypid();
$lock = sys_get_temp_dir() . '/pos_import_' . $db . '.lock';

@unlink($lock);

echo "=== 1. Prendre le verrou ===\n";
$acquis = import_lock_acquire($db);
check('le verrou est pris', $acquis);
check('le fichier de verrou existe', is_file($lock), $lock);

if ($acquis) {
    $pid = (int)trim((string)@file_get_contents($lock));
    check('il contient le PID du détenteur', $pid === getmypid(), 'pid=' . $pid);
}

echo "\n=== 2. Un 2e détenteur est REFUSÉ ===\n";
// On simule un 2e processus enendant le verrou « détenu par un autre ».
$autre = ['pid' => 999999, 'fichier' => $lock];
$GLOBALS['import_lock_file'] = $lock;

$orphelin = import_lock_is_orphan($autre['pid']);
check(
    'un verrou tenu par un PID inconnu n\'est PAS orphelin',
    $orphelin === false,
    'is_orphan=' . var_export($orphelin, true)
);

echo "\n=== 3. Libérer ===\n";
import_lock_release();
check('le verrou est libéré', !is_file($lock));

echo "\n=== 4. On peut le reprendre ===\n";
$GLOBALS['import_lock_file'] = null;
check('le verrou est reprenable', import_lock_acquire($db));
import_lock_release();

echo "\n=== 5. Libérer NE doit PAS tuer le verrou d'un AUTRE ===\n";
/*
 * ⚠️ REGRESSION : le run A se termine et son `shutdown` supprimait le
 *    fichier de verrou — alors que le run B (démarré entre-temps)
 *    venait de le prendre. B perdait son verrou, et C pouvait démarrer
 *    en parallèle : deux mesures sur la même base → des milliers
 *    d'erreurs fantômes.
 */
$GLOBALS['import_lock_file'] = $lock;
file_put_contents($lock, (string)(getmypid() + 1));   // le verrou appartient à quelqu'un d'AUTRE
import_lock_release();
check(
    'le verrou d\'un autre processus est PRÉSERVÉ',
    is_file($lock),
    'il ne faut pas supprimer le verrou de quelqu\'un d\'autre'
);
@unlink($lock);

echo "\n=== 6. Un verrou ORPHELIN est récupérable ===\n";
// Un fichier de verrou « vieux » (mtime dans le passé) est orphelin.
file_put_contents($lock, '999999');
touch($lock, time() - 7201);   // 2h01 dans le passé
$GLOBALS['import_lock_file'] = null;

check(
    'un verrou de plus de 2 h est considéré orphelin',
    import_lock_is_orphan(999999) === true
);
check(
    'le verrou orphelin est repris',
    import_lock_acquire($db)
);
import_lock_release();

@unlink($lock);

echo "\n" . ($failures === 0
    ? "=== LE VERROU FONCTIONNE ===\n"
    : "=== {$failures} ÉCHEC(S) ===\n");

exit($failures === 0 ? 0 : 1);
