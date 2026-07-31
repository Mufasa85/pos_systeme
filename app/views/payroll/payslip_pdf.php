<!-- Payroll Payslip PDF placeholder -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>PDF du bulletin</h2>
    </div>
    <div class="card">
        <div class="card-body">
            <p>Le PDF est généré automatiquement via l'API : <code>/api/payroll/payslips/pdf/{id}</code>.</p>
            <p>Le flux est directement affiché ou téléchargé par le navigateur.</p>
            <a href="/payroll/payslips" class="btn btn-secondary">Retour aux bulletins</a>
        </div>
    </div>
</div>
