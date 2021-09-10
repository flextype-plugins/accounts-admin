<?php

declare(strict_types=1);

use Flextype\Middlewares\CsrfMiddleware;
use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInMiddleware;
use Flextype\Plugin\AccountsAdmin\Controllers\AccountsAdminController;
use Slim\Routing\RouteCollectorProxy;

app()->group('/' . $adminRoute . '/accounts', function (RouteCollectorProxy $group) {
    $group->get('/no-access', [AccountsAdminController::class, 'noAccess'])->setName('admin.accounts.no-access');
    $group->get('/login', [AccountsAdminController::class, 'login'])->setName('admin.accounts.login');
    $group->post('/login', [AccountsAdminController::class, 'loginProcess'])->setName('admin.accounts.loginProcess');
    $group->get('/reset-password', [AccountsAdminController::class, 'resetPassword'])->setName('admin.accounts.resetPassword');
    $group->post('/reset-password', [AccountsAdminController::class, 'resetPasswordProcess'])->setName('admin.accounts.resetPasswordProcess');
    $group->get('/new-password/{id}/{hash}', [AccountsAdminController::class, 'newPasswordProcess'])->setName('admin.accounts.newPasswordProcess');
    $group->get('/registration', [AccountsAdminController::class, 'registration'])->setName('admin.accounts.registration');
    $group->post('/registration', [AccountsAdminController::class, 'registrationProcess'])->setName('admin.accounts.registrationProcess');
})->add(new CsrfMiddleware());

app()->group('/' . $adminRoute . '/accounts', function (RouteCollectorProxy $group) {
    $group->post('/logout', [AccountsAdminController::class, 'logoutProcess'])->setName('admin.accounts.logoutProcess');
})->add(new AclIsUserLoggedInMiddleware(['redirect' => 'admin.accounts.login']))
  ->add(new CsrfMiddleware());