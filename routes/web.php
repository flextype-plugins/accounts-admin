<?php

declare(strict_types=1);

use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInMiddleware;
use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInRolesInMiddleware;
use Flextype\Plugin\AccountsAdmin\Middlewares\AccountsIsSupperAdminRegisteredMiddleware;

$app->group('/' . $admin_route . '/accounts', function () use ($app, $flextype) {
    $app->get('/login', 'AccountsAdminController:login')->setName('admin.accounts.login');
    $app->post('/login', 'AccountsAdminController:loginProcess')->setName('admin.accounts.loginProcess');
    $app->get('/reset-password', 'AccountsAdminController:resetPassword')->setName('admin.accounts.resetPassword');
    $app->post('/reset-password', 'AccountsAdminController:resetPasswordProcess')->setName('admin.accounts.resetPasswordProcess');
    $app->get('/new-password/{email}/{hash}', 'AccountsAdminController:newPasswordProcess')->setName('admin.accounts.newPasswordProcess');
    $app->get('/registration', 'AccountsAdminController:registration')->setName('admin.accounts.registration')->add(new AccountsIsSupperAdminRegisteredMiddleware(['container' => $flextype, 'redirect' => 'admin.accounts.login']));
    $app->post('/registration', 'AccountsAdminController:registrationProcess')->setName('admin.accounts.registrationProcess')->add(new AccountsIsSupperAdminRegisteredMiddleware(['container' => $flextype, 'redirect' => 'admin.accounts.login']));
})->add('csrf');

$app->group('/' . $admin_route . '/accounts', function () use ($app, $flextype) {
    $app->get('', 'AccountsAdminController:index')->setName('admin.accounts.index');
    $app->get('/add', 'AccountsAdminController:add')->setName('admin.accounts.add');
    $app->post('/add', 'AccountsAdminController:addProcess')->setName('admin.accounts.addProcess');
    $app->get('/edit', 'AccountsAdminController:edit')->setName('admin.accounts.edit');
    $app->post('/edit', 'AccountsAdminController:editProcess')->setName('admin.accounts.editProcess');
    $app->post('/delete', 'AccountsAdminController:deleteProcess')->setName('admin.accounts.deleteProcess');
    $app->post('/logout', 'AccountsAdminController:logoutProcess')->setName('admin.accounts.logoutProcess');
})->add(new AclIsUserLoggedInMiddleware(['container' => $flextype, 'redirect' => 'admin.accounts.login']))
  ->add(new AclIsUserLoggedInRolesInMiddleware(['container' => $flextype,
                                                'redirect' => ($flextype->acl->isUserLoggedIn() ? 'admin.accounts.no-access' : 'admin.accounts.login'),
                                                'roles' => 'admin']))
  ->add('csrf');


$app->group('/' . $admin_route . '/accounts', function () use ($app, $flextype) : void {
    $app->get('/no-access', function($request, $response, $args) {
        return $response->write("You have no access to this page.");
    })->setName('admin.accounts.no-access');
});
