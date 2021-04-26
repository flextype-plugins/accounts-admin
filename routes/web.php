<?php

declare(strict_types=1);

use Flextype\Middlewares\CsrfMiddleware;
use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInMiddleware;
use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInRolesInMiddleware;
use Flextype\Plugin\AccountsAdmin\Controllers\AccountsAdminController;

flextype()->group('/' . $admin_route . '/accounts', function () {
    flextype()->get('/no-access', function() { return 'no-access'; })->setName('admin.accounts.no-access');
    flextype()->get('/login', AccountsAdminController::class . ':login')->setName('admin.accounts.login');
    flextype()->post('/login', AccountsAdminController::class . ':loginProcess')->setName('admin.accounts.loginProcess');
    flextype()->get('/reset-password', AccountsAdminController::class . ':resetPassword')->setName('admin.accounts.resetPassword');
    flextype()->post('/reset-password', AccountsAdminController::class . ':resetPasswordProcess')->setName('admin.accounts.resetPasswordProcess');
    flextype()->get('/new-password/{email}/{hash}', AccountsAdminController::class . ':newPasswordProcess')->setName('admin.accounts.newPasswordProcess');
    flextype()->get('/registration', AccountsAdminController::class . ':registration')->setName('admin.accounts.registration');
    flextype()->post('/registration', AccountsAdminController::class . ':registrationProcess')->setName('admin.accounts.registrationProcess');
})->add(new CsrfMiddleware());

flextype()->group('/' . $admin_route . '/accounts', function () {
    flextype()->get('', AccountsAdminController::class . ':index')->setName('admin.accounts.index');
    flextype()->get('/add', AccountsAdminController::class . ':add')->setName('admin.accounts.add');
    flextype()->post('/add', AccountsAdminController::class . ':addProcess')->setName('admin.accounts.addProcess');
    flextype()->get('/edit', AccountsAdminController::class . ':edit')->setName('admin.accounts.edit');
    flextype()->post('/edit', AccountsAdminController::class . ':editProcess')->setName('admin.accounts.editProcess');
    flextype()->post('/delete', AccountsAdminController::class . ':deleteProcess')->setName('admin.accounts.deleteProcess');
    flextype()->post('/logout', AccountsAdminController::class . ':logoutProcess')->setName('admin.accounts.logoutProcess');
})->add(new AclIsUserLoggedInMiddleware(['redirect' => 'admin.accounts.login']))
  ->add(new AclIsUserLoggedInRolesInMiddleware(['redirect' => (flextype()->getContainer()->acl->isUserLoggedIn() ? 'admin.accounts.no-access' : 'admin.accounts.login'),
                                                'roles' => 'admin']))
  ->add(new CsrfMiddleware());
