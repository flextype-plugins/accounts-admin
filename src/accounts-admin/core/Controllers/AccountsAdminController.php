<?php

declare(strict_types=1);

/**
 * @link https://flextype.org
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flextype\Plugin\AccountsAdmin\Controllers;

use Slim\Psr7\Response as R;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use Ramsey\Uuid\Uuid;
use Slim\Http\Environment;
use Slim\Http\Uri;
use const PASSWORD_BCRYPT;
use function array_merge;
use function bin2hex;
use function date;
use function Flextype\Component\I18n\__;
use function password_hash;
use function password_verify;
use function random_bytes;
use function strtr;
use function time;
use function trim;
use function redirect;

/**
 * @property twig $twig
 * @property Fieldsets $fieldsets
 * @property Router $router
 * @property Flash $flash
 */
class AccountsAdminController
{
    /**
     * Login page
     *
     * @param Request  $request  ServerRequestInterface
     * @param Response $response ResponseInterface.
     */
    public function login(Request $request, Response $response): Response
    {
        if (acl()->isUserLoggedIn()) {
            return redirect('admin.dashboard.index');
        }

        if (!$this->isSuperAdminExists()) {
            return redirect('admin.accounts.registration');
        }

        return twig()->render($response, 'plugins/accounts-admin/templates/login.html');
    }

    /**
     * Login page proccess
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function loginProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $data = $request->getParsedBody();

        // Process form
        $form = blueprints()->form($data)->process();

        if (entries()->has('accounts/' . $form->get('fields.id'))) {
            $userAcccount = entries()->fetch('accounts/' . $form->get('fields.id'));

            if (password_verify(trim($form->get('fields.password')), $userAcccount['hashed_password'])) {

                acl()->setUserLoggedInEmail($form->get('fields.id'));
                acl()->setUserLoggedInRoles($userAcccount['roles'] ?? '');
                acl()->setUserLoggedInUuid($userAcccount['uuid'] ?? '');
                acl()->setUserLoggedIn(true);


                // Run event onAccountsAdminUserLoggedIn
                emitter()->emit('onAccountsAdminUserLoggedIn');

                return redirect('admin.dashboard.index');
            }

            flash()->addMessage('error', __('accounts_admin_message_wrong_email_password'));

            return redirect('admin.accounts.login');
        }

        flash()->addMessage('error', __('accounts_admin_message_wrong_email_password'));

        return redirect('admin.accounts.login');

    }

    /**
     * Registration page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function registration(Request $request, Response $response) : Response
    {
        if (acl()->isUserLoggedIn()) {
            return redirect('admin.dashboard.index');
        }

        if ($this->isSuperAdminExists()) {
            return redirect('admin.accounts.login');
        };

        return twig()->render($response, 'plugins/accounts-admin/templates/registration.html');
    }

    /**
     * Reset passoword page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function resetPassword(Request $request, Response $response) : Response
    {
        return twig()->render($response, 'plugins/accounts-admin/templates/reset-password.html');
    }

    /**
     * No Access page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function noAccess(Request $request, Response $response) : Response
    {
        return twig()->render($response, 'plugins/accounts-admin/templates/no-access.html');
    }

    /**
     * New passoword process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function newPasswordProcess($id, $hash, Request $request, Response $response) : Response
    {
        if (entries()->has('accounts/' . $id)) {
            
            $userAccount = entries()->fetch('accounts/' . $id);

            if (is_null($userAccount['hashed_password_reset'])) {
                flash()->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));
                return redirect('admin.accounts.login');
            }

            if (password_verify(trim($hash), $userAccount['hashed_password_reset'])) {

                // Generate new passoword
                $rawPassword    = bin2hex(random_bytes(16));
                $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);

                $userAccount->delete('hashed_password_reset');
                $userAccount->set('hashed_password', $hashedPassword);

                if (entries()->update('accounts/' . $id, $userAccount->toArray())) {
                    try {

                        // Instantiation and passing `true` enables exceptions
                        $mail = new PHPMailer(true);

                        $newPasswordEmail = serializers()->frontmatter()->decode(filesystem()->file(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-password.md')->get());

                        // Recipients
                        $mail->setFrom(registry()->get('plugins.accounts-admin.settings.from.email'), registry()->get('plugins.accounts-admin.settings.from.name'));
                        $mail->addAddress($id, $id);

                        if (registry()->has('flextype.settings.url') && registry()->get('flextype.settings.url') !== '') {
                            $url = registry()->get('flextype.settings.url');
                        } else {
                            $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                        }

                        if (isset($userAccount['name'])) {
                            $user = $userAccount['name'];
                        } else {
                            $user = $id;
                        }

                        $tags = [
                            '{sitename}' => registry()->get('plugins.accounts-admin.settings.from.name'),
                            '{email}' => $id,
                            '{user}' => $user,
                            '{password}' => $rawPassword,
                            '{url}' => $url,
                        ];

                        $subject = parsers()->shortcodes()->parse($newPasswordEmail['subject']);
                        $content = parsers()->markdown()->parse(parsers()->shortcodes()->parse($newPasswordEmail['content']));

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = strtr($subject, $tags);
                        $mail->Body    = strtr($content, $tags);

                        // Send email
                        $mail->send();

                    } catch (\Exception $e) {

                    }

                    flash()->addMessage('success', __('accounts_admin_message_new_password_was_sended'));

                    // Run event onAccountsAdminNewPasswordReset
                    emitter()->emit('onAccountsAdminNewPasswordReset');

                    return redirect('admin.accounts.login');
                }

                return redirect('admin.accounts.login');
            }

            flash()->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));

            return redirect('admin.accounts.login');
        }

        flash()->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));

        return redirect('admin.accounts.login');
    }

    /**
     * Reset passoword process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function resetPasswordProcess(Request $request, Response $response) : Response
    {
        // Get data from POST
        $data = $request->getParsedBody();

        // Process form
        $form = blueprints()->form($data)->process();

        if (entries()->has($form->get('fields.id'))) {

            $rawHash = bin2hex(random_bytes(16));

            $userAccount = entries()->fetch($form->get('fields.id'));
            
            // Create account
            if (entries()->update($form->get('fields.id'), ['hashed_password_reset' => password_hash($rawHash, PASSWORD_BCRYPT)])) {
                try {

                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $reset_password_email = serializers()->frontmatter()->decode(filesystem()->file(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/reset-password.md')->get());

                    //Recipients
                    $mail->setFrom(registry()->get('plugins.accounts-admin.settings.from.email'), registry()->get('plugins.accounts-admin.settings.from.name'));
                    $mail->addAddress($form->get('fields.id'), $form->get('fields.id'));

                    $url = getBaseUrl();

                    if (isset($userAccount['name'])) {
                        $user = $userAccount['name'];
                    } else {
                        $user = $form->get('fields.id');
                    }

                    $tags = [
                        '{sitename}' => registry()->get('plugins.accounts-admin.settings.from.name'),
                        '{email}' => $form->get('fields.id'),
                        '{user}' => $user,
                        '{url}' => $url,
                        '{new_hash}' => $rawHash,
                    ];

                    $subject = parsers()->shortcodes()->parse($reset_password_email['subject']);
                    $content = parsers()->markdown()->parse(parsers()->shortcodes()->parse($reset_password_email['content']));

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                } catch (\Exception $e) {

                }

                flash()->addMessage('success', __('accounts_admin_message_reset_password_details_was_sended'));

                // Run event onAccountsAdminNewPasswordReset
                emitter()->emit('onAccountsAdminNewPasswordReset');
                
                return redirect('admin.accounts.login');
            }

            flash()->addMessage('error', __('accounts_admin_message_reset_password_details_was_not_sended'));

            return redirect('admin.accounts.login');
        }

        flash()->addMessage('error', __('accounts_admin_message_reset_password_details_was_not_sended'));

        return redirect('admin.accounts.login');
    }

    /**
     * Registration page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function registrationProcess(Request $request, Response $response) : Response
    {
        if ($this->isSuperAdminExists()) {
            return redirect('admin.accounts.login');
        }

        // Clean `var` directory before proccess
        if (filesystem()->directory(PATH['tmp'])->exists()) {
            filesystem()->directory(PATH['tmp'])->clean();
        }

        // Get data from POST
        $data = $request->getParsedBody();

        // Process form
        $form = blueprints()->form($data)->process();

        if (! entries()->has($form->get('fields.id'))) {
            
            $id = $form->get('fields.id');
            
            $form->set('fields.hashed_password', password_hash($form->get('fields.password'), PASSWORD_BCRYPT));
            $form->set('fields.roles', 'admin');
            $form->set('fields.state', 'enabled');
            $form->delete('fields.password');
            $form->delete('fields.id');

            // Create admin account
   
            if (entries()->create('accounts/' . $id, $form->get('fields'))) {
                try {

                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $newUserEmail = serializers()->frontmatter()->decode(filesystem()->file(PATH['project'] . '/plugins/accounts-admin/templates/emails/new-user.md')->get());

                    //Recipients
                    $mail->setFrom(registry()->get('plugins.accounts-admin.settings.from.email'), registry()->get('plugins.accounts-admin.settings.from.name'));
                    $mail->addAddress($id, $id);

                    if ($form->has('fields.name')) {
                        $user = $form->get('fields.name');
                    } else {
                        $user = $id;
                    }

                    $tags = [
                        '{sitename}' =>  registry()->get('plugins.accounts-admin.settings.from.name'),
                        '{user}'    => $user,
                    ];

                    $subject = parsers()->shortcodes()->parse($newUserEmail['subject']);
                    $content = parsers()->markdown()->parse(parsers()->shortcodes()->parse($newUserEmail['content']));

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                } catch (\Exception $e) {

                }

                // Update default entry
                entries()->update('home', ['created_by' => entries()->registry()->get('create.data.uuid'), 
                                                     'published_by' => entries()->registry()->get('create.data.uuid'),
                                                     'published_at' => entries()->registry()->get('create.data.registered_at'), 
                                                     'created_at' => entries()->registry()->get('create.data.registered_at')]);

                // Create default entries delivery token
                $apiEntriesToken = bin2hex(random_bytes(16));
                $apiEntriesTokenDirPath  = PATH['project'] . '/tokens/entries/' . $apiEntriesToken;
                $apiEntriesTokenFilePath = $apiEntriesTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiEntriesTokenDirPath)->exists()) filesystem()->directory($apiEntriesTokenDirPath)->create(0755, true);

                filesystem()->file($apiEntriesTokenFilePath)->put(
                    serializers()->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'newspaper',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => entries()->registry()->get('create.data.uuid'),
                        'created_by' => entries()->registry()->get('create.data.uuid'),
                        'created_at' => entries()->registry()->get('create.data.registered_at'),
                        'updated_by' => entries()->registry()->get('create.data.uuid'),
                        'updated_at' => entries()->registry()->get('create.data.registered_at'),
                    ])
                );

                // Create default images token
                $apiImagesToken = bin2hex(random_bytes(16));
                $apiImagesTokenDirPath  = PATH['project'] . '/tokens/images/' . $apiImagesToken;
                $apiImagesTokenFilePath = $apiImagesTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiImagesTokenDirPath)->exists()) filesystem()->directory($apiImagesTokenDirPath)->create(0755, true);

                filesystem()->file($apiImagesTokenFilePath)->put(
                    serializers()->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'images',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => entries()->registry()->get('create.data.uuid'),
                        'created_by' => entries()->registry()->get('create.data.uuid'),
                        'created_at' => entries()->registry()->get('create.data.registered_at'),
                        'updated_by' => entries()->registry()->get('create.data.uuid'),
                        'updated_at' => entries()->registry()->get('create.data.registered_at'),
                    ])
                );

                // Create default registry delivery token
                $apiRegistryToken = bin2hex(random_bytes(16));
                $apiRegistryTokenDirPath  = PATH['project'] . '/tokens/registry/' . $apiRegistryToken;
                $apiRegistryTokenFilePath = $apiRegistryTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiRegistryTokenDirPath)->exists()) filesystem()->directory($apiRegistryTokenDirPath)->create(0755, true);

                filesystem()->file($apiRegistryTokenFilePath)->put(
                    serializers()->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'archive',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => entries()->registry()->get('create.data.uuid'),
                        'created_by' => entries()->registry()->get('create.data.uuid'),
                        'created_at' => entries()->registry()->get('create.data.registered_at'),
                        'updated_by' => entries()->registry()->get('create.data.uuid'),
                        'updated_at' => entries()->registry()->get('create.data.registered_at'),
                    ])
                );

                // Create default media files delivery token
                $apiMediaToken = bin2hex(random_bytes(16));
                $apiMediaTokenDirPath  = PATH['project'] . '/tokens/media/' . $apiMediaToken;
                $apiMediaTokenFilePath = $apiMediaTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiMediaTokenDirPath)->exists()) filesystem()->directory($apiMediaTokenDirPath)->create(0755, true);

                filesystem()->file($apiMediaTokenFilePath)->put(
                    serializers()->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'archive',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => entries()->registry()->get('create.data.uuid'),
                        'created_by' => entries()->registry()->get('create.data.uuid'),
                        'created_at' => entries()->registry()->get('create.data.registered_at'),
                        'updated_by' => entries()->registry()->get('create.data.uuid'),
                        'updated_at' => entries()->registry()->get('create.data.registered_at'),
                    ])
                );

                // Set Default API's tokens
                $customFlextypeSettingsFilePath = PATH['project'] . '/config/flextype/settings.yaml';
                $customFlextypeSettingsFileData = serializers()->yaml()->decode(filesystem()->file($customFlextypeSettingsFilePath)->get());

                $customFlextypeSettingsFileData['api']['images']['default_token']   = $apiImagesToken;
                $customFlextypeSettingsFileData['api']['entries']['default_token']  = $apiEntriesToken;
                $customFlextypeSettingsFileData['api']['registry']['default_token'] = $apiRegistryToken;
                $customFlextypeSettingsFileData['api']['media']['default_token']    = $apiMediaToken;

                filesystem()->file($customFlextypeSettingsFilePath)->put(serializers()->yaml()->encode($customFlextypeSettingsFileData));

                // Set super admin regisered = true
                $accountsAdminConfig = serializers()->yaml()->decode(filesystem()->file(PATH['project'] . '/plugins/accounts-admin/settings.yaml')->get());
                $accountsAdminConfig['supper_admin_registered'] = true;
                filesystem()->file(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml')->put(serializers()->yaml()->encode($accountsAdminConfig));

                // Clean `var` directory before proccess
                if (filesystem()->directory(PATH['tmp'])->exists()) {
                    filesystem()->directory(PATH['tmp'])->clean();
                }

                return redirect('admin.accounts.login');
            }

            return redirect('admin.accounts.registration');
        }

        return redirect('admin.accounts.registration');
    }

    /**
     * Logout page process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function logoutProcess(Request $request, Response $response) : Response
    {
        session()->destroy();

        // Run event onAccountsAdminLogout
        emitter()->emit('onAccountsAdminLogout');

        return redirect('admin.accounts.login');
    }

    protected function isSuperAdminExists()
    {
        return registry()->get('plugins.accounts-admin.settings.supper_admin_registered');
    }
}
