<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use ShipIt\ShipIt;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    protected ShipIt $shipit;
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');

        $this->shipit = new ShipIt();
    }

    /**
     * Checks if the currently logged-in system user has permission to manage a specific project.
     * 
     * @param string $projectPath The absolute path to the project root.
     * @param array $config The project's configuration array (optional).
     * @return bool
     */
    protected function canManageProject(string $projectPath, array $config = []): bool
    {
        $username = session()->get('username');

        if (empty($username)) {
            return false;
        }

        // 1. Root user has blanket access
        if ($username === 'root') {
            return true;
        }

        // 2. Check explicit configuration in .deploy/config.json
        if (!empty($config['user'])) {
            return $config['user'] === $username;
        }

        // 3. Fallback: Check filesystem ownership of the project directory
        if (function_exists('posix_getpwuid') && file_exists($projectPath)) {
            $ownerId = @fileowner($projectPath);
            if ($ownerId !== false) {
                $ownerInfo = posix_getpwuid($ownerId);
                if ($ownerInfo && $ownerInfo['name'] === $username) {
                    return true;
                }
            }
        }

        return false;
    }
}
