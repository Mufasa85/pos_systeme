<?php
// routes/api.php

/** @var Router $router */

$router->get('/api/produits', 'ProductController@apiList');
$router->get('/api/produit', 'ProductController@apiFind');
$router->post('/api/produit', 'ProductController@apiCreate');
$router->post('/api/produit/update', 'ProductController@apiUpdate');
$router->post('/api/produit/delete', 'ProductController@apiDelete');

$router->post('/api/vente', 'SaleController@apiCreate');
