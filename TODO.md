# TODO - Filtre boutique (super_admin) sur la page produits

1. [x] Créer le plan
2. [ ] `app/controllers/PageController.php` : charger et passer `$shops` à la vue produits
3. [ ] `app/views/produits.php` :
     - Ajouter `<select id="shop-filter">` (visible uniquement super_admin)
     - Ajouter attribut `data-shop` sur chaque `<tr>`
4. [ ] `public/assets/js/app.js` : gérer le filtre boutique dans `initProductsTabs()`
5. [ ] Vérification finale
