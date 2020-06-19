<?php

declare(strict_types=1);

namespace Flextype;

use Flextype\Component\Arr\Arr;
use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Session\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use Ramsey\Uuid\Uuid;
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

/**
 * @property twig $twig
 * @property Fieldsets $fieldsets
 * @property Router $router
 * @property Slugify $slugify
 * @property Flash $flash
 */
class AccountsAdminController extends Container
{
    public function index($request, $response)
    {
        $accounts_list = Filesystem::listContents(PATH['project'] . '/accounts');
        $accounts      = [];

        foreach ($accounts_list as $account) {
            if ($account['type'] !== 'dir' || ! Filesystem::has($account['path'] . '/' . 'profile.yaml')) {
                continue;
            }

            $account = $this->serializer->decode(Filesystem::read($account['path'] . '/profile.yaml'), 'yaml');

            Arr::delete($account, 'hashed_password');
            Arr::delete($account, 'hashed_password_reset');

            $accounts[] = $account;
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

    public function add($request, $response)
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

    public function addProcess($request, $response)
    {
        // Get Data from POST
        $post_data = $request->getParsedBody();

        $username = $this->slugify->slugify($post_data['username']);

        if (! Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $username . '/profile.yaml')) {
            // Generate UUID
            $uuid = Uuid::uuid4()->toString();

            // Get time
            $time = date($this->registry->get('flextype.settings.date_format'), time());

            // Get hashed password
            $hashed_password = password_hash($post_data['password'], PASSWORD_BCRYPT);

            $post_data['username']        = $username;
            $post_data['registered_at']   = $time;
            $post_data['uuid']            = $uuid;
            $post_data['hashed_password'] = $hashed_password;
            $post_data['roles']           = $post_data['roles'];
            $post_data['state']           = 'enabled';

            Arr::delete($post_data, 'csrf_name');
            Arr::delete($post_data, 'csrf_value');
            Arr::delete($post_data, 'password');
            Arr::delete($post_data, 'form-save-action');

            // Create accounts directory and account
            Filesystem::createDir(PATH['project'] . '/accounts/' . $username);

            // Create admin account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $username . '/profile.yaml',
                $this->serializer->encode(
                    $post_data,
                    'yaml'
                )
            )) {
                return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
    }

    public function edit($request, $response)
    {
        // Get Query Params
        $query = $request->getQueryParams();

        $profile = $this->serializer->decode(Filesystem::read(PATH['project'] . '/accounts/' . $query['id'] . '/profile.yaml'), 'yaml');

        Arr::delete($profile, 'hashed_password');
        Arr::delete($profile, 'hashed_password_reset');

        return $this->twig->render(
            $response,
            'plugins/accounts-admin/templates/edit.html',
            [
                'menu_item' => 'accounts',
                'profile' => $profile,
                'id' => $query['id'],
                'links' =>  [
                    'accounts' => [
                        'link' => $this->router->pathFor('admin.accounts.index'),
                        'title' => __('accounts_admin_accounts'),
                    ],
                    'accounts_edit' => [
                        'link' => $this->router->pathFor('admin.accounts.edit') . '?id=' . $query['id'],
                        'title' => __('accounts_admin_edit'),
                        'active' => true,
                    ],
                ],
                'buttons' => [
                    'save_entry' => [
                        'type' => 'action',
                        'link' => 'javascript:;',
                        'title' => __('accounts_admin_save'),
                    ],
                ],
            ]
        );
    }

    public function editProcess($request, $response)
    {
        // Get Query Params
        $query = $request->getQueryParams();

        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get username
        $username = $query['id'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $username . '/profile.yaml')) {
            Arr::delete($post_data, 'csrf_name');
            Arr::delete($post_data, 'csrf_value');
            Arr::delete($post_data, 'form-save-action');
            Arr::delete($post_data, 'password');
            Arr::delete($post_data, 'username');

            if (! empty($post_data['new_password'])) {
                $post_data['hashed_password'] = password_hash($post_data['new_password'], PASSWORD_BCRYPT);
                Arr::delete($post_data, 'new_password');
            } else {
                Arr::delete($post_data, 'password');
                Arr::delete($post_data, 'new_password');
            }

            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = $this->serializer->decode($user_file_body, 'yaml');

            // Create admin account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $username . '/profile.yaml',
                $this->serializer->encode(
                    array_merge($user_file_data, $post_data),
                    'yaml'
                )
            )) {
                return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.index'));
    }

    public function deleteProcess($request, $response)
    {
        $username = $request->getParsedBody()['account-id'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $username . '/profile.yaml')) {
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

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $post_data['username'] . '/profile.yaml')) {
            $user_file = $this->serializer->decode(Filesystem::read($_user_file), 'yaml', false);

            if (password_verify(trim($post_data['password']), $user_file['hashed_password'])) {
                Session::set('account_username', $user_file['username']);
                Session::set('account_roles', $user_file['roles']);
                Session::set('account_uuid', $user_file['uuid']);
                Session::set('account_is_user_logged_in', true);

                return $response->withRedirect($this->router->pathFor('accounts.profile', ['username' => $user_file['username']]));
            }

            $this->flash->addMessage('error', __('admin_message_wrong_username_password'));

            return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
        }

        $this->flash->addMessage('error', __('admin_message_wrong_username_password'));

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
        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $args['username'] . '/profile.yaml')) {
            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = $this->serializer->decode($user_file_body, 'yaml');

            if (password_verify(trim($args['hash']), $user_file_data['hashed_password_reset'])) {
                // Generate new passoword
                $raw_password    = bin2hex(random_bytes(16));
                $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

                $user_file_data['hashed_password'] = $hashed_password;

                Arr::delete($user_file_data, 'hashed_password_reset');

                if (Filesystem::write(
                    PATH['project'] . '/accounts/' . $username . '/profile.yaml',
                    $this->serializer->encode(
                        $user_file_data,
                        'yaml'
                    )
                )) {
                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $new_password_email = $this->serializer->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/emails/new-password.md'), 'frontmatter');

                    //Recipients
                    $mail->setFrom($new_password_email['from'], 'Mailer');
                    $mail->addAddress($user_file_data['email'], $username);

                    if ($this->registry->has('flextype.settings.url') && $this->registry->get('flextype.settings.url') !== '') {
                        $url = $this->registry->get('flextype.settings.url');
                    } else {
                        $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                    }

                    $tags = [
                        '[sitename]' => $this->registry->get('plugins.site.settings.title'),
                        '[username]' => $username,
                        '[password]' => $raw_password,
                        '[url]' => $url,
                    ];

                    $subject = $this->parser->parse($new_password_email['subject'], 'shortcodes');
                    $content = $this->parser->parse($this->parser->parse($new_password_email['content'], 'shortcodes'), 'markdown');

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                    return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
                }

                return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
            }

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

        // Get username
        $username = $post_data['username'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $username . '/profile.yaml')) {
            Arr::delete($post_data, 'csrf_name');
            Arr::delete($post_data, 'csrf_value');
            Arr::delete($post_data, 'form-save-action');
            Arr::delete($post_data, 'username');

            $post_data['hashed_password_reset'] = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = $this->serializer->decode($user_file_body, 'yaml');

            // Create account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $username . '/profile.yaml',
                $this->serializer->encode(
                    array_merge($user_file_data, $post_data),
                    'yaml'
                )
            )) {
                // Instantiation and passing `true` enables exceptions
                $mail = new PHPMailer(true);

                $reset_password_email = $this->serializer->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/reset-password.md'), 'frontmatter');

                //Recipients
                $mail->setFrom($reset_password_email['from'], 'Mailer');
                $mail->addAddress($user_file_data['email'], $username);

                if ($this->registry->has('flextype.settings.url') && $this->registry->get('flextype.settings.url') !== '') {
                    $url = $this->registry->get('flextype.settings.url');
                } else {
                    $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                }

                $tags = [
                    '[sitename]' => $this->registry->get('plugins.site.settings.title'),
                    '[username]' => $username,
                    '[url]' => $url,
                ];

                $subject = $this->parser->parse($reset_password_email['subject'], 'shortcodes');
                $content = $this->parser->parse($this->parser->parse($reset_password_email['content'], 'shortcodes'), 'markdown');

                // Content
                $mail->isHTML(true);
                $mail->Subject = strtr($subject, $tags);
                $mail->Body    = strtr($content, $tags);

                // Send email
                $mail->send();

                return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
            }

            return $response->withRedirect($this->router->pathFor('admin.accounts.registration'));
        }

        return $response->withRedirect($this->router->pathFor('admin.accounts.registration'));
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
        // Get Data from POST
        $post_data = $request->getParsedBody();

        $username = $this->slugify->slugify($post_data['username']);

        if (! Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $username . '/profile.yaml')) {
            // Generate UUID
            $uuid = Uuid::uuid4()->toString();

            // Get time
            $time = date($this->registry->get('flextype.settings.date_format'), time());

            // Get username
            $username = $this->slugify->slugify($post_data['username']);

            // Get hashed password
            $hashed_password = password_hash($post_data['password'], PASSWORD_BCRYPT);

            $post_data['username']        = $username;
            $post_data['registered_at']   = $time;
            $post_data['uuid']            = $uuid;
            $post_data['hashed_password'] = $hashed_password;
            $post_data['roles']           = 'admin';

            Arr::delete($post_data, 'csrf_name');
            Arr::delete($post_data, 'csrf_value');
            Arr::delete($post_data, 'password');
            Arr::delete($post_data, 'form-save-action');

            // Create accounts directory and account
            Filesystem::createDir(PATH['project'] . '/accounts/' . $this->slugify->slugify($post_data['username']));

            // Create admin account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $username . '/profile.yaml',
                $this->serializer->encode(
                    $post_data,
                    'yaml'
                )
            )) {
                // Instantiation and passing `true` enables exceptions
                $mail = new PHPMailer(true);

                $new_user_email = $this->serializer->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-user.md'), 'frontmatter');

                //Recipients
                $mail->setFrom($new_user_email['from'], 'Mailer');
                $mail->addAddress($post_data['email'], $username);

                $tags = [
                    '[sitename]' => $this->registry->get('plugins.site.settings.title'),
                    '[username]' => $this->acl->getUserLoggedInUsername(),
                ];

                $subject = $this->parser->parse($new_user_email['subject'], 'shortcodes');
                $content = $this->parser->parse($this->parser->parse($new_user_email['content'], 'shortcodes'), 'markdown');

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
                $api_delivery_entries_token_dir_path  = PATH['project'] . '/tokens' . '/delivery/entries/' . $api_delivery_entries_token;
                $api_delivery_entries_token_file_path = $api_delivery_entries_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_entries_token_dir_path)) Filesystem::createDir($api_delivery_entries_token_dir_path);

                Filesystem::write(
                    $api_delivery_entries_token_file_path,
                    $this->serializer->encode([
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
                    ], 'yaml')
                );

                // Create default images token
                $api_images_token = bin2hex(random_bytes(16));
                $api_images_token_dir_path  = PATH['project'] . '/tokens' . '/images/' . $api_images_token;
                $api_images_token_file_path = $api_images_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_images_token_dir_path)) Filesystem::createDir($api_images_token_dir_path);

                Filesystem::write(
                    $api_images_token_file_path,
                    $this->serializer->encode([
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
                    ], 'yaml')
                );

                // Create default registry delivery token
                $api_delivery_registry_token = bin2hex(random_bytes(16));
                $api_delivery_registry_token_dir_path  = PATH['project'] . '/tokens' . '/delivery/registry/' . $api_delivery_registry_token;
                $api_delivery_registry_token_file_path = $api_delivery_registry_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_registry_token_dir_path)) Filesystem::createDir($api_delivery_registry_token_dir_path);

                Filesystem::write(
                    $api_delivery_registry_token_file_path,
                    $this->serializer->encode([
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
                    ], 'yaml')
                );

                // Set Default API's tokens
                $custom_flextype_settings_file_path = PATH['project'] . '/config/' . '/settings.yaml';
                $custom_flextype_settings_file_data = $this->serializer->decode(Filesystem::read($custom_flextype_settings_file_path), 'yaml');

                $custom_flextype_settings_file_data['api']['images']['default_token']               = $api_images_token;
                $custom_flextype_settings_file_data['api']['delivery']['entries']['default_token']  = $api_delivery_entries_token;
                $custom_flextype_settings_file_data['api']['delivery']['registry']['default_token'] = $api_delivery_registry_token;

                Filesystem::write($custom_flextype_settings_file_path, $this->serializer->encode($custom_flextype_settings_file_data, 'yaml'));

                // Create uploads dir for default entries
                if (! Filesystem::has(PATH['project'] . '/uploads/entries/home/')) {
                    Filesystem::createDir(PATH['project'] . '/uploads/entries/home/');
                }

                // Set super admin regisered = true
                $accounts_admin_config = $this->serializer->decode(Filesystem::read(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml'), 'yaml');
                $accounts_admin_config['supper_admin_registered'] = true;
                Filesystem::write(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml', $this->serializer->encode($accounts_admin_config, 'yaml'));

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

        return $response->withRedirect($this->router->pathFor('admin.accounts.login'));
    }
}
