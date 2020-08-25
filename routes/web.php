<?php

declare(strict_types=1);

use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInMiddleware;
use Flextype\Plugin\Acl\Middlewares\AclIsUserLoggedInRolesInMiddleware;

flextype()->group('/' . $admin_route . '/accounts', function () {
    flextype()->get('/login', 'AccountsAdminController:login')->setName('admin.accounts.login');
    flextype()->post('/login', 'AccountsAdminController:loginProcess')->setName('admin.accounts.loginProcess');
    flextype()->get('/reset-password', 'AccountsAdminController:resetPassword')->setName('admin.accounts.resetPassword');
    flextype()->post('/reset-password', 'AccountsAdminController:resetPasswordProcess')->setName('admin.accounts.resetPasswordProcess');
    flextype()->get('/new-password/{email}/{hash}', 'AccountsAdminController:newPasswordProcess')->setName('admin.accounts.newPasswordProcess');
    flextype()->get('/registration', 'AccountsAdminController:registration')->setName('admin.accounts.registration');
    flextype()->post('/registration', 'AccountsAdminController:registrationProcess')->setName('admin.accounts.registrationProcess');
})->add('csrf');

flextype()->group('/' . $admin_route . '/accounts', function () {
    flextype()->get('', 'AccountsAdminController:index')->setName('admin.accounts.index');
    flextype()->get('/add', 'AccountsAdminController:add')->setName('admin.accounts.add');
    flextype()->post('/add', 'AccountsAdminController:addProcess')->setName('admin.accounts.addProcess');
    flextype()->get('/edit', 'AccountsAdminController:edit')->setName('admin.accounts.edit');
    flextype()->post('/edit', 'AccountsAdminController:editProcess')->setName('admin.accounts.editProcess');
    flextype()->post('/delete', 'AccountsAdminController:deleteProcess')->setName('admin.accounts.deleteProcess');
    flextype()->post('/logout', 'AccountsAdminController:logoutProcess')->setName('admin.accounts.logoutProcess');
})->add(new AclIsUserLoggedInMiddleware(['redirect' => 'admin.accounts.login']))
  ->add(new AclIsUserLoggedInRolesInMiddleware(['redirect' => (flextype()->getContainer()->acl->isUserLoggedIn() ? 'admin.accounts.no-access' : 'admin.accounts.login'),
                                                      'roles' => 'admin']))
  ->add('csrf');
