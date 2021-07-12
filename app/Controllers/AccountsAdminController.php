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
        // Get data from POST
        $data = $request->getParsedBody();

        // Delete...
        if (flextype('accounts')->delete($data['account-id'])) {
            flextype('flash')->addMessage('success', __('accounts_admin_message_account_deleted'));
        } else {
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
        $id = $post_data['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $id . '/profile.yaml')) {
            $user_file = flextype('serializers')->yaml()->decode(Filesystem::read($_user_file), false);

            if (password_verify(trim($post_data['password']), $user_file['hashed_password'])) {

                flextype('acl')->setUserLoggedInEmail($id);
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
        $id = $args['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $id . '/profile.yaml')) {
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
                    PATH['project'] . '/accounts/' . $id . '/profile.yaml',
                    flextype('serializers')->yaml()->encode($user_file_data)
                )) {

                    try {

                        // Instantiation and passing `true` enables exceptions
                        $mail = new PHPMailer(true);

                        $new_password_email = flextype('serializers')->frontmatter()->decode(Filesystem::read(PATH['project'] . '/' . 'plugins/accounts-admin/templates/emails/new-password.md'));

                        //Recipients
                        $mail->setFrom(flextype('registry')->get('plugins.accounts-admin.settings.from.email'), flextype('registry')->get('plugins.accounts-admin.settings.from.name'));
                        $mail->addAddress($id, $id);

                        if (flextype('registry')->has('flextype.settings.url') && flextype('registry')->get('flextype.settings.url') !== '') {
                            $url = flextype('registry')->get('flextype.settings.url');
                        } else {
                            $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                        }

                        if (isset($user_file_data['full_name'])) {
                            $user = $user_file_data['full_name'];
                        } else {
                            $user = $id;
                        }

                        $tags = [
                            '{sitename}' => flextype('registry')->get('plugins.accounts-admin.settings.from.name'),
                            '{email}' => $id,
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
        $id = $post_data['email'];

        if (Filesystem::has($_user_file = PATH['project'] . '/accounts/' . $id . '/profile.yaml')) {
            Arrays::delete($post_data, '__csrf_token');
            
            Arrays::delete($post_data, 'form-save-action');
            Arrays::delete($post_data, 'email');

            $raw_hash                           = bin2hex(random_bytes(16));
            $post_data['hashed_password_reset'] = password_hash($raw_hash, PASSWORD_BCRYPT);

            $user_file_body = Filesystem::read($_user_file);
            $user_file_data = flextype('serializers')->yaml()->decode($user_file_body);

            // Create account
            if (Filesystem::write(
                PATH['project'] . '/accounts/' . $id . '/profile.yaml',
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
                    $mail->addAddress($id, $id);

                    if (flextype('registry')->has('flextype.settings.url') && flextype('registry')->get('flextype.settings.url') !== '') {
                        $url = flextype('registry')->get('flextype.settings.url');
                    } else {
                        $url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
                    }

                    if (isset($user_file_data['full_name'])) {
                        $user = $user_file_data['full_name'];
                    } else {
                        $user = $id;
                    }

                    $tags = [
                        '{sitename}' => flextype('registry')->get('plugins.accounts-admin.settings.from.name'),
                        '{email}' => $id,
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

        // Clean `var` directory before proccess
        if (filesystem()->directory(PATH['tmp'])->exists()) {
            filesystem()->directory(PATH['tmp'])->clean();
        }

        // Get data from POST
        $data = $request->getParsedBody();

        // Process form
        $form = flextype('blueprints')->form($data)->process();

        if (! flextype('accounts')->has($form->get('fields.id'))) {
            
            $id = $form->get('fields.id');
            
            $form->set('fields.hashed_password', password_hash($form->get('fields.password'), PASSWORD_BCRYPT));
            $form->set('fields.roles', 'admin');
            $form->set('fields.state', 'enabled');
            $form->delete('fields.password');
            $form->delete('fields.id');

            // Create admin account
   
            if (flextype('accounts')->create($id, $form->get('fields'))) {
                try {

                    // Instantiation and passing `true` enables exceptions
                    $mail = new PHPMailer(true);

                    $newUserEmail = flextype('serializers')->frontmatter()->decode(filesystem()->file(PATH['project'] . '/plugins/accounts-admin/templates/emails/new-user.md')->get());

                    //Recipients
                    $mail->setFrom(flextype('registry')->get('plugins.accounts-admin.settings.from.email'), flextype('registry')->get('plugins.accounts-admin.settings.from.name'));
                    $mail->addAddress($id, $id);

                    if ($form->has('fields.name')) {
                        $user = $form->get('fields.name');
                    } else {
                        $user = $id;
                    }

                    $tags = [
                        '{sitename}' =>  flextype('registry')->get('plugins.accounts-admin.settings.from.name'),
                        '{user}'    => $user,
                    ];

                    $subject = flextype('parsers')->shortcode()->process($newUserEmail['subject']);
                    $content = flextype('parsers')->markdown()->parse(flextype('parsers')->shortcode()->process($newUserEmail['content']));

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = strtr($subject, $tags);
                    $mail->Body    = strtr($content, $tags);

                    // Send email
                    $mail->send();

                } catch (\Exception $e) {

                }

                // Update default entry
                flextype('entries')->update('home', ['created_by' => flextype('accounts')->storage()->get('create.data.uuid'), 
                                                     'published_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                                                     'published_at' => flextype('accounts')->storage()->get('create.data.registered_at'), 
                                                     'created_at' => flextype('accounts')->storage()->get('create.data.registered_at')]);

                // Create default entries delivery token
                $apiEntriesToken = bin2hex(random_bytes(16));
                $apiEntriesTokenDirPath  = PATH['project'] . '/tokens/entries/' . $apiEntriesToken;
                $apiEntriesTokenFilePath = $apiEntriesTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiEntriesTokenDirPath)->exists()) filesystem()->directory($apiEntriesTokenDirPath)->create(0755, true);

                filesystem()->file($apiEntriesTokenFilePath)->put(
                    flextype('serializers')->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'newspaper',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                        'updated_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'updated_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                    ])
                );

                // Create default images token
                $apiImagesToken = bin2hex(random_bytes(16));
                $apiImagesTokenDirPath  = PATH['project'] . '/tokens/images/' . $apiImagesToken;
                $apiImagesTokenFilePath = $apiImagesTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiImagesTokenDirPath)->exists()) filesystem()->directory($apiImagesTokenDirPath)->create(0755, true);

                filesystem()->file($apiImagesTokenFilePath)->put(
                    flextype('serializers')->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'images',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                        'updated_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'updated_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                    ])
                );

                // Create default registry delivery token
                $apiRegistryToken = bin2hex(random_bytes(16));
                $apiRegistryTokenDirPath  = PATH['project'] . '/tokens/registry/' . $apiRegistryToken;
                $apiRegistryTokenFilePath = $apiRegistryTokenDirPath . '/token.yaml';

                if (! filesystem()->directory($apiRegistryTokenDirPath)->exists()) filesystem()->directory($apiRegistryTokenDirPath)->create(0755, true);

                filesystem()->file($apiRegistryTokenFilePath)->put(
                    flextype('serializers')->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'archive',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                        'updated_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'updated_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                    ])
                );

                // Create default media files delivery token
                $apiMediaToken = bin2hex(random_bytes(16));
                $apiMediaTokenDirPath  = PATH['project'] . '/tokens/media/' . $apiMediaToken;
                $apiMediaTokenFilePath = $apiMediaTokenDirPath . '/token.yaml';

                if (! Filesystem::has($apiMediaTokenDirPath)) Filesystem::createDir($apiMediaTokenDirPath);

                filesystem()->file($apiMediaTokenFilePath)->put(
                    flextype('serializers')->yaml()->encode([
                        'title' => 'Default',
                        'icon' => [
                            'icon' => 'archive',
                            'set' => 'bootstrap',
                        ],
                        'limit_calls' => (int) 0,
                        'calls' => (int) 0,
                        'state' => 'enabled',
                        'uuid' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'created_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                        'updated_by' => flextype('accounts')->storage()->get('create.data.uuid'),
                        'updated_at' => flextype('accounts')->storage()->get('create.data.registered_at'),
                    ])
                );

                // Set Default API's tokens
                $customFlextypeSettingsFilePath = PATH['project'] . '/config/flextype/settings.yaml';
                $customFlextypeSettingsFileData = flextype('serializers')->yaml()->decode(filesystem()->file($customFlextypeSettingsFilePath)->get());

                $customFlextypeSettingsFileData['api']['images']['default_token']   = $apiImagesToken;
                $customFlextypeSettingsFileData['api']['entries']['default_token']  = $apiEntriesToken;
                $customFlextypeSettingsFileData['api']['registry']['default_token'] = $apiRegistryToken;
                $customFlextypeSettingsFileData['api']['media']['default_token']    = $apiMediaToken;

                filesystem()->file($customFlextypeSettingsFilePath)->put(flextype('serializers')->yaml()->encode($customFlextypeSettingsFileData));

                // Set super admin regisered = true
                $accountsAdminConfig = flextype('serializers')->yaml()->decode(filesystem()->file(PATH['project'] . '/plugins/accounts-admin/settings.yaml')->get());
                $accountsAdminConfig['supper_admin_registered'] = true;
                filesystem()->file(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml')->put(flextype('serializers')->yaml()->encode($accountsAdminConfig));

                // Clean `var` directory before proccess
                if (filesystem()->directory(PATH['tmp'])->exists()) {
                    filesystem()->directory(PATH['tmp'])->clean();
                }

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
