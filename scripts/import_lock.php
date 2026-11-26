<?php
/**
 * Verrou pour les mesures de performance (import 50 000 produits).
 *
 * ⚠️ POURQUOI CE FICHIER EXISTE
 * -----------------------------
 * Deux mesures lancées EN PARALLÈLE sur la même base jetable se
 * marchent dessus : l'une fait `DROP DATABASE` pendant que l'autre
 * écrit. La survivante rapporte alors des milliers d'erreurs FANTÔMES
 * (« Duplicate entry », « Unknown database ») — ce qui fait croire à
 * une régression du code alors que le code est bon.
 *
 * ⚠️ `flock()` NE MARCHE PAS ICI
 * -------------------------------
 * Vérifié sur ce setup (Windows/MSYS) : deux processus PHP distincts
 * obtiennent TOUS DEUX le verrou `flock`. Le verrou par EXISTENCE DE
 * FICHIER, lui, fonctionne partout.
 *
 * @see scripts/measure_import_csv.php  (l'utilisateur du verrou)
 * @see scripts/verify_import_lock.php  (le test)
 */

/** Le fichier de verrou du processus courant (global pour le libérer). */
$GLOBALS['import_lock_file'] = null;

/**
 * Le processus existe-t-il encore ?
 *
 * ⚠️ `posix_kill` n'est pas disponible (pas d'extension posix sous ce
 *    PHP), et sous MSYS `$!` renvoie le PID du SHELL, pas de PHP — un
 *    PID lu dans un fichier de verrou ne peut donc pas être reliably
 *    comparé à `getmypid()`.
 *
 *    On s'appuie donc sur l'ÂGE du fichier : un verrou plus vieux que
 *    $maxAgeSecondes est considéré comme orphelin. Un import de 50 000
 *    produits dure ~4 minutes ; 2 heures de marge Large.
 *
 * @param int $pid              PID du détenteur (informatif)
 * @param int $maxAgeSecondes   Âge au-delà duquel le verrou est orphelin
 */
function import_lock_is_orphan(int $pid, int $maxAgeSecondes = 7200): bool
{
    global $import_lock_file;

    if ($import_lock_file === null || !is_file($import_lock_file)) {
        return true;
    }

    $mtime = @filemtime($import_lock_file);
    if ($mtime === false) {
        return false;   // on ne sait pas : on ne libère pas
    }

    return (time() - $mtime) > $maxAgeSecondes;
}

/**
 * Prend le verrou sur une base jetable.
 *
 * @param string $db  Nom de la base (le verrou est par base)
 * @return bool      true si le verrou est pris, false si déjà pris
 */
function import_lock_acquire(string $db): bool
{
    global $import_lock_file;

    $fichier = sys_get_temp_dir() . '/pos_import_' . $db . '.lock';

    if (is_file($fichier)) {
        $pid = (int)trim((string)@file_get_contents($fichier));
        if (import_lock_is_orphan($pid)) {
            fwrite(STDERR, "Verrou orphelin (pid {$pid}) : libéré.\n");
            @unlink($fichier);
        } else {
            fwrite(STDERR, "Une mesure est DÉJÀ en cours sur {$db}"
                . ($pid > 0 ? " (pid {$pid})" : '') . ".\n");
            fwrite(STDERR, "Attends qu'elle finisse.\n");
            return false;
        }
    }

    // Création EXCLUSIVE : si le fichier apparaît entre-temps, on échoue.
    $handle = @fopen($fichier, 'x');
    if (!$handle) {
        fwrite(STDERR, "Impossible de prendre le verrou sur {$db}.\n");
        return false;
    }
    fwrite($handle, (string)getmypid());
    fflush($handle);
    fclose($handle);

    $import_lock_file = $fichier;
    return true;
}

/**
 * Libère le verrou.
 */
function import_lock_release(): void
{
    global $import_lock_file;

    if ($import_lock_file === null || !is_file($import_lock_file)) {
        return;
    }

    /*
     * ⚠️ On ne supprime le verrou que s'il est TOUJOURS LE NÔTRE.
     *
     *    Sinon : le run A se termine, son `register_shutdown_function`
     *    supprime le fichier de verrou — mais le run B (qui a démarré
     *    après) tient maintenant le verrou. A le supprime → B et C
     *    tournent en parallèle sur la même base → des milliers d'erreurs
     *    fantômes. C'est EXACTEMENT ce qui s'est passé.
     */
    $pid = (int)trim((string)@file_get_contents($import_lock_file));
    if ($pid !== getmypid()) {
        return;   // ce verrou appartient à quelqu'un d'autre : on n'y touche pas
    }

    @unlink($import_lock_file);
    $import_lock_file = null;
}
