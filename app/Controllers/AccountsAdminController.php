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

/**
 * @property twig $twig
 * @property Fieldsets $fieldsets
 * @property Router $router
 * @property Flash $flash
 */
class AccountsAdminController
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
        return flextype('twig')->render($response, 'plugins/accounts-admin/templates/index.html', [
            'accountsList' => flextype('accounts')->fetch('', ['collection' => true, 'depth' => ['1']])->toArray(),
            'menu_item' => 'accounts-admin',
            'links' =>  [
                'accounts' => [
                    'link' => flextype('router')->pathFor('admin.accounts.index'),
                    'title' => __('accounts_admin_accounts'),
                ],
            ]
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
        return flextype('twig')->render(
            $response,
            'plugins/accounts-admin/templates/add.html',
            [
                'menu_item' => 'accounts-admin',
                'links' =>  [
                    'accounts' => [
                        'link' => flextype('router')->pathFor('admin.accounts.index'),
                        'title' => __('accounts_admin_accounts'),
                    ],
                    'accounts_add' => [
                        'link' => flextype('router')->pathFor('admin.accounts.add'),
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
        // Get data from POST
        $data = $request->getParsedBody();

        // Process form
        $form = flextype('blueprints')->form($data)->process();

        if (! flextype('accounts')->has($form->get('fields.id'))) {

            $id = $form->get('fields.id');

            $form->set('fields.hashed_password', password_hash($form->get('fields.password'), PASSWORD_BCRYPT));
            $form->delete('fields.password');
            $form->delete('fields.id');

            if (flextype('accounts')->create($id, $form->get('fields'))) {
                return $response->withRedirect(flextype('router')->pathFor('admin.accounts.index'));
            }

            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.index'));
        }

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.index'));
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

        return flextype('twig')->render(
            $response,
            'plugins/accounts-admin/templates/edit.html',
            [
                'menu_item' => 'accounts',
                'account' => flextype('accounts')->fetch($query['id'])->toArray(),
                'query' => $query,
                'links' =>  [
                    'accounts' => [
                        'link' => flextype('router')->pathFor('admin.accounts.index'),
                        'title' => __('accounts_admin_accounts'),
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
        // Get data from POST
        $data = $request->getParsedBody();

        // Process form
        $form = flextype('blueprints')->form($data)->process();

        if ($form->get('fields.new_password') != null) {
            $form->set('fields.hashed_password', password_hash($form->get('fields.new_password'), PASSWORD_BCRYPT));
            $form->delete('fields.new_password');
        }

        if (flextype('accounts')->update($form->get('fields.id'), $form->copy()->delete('fields.id')->get('fields'))) {
            flextype('flash')->addMessage('success', $form->get('messages.success'));
        } else {
            flextype('flash')->addMessage('error', $form->get('messages.error'));
        }

        return $response->withRedirect($form->get('redirect'));
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
                flextype('flash')->addMessage('success', __('accounts_admin_message_account_deleted'));
            }
            flextype('flash')->addMessage('error', __('accounts_admin_message_account_was_not_deleted'));
        }

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.index'));
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

        if (flextype('acl')->isUserLoggedIn()) {
            return $response->withRedirect(flextype('router')->pathFor('admin.dashboard.index'));
        }

        if (!$this->isSuperAdminExists()) {
            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.registration'));
        }


        return flextype('twig')->render($response, 'plugins/accounts-admin/templates/login.html');
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
            $user_file = flextype('serializers')->yaml()->decode(Filesystem::read($_user_file), false);

            if (password_verify(trim($post_data['password']), $user_file['hashed_password'])) {

                flextype('acl')->setUserLoggedInEmail($email);
                flextype('acl')->setUserLoggedInRoles($user_file['roles']);
                flextype('acl')->setUserLoggedInUuid($user_file['uuid']);
                flextype('acl')->setUserLoggedIn(true);


                // Run event onAccountsAdminUserLoggedIn
                flextype('emitter')->emit('onAccountsAdminUserLoggedIn');

                return $response->withRedirect(flextype('router')->pathFor('admin.dashboard.index'));
            }

            flextype('flash')->addMessage('error', __('accounts_admin_message_wrong_email_password'));

            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
        }

        flextype('flash')->addMessage('error', __('accounts_admin_message_wrong_email_password'));

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));

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
        if (flextype('acl')->isUserLoggedIn()) {
            return $response->withRedirect(flextype('router')->pathFor('admin.dashboard.index'));
        }

        if ($this->isSuperAdminExists()) {
            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
        }

        return flextype('twig')->render($response, 'plugins/accounts-admin/templates/registration.html');
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
        return flextype('twig')->render($response, 'plugins/accounts-admin/templates/reset-password.html');
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
            $user_file_data = flextype('serializers')->yaml()->decode($user_file_body);

            if (is_null($user_file_data['hashed_password_reset'])) {
                flextype('flash')->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));
                return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
            }

            if (password_verify(trim($args['hash']), $user_file_data['hashed_password_reset'])) {

                // Generate new passoword
                $raw_password    = bin2hex(random_bytes(16));
                $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

                $user_file_data['hashed_password'] = $hashed_password;

                Arrays::delete($user_file_data, 'hashed_password_reset');

                if (Filesystem::write(
                    PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                    flextype('serializers')->yaml()->encode($user_file_data)
                )) {

                    try {

                        // Instantiation and passing `true` enables exceptions
                        $mail = new PHPMailer(true);

                        $new_password_email = flextype('serializers')->frontmatter()->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-password.md'));

                        //Recipients
                        $mail->setFrom(flextype('registry')->get('plugins.accounts-admin.settings.from.email'), flextype('registry')->get('plugins.accounts-admin.settings.from.name'));
                        $mail->addAddress($email, $email);

                        if (flextype('registry')->has('flextype.settings.url') && flextype('registry')->get('flextype.settings.url') !== '') {
                            $url = flextype('registry')->get('flextype.settings.url');
                        } else {
                            $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                        }

                        if (isset($user_file_data['full_name'])) {
                            $user = $user_file_data['full_name'];
                        } else {
                            $user = $email;
                        }

                        $tags = [
                            '{sitename}' => flextype('registry')->get('plugins.accounts-admin.settings.from.name'),
                            '{email}' => $email,
                            '{user}' => $user,
                            '{password}' => $raw_password,
                            '{url}' => $url,
                        ];

                        $subject = flextype('parsers')->shortcode()->process($new_password_email['subject']);
                        $content = flextype('parsers')->markdown()->parse(flextype('parsers')->shortcode()->process($new_password_email['content']));

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = strtr($subject, $tags);
                        $mail->Body    = strtr($content, $tags);

                        // Send email
                        $mail->send();

                    } catch (\Exception $e) {

                    }

                    flextype('flash')->addMessage('success', __('accounts_admin_message_new_password_was_sended'));

                    // Run event onAccountsAdminNewPasswordReset
                    flextype('emitter')->emit('onAccountsAdminNewPasswordReset');

                    return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
                }

                return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
            }

            flextype('flash')->addMessage('error', __('accounts_admin_message_hashed_password_reset_not_valid'));

            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
        }

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));

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
            Arrays::delete($post_data, '__csrf_token');
            
            Arrays::delete($post_data, 'form-save-action');
            Arrays::delete($post_data, 'email');

            $raw_hash                           = bin2hex(random_bytes(16));
            $post_data['hashed_password_reset'] = password_hash($raw_hash, PASSWORD_BCRYPT);

            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = flextype('serializers')->yaml()->decode($user_file_body);

            // Create account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                flextype('serializers')->yaml()->encode(
                    array_merge($user_file_data, $post_data)
                )
            )) {
                try {

                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $reset_password_email = flextype('serializers')->frontmatter()->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/reset-password.md'));

                    //Recipients
                    $mail->setFrom(flextype('registry')->get('plugins.accounts-admin.settings.from.email'), flextype('registry')->get('plugins.accounts-admin.settings.from.name'));
                    $mail->addAddress($email, $email);

                    if (flextype('registry')->has('flextype.settings.url') && flextype('registry')->get('flextype.settings.url') !== '') {
                        $url = flextype('registry')->get('flextype.settings.url');
                    } else {
                        $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                    }

                    if (isset($user_file_data['full_name'])) {
                        $user = $user_file_data['full_name'];
                    } else {
                        $user = $email;
                    }

                    $tags = [
                        '{sitename}' => flextype('registry')->get('plugins.accounts-admin.settings.from.name'),
                        '{email}' => $email,
                        '{user}' => $user,
                        '{url}' => $url,
                        '{new_hash}' => $raw_hash,
                    ];

                    $subject = flextype('parsers')->shortcode()->process($reset_password_email['subject']);
                    $content = flextype('parsers')->markdown()->parse(flextype('parsers')->shortcode()->process($reset_password_email['content']));

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                } catch (\Exception $e) {

                }

                // Run event onAccountsAdminNewPasswordReset
                flextype('emitter')->emit('onAccountsAdminNewPasswordReset');

                return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
            }

            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
        }

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
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
            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
        }

        // Clear cache before proccess
        Filesystem::deleteDir(PATH['tmp']);

        // Get Data from POST
        $post_data = $request->getParsedBody();

        // Get email
        $email = $post_data['email'];

        if (! Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $email . '/profile.yaml')) {
            // Generate UUID
            $uuid = Uuid::uuid4()->toString();

            // Get time
            $time = date(flextype('registry')->get('flextype.settings.date_format'), time());

            // Get hashed password
            $hashed_password = password_hash($post_data['password'], PASSWORD_BCRYPT);

            $post_data['email']           = $email;
            $post_data['registered_at']   = $time;
            $post_data['uuid']            = $uuid;
            $post_data['hashed_password'] = $hashed_password;
            $post_data['roles']           = 'admin';
            $post_data['state']           = 'enabled';

            Arrays::delete($post_data, '__csrf_token');
            
            Arrays::delete($post_data, 'password');
            Arrays::delete($post_data, 'form-save-action');

            // Create accounts directory and account
            Filesystem::createDir(PATH['project'] . '/accounts/' . $post_data['email']);

            // Create admin account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $email . '/profile.yaml',
                flextype('serializers')->yaml()->encode(
                    $post_data
                )
            )) {
                try {

                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $new_user_email = flextype('serializers')->frontmatter()->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-user.md'));

                    //Recipients
                    $mail->setFrom(flextype('registry')->get('plugins.accounts-admin.settings.from.email'), flextype('registry')->get('plugins.accounts-admin.settings.from.name'));
                    $mail->addAddress($email, $email);

                    if (isset($post_data['full_name'])) {
                        $user = $post_data['full_name'];
                    } else {
                        $user = $email;
                    }

                    $tags = [
                        '{sitename}' =>  flextype('registry')->get('plugins.accounts-admin.settings.from.name'),
                        '{user}'    => $user,
                    ];

                    $subject = flextype('parsers')->shortcode()->process($new_user_email['subject']);
                    $content = flextype('parsers')->markdown()->parse(flextype('parsers')->shortcode()->process($new_user_email['content']));

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                } catch (\Exception $e) {

                }

                // Update default entry
                flextype('entries')->update('home', ['created_by' => $uuid, 'published_by' => $uuid, 'published_at' => $time, 'created_at' => $time]);

                // Create default entries delivery token
                $api_delivery_entries_token = bin2hex(random_bytes(16));
                $api_delivery_entries_token_dir_path  = PATH['project'] . '/tokens/entries/' . $api_delivery_entries_token;
                $api_delivery_entries_token_file_path = $api_delivery_entries_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_entries_token_dir_path)) Filesystem::createDir($api_delivery_entries_token_dir_path);

                Filesystem::write(
                    $api_delivery_entries_token_file_path,
                    flextype('serializers')->yaml()->encode([
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
                    flextype('serializers')->yaml()->encode([
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
                    flextype('serializers')->yaml()->encode([
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

                // Create default media files delivery token
                $api_delivery_media_files_token = bin2hex(random_bytes(16));
                $api_delivery_media_files_token_dir_path  = PATH['project'] . '/tokens/media/files/' . $api_delivery_media_files_token;
                $api_delivery_media_files_token_file_path = $api_delivery_media_files_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_media_files_token_dir_path)) Filesystem::createDir($api_delivery_media_files_token_dir_path);

                Filesystem::write(
                    $api_delivery_media_files_token_file_path,
                    flextype('serializers')->yaml()->encode([
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

                // Create default media folders delivery token
                $api_delivery_media_folders_token = bin2hex(random_bytes(16));
                $api_delivery_media_folders_token_dir_path  = PATH['project'] . '/tokens/media/folders/' . $api_delivery_media_folders_token;
                $api_delivery_media_folders_token_file_path = $api_delivery_media_folders_token_dir_path . '/token.yaml';

                if (! Filesystem::has($api_delivery_media_folders_token_dir_path)) Filesystem::createDir($api_delivery_media_folders_token_dir_path);

                Filesystem::write(
                    $api_delivery_media_folders_token_file_path,
                    flextype('serializers')->yaml()->encode([
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
                $custom_flextype_settings_file_data = flextype('serializers')->yaml()->decode(Filesystem::read($custom_flextype_settings_file_path));

                $custom_flextype_settings_file_data['api']['images']['default_token']   = $api_images_token;
                $custom_flextype_settings_file_data['api']['entries']['default_token']  = $api_delivery_entries_token;
                $custom_flextype_settings_file_data['api']['registry']['default_token'] = $api_delivery_registry_token;
                $custom_flextype_settings_file_data['api']['media']['files']['default_token'] = $api_delivery_media_files_token;
                $custom_flextype_settings_file_data['api']['media']['folders']['default_token'] = $api_delivery_media_folders_token;


                Filesystem::write($custom_flextype_settings_file_path, flextype('serializers')->yaml()->encode($custom_flextype_settings_file_data));

                // Create uploads dir for default entries
                if (! Filesystem::has(PATH['project'] . '/media/entries/home/')) {
                    Filesystem::createDir(PATH['project'] . '/media/entries/home/');
                }

                // Set super admin regisered = true
                $accounts_admin_config = flextype('serializers')->yaml()->decode(Filesystem::read(PATH['project'] . '/plugins/accounts-admin/settings.yaml'));
                $accounts_admin_config['supper_admin_registered'] = true;
                Filesystem::write(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml', flextype('serializers')->yaml()->encode($accounts_admin_config));

                // Clear cache after proccess
                Filesystem::deleteDir(PATH['tmp']);

                return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
            }

            return $response->withRedirect(flextype('router')->pathFor('admin.accounts.registration'));
        }

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.registration'));
    }

    /**
     * Logout page process
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     */
    public function logoutProcess(Request $request, Response $response) : Response
    {
        flextype('session')->destroy();

        // Run event onAccountsAdminLogout
        flextype('emitter')->emit('onAccountsAdminLogout');

        return $response->withRedirect(flextype('router')->pathFor('admin.accounts.login'));
    }

    protected function isSuperAdminExists()
    {
        return flextype('registry')->get('plugins.accounts-admin.settings.supper_admin_registered');
    }
}
