<?php
/**
 * 用法示例,注意自动加载方式
 */
use Clovers\Route\Route;
$router = new Route();
Route::get('/', 'HomeController@index');
Route::get('user/{id}/comment/{comment_id}', function($id){
var_dump($id);
});
Route::get('user/{id}/', 'UserController@show');
Route::any('test/code/{id?}', 'CodeController@send');
$router->run();
