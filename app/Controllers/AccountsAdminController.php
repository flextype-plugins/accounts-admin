<?php

declare(strict_types=1);

namespace Flextype;

use Flextype\Component\Session\Session;
use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Arr\Arr;
use Ramsey\Uuid\Uuid;
use function date;
use function Flextype\Component\I18n\__;

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
                    'active' => true
                ],
            ],
            'buttons' => [
                'accounts_add' => [
                    'link' => $this->router->pathFor('admin.accounts.add'),
                    'title' => __('accounts_admin_create_new_user')
                ]
            ],
            'logged_in_username' => Session::get('account_username'),
            'logged_in_roles' => Session::get('account_roles'),
            'logged_in_uuid' => Session::get('account_uuid'),
            'logged_in' => Session::get('account_is_user_logged_in'),
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
                        'active' => true
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
                        'active' => true
                    ],
                ],
                'buttons' => [
                    'save_entry' => [
                        'type' => 'action',
                        'link' => 'javascript:;',
                        'title' => __('accounts_admin_save')
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
        if ($this->fieldsets->delete($request->getParsedBody()['fieldset-id'])) {
            $this->flash->addMessage('success', __('form_admin_message_fieldset_deleted'));
        } else {
            $this->flash->addMessage('error', __('form_admin_message_fieldset_was_not_deleted'));
        }

        return $response->withRedirect($this->router->pathFor('admin.fieldsets.index'));
    }
}
