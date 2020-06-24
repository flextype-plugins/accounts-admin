<?php

declare(strict_types=1);

/**
 * Flextype (http://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Flextype\Component\Filesystem\Filesystem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AccountsIsSupperAdminRegisteredMiddleware extends Container
{
    /**
     * Middleware Settings
     */
    protected $settings;

    /**
     * __construct
     */
    public function __construct($settings)
    {
        parent::__construct($settings['container']);

        $this->settings = $settings;
    }

    /**
     * __invoke
     *
     * @param Request  $request  PSR7 request
     * @param Response $response PSR7 response
     * @param callable $next     Next middleware
     */
    public function __invoke(Request $request, Response $response, callable $next) : Response
    {
        // Get admin config file
        if (Filesystem::has(PATH['project'] . '/config/plugins/accounts-admin/settings.yaml')) {
            $acconts_admin_config_path = PATH['project'] . '/config/plugins/accounts-admin/settings.yaml';
        } else {
            $acconts_admin_config_path = PATH['project'] . '/plugins/accounts-admin/settings.yaml';
        }

        $accounts_admin_config = $this->serializer->decode(Filesystem::read($acconts_admin_config_path), 'yaml');

        if ($accounts_admin_config['supper_admin_registered'] === false) {
            $response = $next($request, $response);
        } else {
            $response = $response->withRedirect($this->router->pathFor($this->settings['redirect']));
        }

        return $response;
    }
}
