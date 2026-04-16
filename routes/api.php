<?php
// routes/api.php

/** @var Router $router */

$router->get('/api/produits', 'ProductController@apiList');
$router->get('/api/produit', 'ProductController@apiFind');
$router->post('/api/produit', 'ProductController@apiCreate');

$router->post('/api/vente', 'SaleController@apiCreate');
