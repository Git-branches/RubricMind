<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a signed-in, active user (FR-01).
 *
 * Users with a temporary password set by an admin are sent to the
 * change-password page until they choose their own.
 */
class AuthFilter implements FilterInterface
{
    /**
     * Paths a user may still reach while they must change their password.
     */
    private const PASSWORD_CHANGE_PATHS = ['account/password', 'logout'];

    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
        }

        $path = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';

        if ($auth->user()['must_change_password'] && ! in_array($path, self::PASSWORD_CHANGE_PATHS, true)) {
            return redirect()->to(site_url('account/password'))
                ->with('warning', 'Please choose a new password before continuing.');
        }
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Signed-in pages show private records, so browsers must not cache them.
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
