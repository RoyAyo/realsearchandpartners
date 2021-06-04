<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return 'hello world';
});

$router->group(['prefix' => 'api'], function() use ($router){

    // URL: {{BASE_DOMAIN}}/api/auth
    $router->group(['prefix'=> 'auth'], function() use ($router) {

        // URL: {{BASE_DOMAIN}}/api/auth/register
        $router->post('/register', 'AuthController@register');

        // URL: {{BASE_DOMAIN}}/api/auth/login
        $router->post('/login', 'AuthController@login');

        // URL: {{BASE_DOMAIN}}/api/auth/logout
        $router->get('/logout', 'AuthController@logout');
    });

    // URL: {{BASE_DOMAIN}}/api/transactions
    $router->group(['prefix'=> 'transactions'], function() use ($router) {

        // URL: {{BASE_DOMAIN}}/api/auth/register
        $router->get('/', 'TransactionsController@register');
    });

    // URL: {{BASE_DOMAIN}}/api/transactions
    $router->group(['prefix'=> 'payment'], function() use ($router) {

        // URL: {{BASE_DOMAIN}}/api/auth/register
        $router->post('/initialize', 'TransactionsController@initializePayment');

        $router->post('/verify', 'TransactionsController@verifyPayment');
    });
});