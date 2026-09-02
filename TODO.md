# TODO - Filtre boutique (super_admin) sur la page produits

1. [x] Créer le plan
2. [ ] `app/controllers/PageController.php` : charger et passer `$shops` à la vue produits
3. [ ] `app/views/produits.php` :
     - Ajouter `<select id="shop-filter">` (visible uniquement super_admin)
     - Ajouter attribut `data-shop` sur chaque `<tr>`
4. [ ] `public/assets/js/app.js` : gérer le filtre boutique dans `initProductsTabs()`
5. [ ] Vérification finale

## Commandes à exécuter 

```bash
composer update
npm install
npx playwright install
```

## Type               Commande

Tests PHP             composer test
Unitaire              composer test:unit
Intégration           composer test:integration
Fonctionnel           composer test:functional
PHP+statique+style    composer test:all
Analyse statique      composer static
Style PHP             composer cs:check / composer cs:fix
ESLint                npm run lint / npm run lint:fix
E2E                   npm run test:e2e
Visuel                npm run test:visual