<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restricts a route to the roles given as arguments, e.g. 'role:admin' or
 * 'role:teacher,admin'. Must run after the 'auth' filter.
 */
class RoleFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($arguments === null || $arguments === []) {
            throw new \LogicException('The role filter needs at least one role, e.g. role:admin.');
        }

        if (! service('auth')->hasRole(...$arguments)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/forbidden'));
        }
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
