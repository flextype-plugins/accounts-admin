<?php

declare(strict_types=1);

namespace Flextype;

$app->group('/' . $admin_route, function () use ($app) : void {

    // FieldsetsController
    $app->get('/accounts', 'AccountsAdminController:index')->setName('admin.accounts.index');
    $app->get('/accounts/add', 'AccountsAdminController:add')->setName('admin.accounts.add');
    $app->post('/accounts/add', 'AccountsAdminController:addProcess')->setName('admin.accounts.addProcess');
    $app->get('/accounts/edit', 'AccountsAdminController:edit')->setName('admin.accounts.edit');
    $app->post('/accounts/edit', 'AccountsAdminController:editProcess')->setName('admin.accounts.editProcess');
    $app->post('/accounts/delete', 'AccountsAdminController:deleteProcess')->setName('admin.accounts.deleteProcess');

})->add(new AdminPanelIsUserLoggedInMiddleware($flextype))->add('csrf');
