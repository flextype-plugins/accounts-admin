<?php

declare(strict_types=1);

/**
 * @link https://flextype.org
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flextype\Plugin\AccountsAdmin\Controllers;

use Flextype\Component\Arrays\Arrays;
use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Session\Session;
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
use Flextype\App\Foundation\Container;

/**
 * @property twig $twig
 * @property Fieldsets $fieldsets
 * @property Router $router
 * @property Flash $flash
 */
class AccountsAdminController extends Container
{
    /**
     * Index page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function index(Request $request, Response $response, array $args) : Response
    {
        $accounts_list = Filesystem::listContents(PATH['project'] . '/accounts');
        $accounts      = [];

        foreach ($accounts_list as $account) {
            if ($account['type'] !== 'dir' || ! Filesystem::has($account['path'] . '/' . 'profile.yaml')) {
                continue;
            }

            $account_to_store = $this->yaml->decode(Filesystem::read($account['path'] . '/profile.yaml'));

            $_path = explode('/', $account['path']);
            $account_to_store['email'] = array_pop($_path);

            Arrays::delete($account, 'hashed_password');
            Arrays::delete($account, 'hashed_password_reset');


            $accounts[] = $account_to_store;
        }

        return $this->twig->render($response, 'plugins/accounts-admin/templates/index.html', [
            'accounts_list' => $accounts,
            'menu_item' => 'accounts-admin',
            'links' =>  [
                'accounts' => [
                    'link' => $this->router->pathFor('admin.accounts.index'),
                    'title' => __('accounts_admin_accounts'),
                    'active' => true,
                ],
            ],
            'buttons' => [
                'accounts_add' => [
                    'link' => $this->router->pathFor('admin.accounts.add'),
                    'title' => __('accounts_admin_create_new_user'),
                ],
            ],
        ]);
    }

    /**
     * Add page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function add(Request $request, Response $response, array $args) : Response
    {
        return $this->twig->render(
            $response,
            'plugins/accounts-admin/templates/add.html',
            [
                'menu_item' => 'accounts-admin',
                'links' =>  [
                    'accounts' => [
                        'link' => $this->router->pathFor('admin.accounts.index'),
                        'title' => __('accounts_admin_accounts'),
                    ],
                    'accounts_add' => [
                        'link' => $this->router->pathFor('admin.accounts.add'),
                        'title' => __('accounts_admin_create_new_user'),
                        'active' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Add proccess page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function addProcess(Request $request, Response $response, array $args) : Response
    {
        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get user email
        $email = $post_data['email'];

        if (! Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {

            // Generate UUID
            $uuid = Uuid::uuid4()->toString();

            // Get time
            $time = date($this->registry->get('flextype.settings.date_format'), time());

            // Get hashed password
            $hashed_password = password_hash($post_data['password'], PASSWORD_BCRYPT);

            $post_data['email']           = $email;
            $post_data['registered_at']   = $time;
            $post_data['uuid']            = $uuid;
            $post_data['hashed_password'] = $hashed_password;
            $post_data['roles']           = $post_data['roles'];
            $post_data['state']           = $post_data['state'];

            Arrays::delete($post_data, 'csrf_name');
            Arrays::delete($post_data, 'csrf_value');
            Arrays::delete($post_data, 'password');
            Arrays::delete($post_data, 'form-save-action');

            // Create directory for account
            Filesystem::createDir(PATH['project'] . '/accounts/' . $email);

            // Create account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                $this->yaml->encode(
                    $post_data
                )
            )) {
                return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
    }

    /**
     * Edit page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function edit(Request $request, Response $response, array $args) : Response
    {
        // Get Query Params
        $query = $request->getQueryParams();

        // Get Profile ID
        $email = $query['email'];

        // Get Profile
        $profile = $this->yaml->decode(Filesystem::read(PATH['project'] . '/accounts/' . $email . '/profile.yaml'));

        Arrays::delete($profile, 'hashed_password');
        Arrays::delete($profile, 'hashed_password_reset');

        return $this->twig->render(
            $response,
            'plugins/accounts-admin/templates/edit.html',
            [
                'menu_item' => 'accounts',
                'profile' => $profile,
                'email' => $email,
                'links' =>  [
                    'accounts' => [
                        'link' => $this->router->pathFor('admin.accounts.index'),
                        'title' => __('accounts_admin_accounts'),
                    ],
                    'accounts_edit' => [
                        'link' => $this->router->pathFor('admin.accounts.edit') . '?email=' . $query['email'],
                        'title' => __('accounts_admin_edit'),
                        'active' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Edit proccess page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function editProcess(Request $request, Response $response, array $args) : Response
    {
        // Get Query Params
        $query = $request->getQueryParams();

        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get email
        $email = $query['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            Arrays::delete($post_data, 'csrf_name');
            Arrays::delete($post_data, 'csrf_value');
            Arrays::delete($post_data, 'form-save-action');
            Arrays::delete($post_data, 'password');
            Arrays::delete($post_data, 'email');

            if (! empty($post_data['new_password'])) {
                $post_data['hashed_password'] = password_hash($post_data['new_password'], PASSWORD_BCRYPT);
                Arrays::delete($post_data, 'new_password');
            } else {
                Arrays::delete($post_data, 'password');
                Arrays::delete($post_data, 'new_password');
            }

            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = $this->yaml->decode($user_file_body);

            // Create admin account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                $this->yaml->encode(
                    array_merge($user_file_data, $post_data)
                )
            )) {
                return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
    }

    /**
     * Delete proccess page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function deleteProcess(Request $request, Response $response, array $args) : Response
    {
        // Get email
        $email = $request->getParsedBody()['account-email'];

        // Delete...
        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            if (Filesystem::delete($_user_file)) {
                $this->flash->addMessage('success', __('accounts_admin_message_account_deleted'));
            }
            $this->flash->addMessage('error', __('accounts_admin_message_account_was_not_deleted'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
    }

    /**
     * Login page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function login(Request $request, Response $response, array $args) : Response
    {
        if ($this->acl->isUserLoggedIn()) {
            return $response->withRedirect($this->router->pathFor('admin.dashboard.index'));
        }

        if (!$this->isSuperAdminExists()) {
            return $response->withRedirect($this->router->pathFor('admin.accounts.registration'));
        }


        return $this->twig->render($response, 'plugins/accounts-admin/templates/login.html');
    }

    /**
     * Login page proccess
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function loginProcess(Request $request, Response $response, array $args) : Response
    {
        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get email
        $email = $post_data['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            $user_file = $this->yaml->decode(Filesystem::read($_user_file), false);

            if (password_verify(trim($post_data['password']), $user_file['hashed_password'])) {

                $this->acl->setUserLoggedInEmail($email);
                $this->acl->setUserLoggedInRoles($user_file['roles']);
                $this->acl->setUserLoggedInUuid($user_file['uuid']);
                $this->acl->setUserLoggedIn(true);

                // Run event onAccountsAdminUserLoggedIn
                $this->emitter->emit('onAccountsAdminUserLoggedIn');

                return $response->withRedirect($this->router->pathFor('admin.dashboard.index'));
            }

            $this->flash->addMessage('error', __('accounts_admin_message_wrong_email_password'));

            return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
        }

        $this->flash->addMessage('error', __('accounts_admin_message_wrong_email_password'));

        return $response->withRedirect($this->router->pathFor('admin.accounts.login'));

    }

    /**
     * Registration page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function registration(Request $request, Response $response, array $args) : Response
    {
        if ($this->acl->isUserLoggedIn()) {
            return $response->withRedirect($this->router->pathFor('admin.dashboard.index'));
        }

        if ($this->isSuperAdminExists()) {
            return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
        }

        return $this->twig->render($response, 'plugins/accounts-admin/templates/registration.html');
    }

    /**
     * Reset passoword page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function resetPassword(Request $request, Response $response, array $args) : Response
    {
        return $this->twig->render($response, 'plugins/accounts-admin/templates/reset-password.html');
    }

    /**
     * New passoword process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function newPasswordProcess(Request $request, Response $response, array $args) : Response
    {
        // Get email
        $email = $args['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = $this->yaml->decode($user_file_body);

            if (is_null($user_file_data['hashed_password_reset'])) {
                $this->flash->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));
                return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
            }

            if (password_verify(trim($args['hash']), $user_file_data['hashed_password_reset'])) {

                // Generate new passoword
                $raw_password    = bin2hex(random_bytes(16));
                $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

                $user_file_data['hashed_password'] = $hashed_password;

                Arrays::delete($user_file_data, 'hashed_password_reset');

                if (Filesystem::write(
                    PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                    $this->yaml->encode($user_file_data)
                )) {
                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $new_password_email = $this->frontmatter->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-password.md'));

                    //Recipients
                    $mail->setFrom($this->registry->get('plugins.accounts-admin.settings.from.email'), $this->registry->get('plugins.accounts-admin.settings.from.name'));
                    $mail->addAddress($email, $email);

                    if ($this->registry->has('flextype.settings.url') && $this->registry->get('flextype.settings.url') !== '') {
                        $url = $this->registry->get('flextype.settings.url');
                    } else {
                        $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                    }

                    if (isset($user_file_data['full_name'])) {
                        $user = $user_file_data['full_name'];
                    } else {
                        $user = $email;
                    }

                    $tags = [
                        '{sitename}' => $this->registry->get('plugins.accounts-admin.settings.from.name'),
                        '{email}' => $email,
                        '{user}' => $user,
                        '{password}' => $raw_password,
                        '{url}' => $url,
                    ];

                    $subject = $this->shortcode->parse($new_password_email['subject']);
                    $content = $this->markdown->parse($this->shortcode->parse($new_password_email['content']));

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                    $this->flash->addMessage('success', __('accounts_admin_message_new_password_was_sended'));

                    // Run event onAccountsAdminNewPasswordReset
                    $this->emitter->emit('onAccountsAdminNewPasswordReset');

                    return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
                }

                return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
            }

            $this->flash->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));

            return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.login'));

    }

    /**
     * Reset passoword process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function resetPasswordProcess(Request $request, Response $response, array $args) : Response
    {
        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get email
        $email = $post_data['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            Arrays::delete($post_data, 'csrf_name');
            Arrays::delete($post_data, 'csrf_value');
            Arrays::delete($post_data, 'form-save-action');
            Arrays::delete($post_data, 'email');

            $raw_hash                           = bin2hex(random_bytes(16));
            $post_data['hashed_password_reset'] = password_hash($raw_hash, PASSWORD_BCRYPT);

            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = $this->yaml->decode($user_file_body);

            // Create account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                $this->yaml->encode(
                    array_merge($user_file_data, $post_data)
                )
            )) {
                // Instantiation and passing `true` enables exceptions
                $mail = new PHPMailer(true);

                $reset_password_email = $this->frontmatter->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/reset-password.md'));

                //Recipients
                $mail->setFrom($this->registry->get('plugins.accounts-admin.settings.from.email'), $this->registry->get('plugins.accounts-admin.settings.from.name'));
                $mail->addAddress($email, $email);

                if ($this->registry->has('flextype.settings.url') && $this->registry->get('flextype.settings.url') !== '') {
                    $url = $this->registry->get('flextype.settings.url');
                } else {
                    $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                }

                if (isset($user_file_data['full_name'])) {
                    $user = $user_file_data['full_name'];
                } else {
                    $user = $email;
                }

                $tags = [
                    '{sitename}' => $this->registry->get('plugins.accounts-admin.settings.from.name'),
                    '{email}' => $email,
                    '{user}' => $user,
                    '{url}' => $url,
                    '{new_hash}' => $raw_hash,
                ];

                $subject = $this->shortcode->parse($reset_password_email['subject']);
                $content = $this->markdown->parse($this->shortcode->parse($reset_password_email['content']));

                // Content
                $mail->isHTML(true);
                $mail->Subject = strtr($subject, $tags);
                $mail->Body    = strtr($content, $tags);

                // Send email
                $mail->send();

                // Run event onAccountsAdminNewPasswordReset
                $this->emitter->emit('onAccountsAdminNewPasswordReset');

                return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
    }

    /**
     * Registration page
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param array    $args     Args
     */
    public function registrationProcess(Request $request, Response $response, array $args) : Response
    {
        if ($this->isSuperAdminExists()) {
            return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
        }

        // Clear cache before proccess
        $this->cache->clearAll();

        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get email
        $email = $post_data['email'];

        if (! Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            // Generate UUID
            $uuid = Uuid::uuid4()->toString();

            // Get time
            $time = date($this->registry->get('flextype.settings.date_format'), time());

            // Get hashed password
            $hashed_password = password_hash($post_data['password'], PASSWORD_BCRYPT);

            $post_data['email']           = $email;
            $post_data['registered_at']   = $time;
            $post_data['uuid']            = $uuid;
            $post_data['hashed_password'] = $hashed_password;
            $post_data['roles']           = 'admin';
            $post_data['state']           = 'enabled';

            Arrays::delete($post_data, 'csrf_name');
            Arrays::delete($post_data, 'csrf_value');
            Arrays::delete($post_data, 'password');
            Arrays::delete($post_data, 'form-save-action');

            // Create accounts directory and account
            Filesystem::createDir(PATH['project'] . '/accounts/' . $post_data['email']);

            // Create admin account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                $this->yaml->encode(
                    $post_data
                )
            )) {
                // Instantiation and passing `true` enables exceptions
                $mail = new PHPMailer(true);

                $new_user_email = $this->frontmatter->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-user.md'));

                //Recipients
                $mail->setFrom($this->registry->get('plugins.accounts-admin.settings.from.email'), $this->registry->get('plugins.accounts-admin.settings.from.name'));
                $mail->addAddress($email, $email);

                if (isset($post_data['full_name'])) {
                    $user = $post_data['full_name'];
                } else {
                    $user = $email;
                }

                $tags = [
                    '{sitename}' =>  $this->registry->get('plugins.accounts-admin.settings.from.name'),
                    '{user}'    => $user,
                ];

                $subject = $this->shortcode->parse($new_user_email['subject']);
                $content = $this->markdown->parse($this->shortcode->parse($new_user_email['content']));

                // Content
                $mail->isHTML(true);
                $mail->Subject = strtr($subject, $tags);
                $mail->Body    = strtr($content, $tags);

                // Send email
                $mail->send();

                // Update default entry
                $this->entries->update('home', ['created_by' => $uuid, 'published_by' => $uuid, 'published_at' => $time, 'created_at' => $time]);

                // Create default entries delivery token
                $api_delivery_entries_token = bin2hex(random_bytes(16));
                $api_delivery_entries_token_dir_path  = PATH['project'] . '/tokens/entries/' . $api_delivery_entries_token;
                $api_delivery_entries_token_file_path = $api_delivery_entries_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_entries_token_dir_path)) Filesystem::createDir($api_delivery_entries_token_dir_path);

                Filesystem::write(
                    $api_delivery_entries_token_file_path,
                    $this->yaml->encode([
                        'title' => 'Default',
                        'icon' => 'fas fa-database',
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => $uuid,
                        'created_by' => $uuid,
                        'created_at' => $time,
                        'updated_by' => $uuid,
                        'updated_at' => $time,
                    ])
                );

                // Create default images token
                $api_images_token = bin2hex(random_bytes(16));
                $api_images_token_dir_path  = PATH['project'] . '/tokens/images/' . $api_images_token;
                $api_images_token_file_path = $api_images_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_images_token_dir_path)) Filesystem::createDir($api_images_token_dir_path);

                Filesystem::write(
                    $api_images_token_file_path,
                    $this->yaml->encode([
                        'title' => 'Default',
                        'icon' => 'far fa-images',
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => $uuid,
                        'created_by' => $uuid,
                        'created_at' => $time,
                        'updated_by' => $uuid,
                        'updated_at' => $time,
                    ])
                );

                // Create default registry delivery token
                $api_delivery_registry_token = bin2hex(random_bytes(16));
                $api_delivery_registry_token_dir_path  = PATH['project'] . '/tokens/registry/' . $api_delivery_registry_token;
                $api_delivery_registry_token_file_path = $api_delivery_registry_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_registry_token_dir_path)) Filesystem::createDir($api_delivery_registry_token_dir_path);

                Filesystem::write(
                    $api_delivery_registry_token_file_path,
                    $this->yaml->encode([
                        'title' => 'Default',
                        'icon' => 'fas fa-archive',
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => $uuid,
                        'created_by' => $uuid,
                        'created_at' => $time,
                        'updated_by' => $uuid,
                        'updated_at' => $time,
                    ])
                );

                // Set Default API's tokens
                $custom_flextype_settings_file_path = PATH['project'] . '/config/flextype/settings.yaml';
                $custom_flextype_settings_file_data = $this->yaml->decode(Filesystem::read($custom_flextype_settings_file_path));

                $custom_flextype_settings_file_data['api']['images']['default_token']   = $api_images_token;
                $custom_flextype_settings_file_data['api']['entries']['default_token']  = $api_delivery_entries_token;
                $custom_flextype_settings_file_data['api']['registry']['default_token'] = $api_delivery_registry_token;

                Filesystem::write($custom_flextype_settings_file_path, $this->yaml->encode($custom_flextype_settings_file_data));

                // Create uploads dir for default entries
                if (! Filesystem::has(PATH['project'] . '/uploads/entries/home/')) {
                    Filesystem::createDir(PATH['project'] . '/uploads/entries/home/');
                }

                // Set super admin regisered = true
                $accounts_admin_config = $this->yaml->decode(Filesystem::read(PATH['project'] . '/plugins/accounts-admin/settings.yaml'));
                $accounts_admin_config['supper_admin_registered'] = true;
                Filesystem::write(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml', $this->yaml->encode($accounts_admin_config));

                // Clear cache after proccess
                $this->cache->clearAll();

                return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.registration'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.registration'));
    }

    /**
     * Logout page process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function logoutProcess(Request $request, Response $response) : Response
    {
        Session::destroy();

        // Run event onAccountsAdminLogout
        $this->emitter->emit('onAccountsAdminLogout');

        return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
    }

    protected function isSuperAdminExists()
    {
        return $this->registry->get('plugins.accounts-admin.settings.supper_admin_registered');
    }
}
