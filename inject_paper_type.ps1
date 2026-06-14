$filePath = "c:\Users\CESAR PAYSAYO\Documents\ArcaneCore\Projet\sys-pos\pos_systeme\app\views\layout\footer.php"
$content = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)
$marker = '    <script src="./assets/js/recharges.js?v=1.0.6"></script>'
$newLine = '    <script src="./assets/js/paper-type.js?v=1.0.0"></script>'
if ($content -notlike "*paper-type.js*") {
    $content = $content.Replace($marker, $marker + [Environment]::NewLine + $newLine)
    [System.IO.File]::WriteAllText($filePath, $content, [System.Text.UTF8Encoding]::new($false))
    Write-Output "Added paper-type.js"
} else {
    Write-Output "Already present"
}
