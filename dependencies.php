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
flextype('registry')->set('plugins.admin.settings.navigation.extends.accounts', ['title' => __('accounts_admin_accounts'), 'icon' => ['name' => 'users', 'set' => 'fontawesome|solid'], 'link' => flextype('router')->pathFor('admin.accounts.index')]);
