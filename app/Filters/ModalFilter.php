<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Supports forms shown in a modal (see assets/js/app.js).
 *
 * Modal requests send the "X-Modal: 1" header. When such a request ends in a
 * redirect (a successful save), the redirect is replaced by a small JSON reply
 * so the browser script can navigate itself. That keeps the flash message in
 * the session for the page the user actually sees.
 */
class ModalFilter implements FilterInterface
{
    public const HEADER = 'X-Modal';

    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request->getHeaderLine(self::HEADER) !== '1') {
            return;
        }

        $status = $response->getStatusCode();

        if ($status < 300 || $status >= 400 || ! $response->hasHeader('Location')) {
            return;
        }

        $location = $response->getHeaderLine('Location');

        return $response
            ->removeHeader('Location')
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setBody(json_encode(['redirect' => $location]));
    }
}
