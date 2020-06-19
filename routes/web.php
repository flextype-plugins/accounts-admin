<?php

declare(strict_types=1);

namespace Flextype;

$app->group('/' . $admin_route . '/accounts', function () use ($app) : void {
    $app->get('/login', 'AccountsAdminController:login')->setName('admin.accounts.login');
    $app->post('/login', 'AccountsAdminController:loginProcess')->setName('admin.accounts.loginProcess');
    $app->get('/reset-password', 'AccountsAdminController:resetPassword')->setName('admin.accounts.resetPassword');
    $app->post('/reset-password', 'AccountsAdminController:resetPasswordProcess')->setName('admin.accounts.resetPasswordProcess');
    $app->get('/new-password/{username}/{hash}', 'AccountsAdminController:newPasswordProcess')->setName('admin.accounts.newPasswordProcess');
    $app->get('/registration', 'AccountsAdminController:registration')->setName('admin.accounts.registration');
    $app->post('/registration', 'AccountsAdminController:registrationProcess')->setName('admin.accounts.registrationProcess');
})->add('csrf');


$app->group('/' . $admin_route . '/accounts', function () use ($app) : void {
    $app->get('', 'AccountsAdminController:index')->setName('admin.accounts.index');
    $app->get('/add', 'AccountsAdminController:add')->setName('admin.accounts.add');
    $app->post('/add', 'AccountsAdminController:addProcess')->setName('admin.accounts.addProcess');
    $app->get('/edit', 'AccountsAdminController:edit')->setName('admin.accounts.edit');
    $app->post('/edit', 'AccountsAdminController:editProcess')->setName('admin.accounts.editProcess');
    $app->post('/delete', 'AccountsAdminController:deleteProcess')->setName('admin.accounts.deleteProcess');
    $app->post('/logout', 'AccountsAdminController:logoutProcess')->setName('admin.accounts.logoutProcess');
})->add(new AclAccountIsUserLoggedInMiddleware(['container' => $flextype, 'redirect' => 'admin.users.login']))
  ->add(new AclAccountsIsUserLoggedInRolesOneOfMiddleware(['container' => $flextype, 'redirect' => 'admin.accounts.login', 'roles' => 'admin']))
  ->add('csrf');
