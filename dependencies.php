<?php

declare(strict_types=1);

/**
 * @link http://digital.flextype.org
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flextype\Plugin\AccountsAdmin;

use Flextype\Plugin\AccountsAdmin\Controllers\AccountsAdminController;
use Slim\Flash\Messages;
use Flextype\Component\I18n\I18n;
use function Flextype\Component\I18n\__;

// Add Admin Navigation
$flextype->container('registry')->set('plugins.admin.settings.navigation.extends.accounts', ['title' => __('accounts_admin_accounts'),'icon' => 'fas fa-users', 'link' => $flextype->container('router')->pathFor('admin.accounts.index')]);

/**
 * Add Accounts Admin Controller to Flextype container
 */
$flextype->container()['AccountsAdminController'] = static function () use ($flextype) {
    return new AccountsAdminController($flextype);
};
