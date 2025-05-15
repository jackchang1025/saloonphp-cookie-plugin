<?php

namespace Weijiajia\SaloonphpCookiePlugin;

use Saloon\Http\PendingRequest;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;
use GuzzleHttp\Cookie\CookieJar;
use Saloon\Http\Response;
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface;

trait HasCookie
{
    protected ?GuzzleCookieJarInterface $cookieJar = null;
    public function bootHasCookie(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();
        $connector = $pendingRequest->getConnector();

        if (! $request instanceof CookieJarInterface && ! $connector instanceof CookieJarInterface) {
            throw new CookieException(sprintf('Your connector or request must implement %s to use the HasCookie plugin', CookieJarInterface::class));
        }

        /** @var CookieJarInterface $cookieProvider */
        $cookieProvider = $request instanceof CookieJarInterface ? $request : $connector;

        $cookieJarInstance = $cookieProvider->getCookieJar();

        // If the CookieJarInterface implementor returns null or not a GuzzleCookieJarInterface,
        // then we do not proceed with cookie handling for this request.
        if (! $cookieJarInstance instanceof GuzzleCookieJarInterface) {
            return; // No valid cookie jar to use, so don't attach middleware or sync cookies.
        }

        // Request Middleware: Add Cookie header to the outgoing request
        $pendingRequest->middleware()->onRequest(function (PendingRequest $pendingReq) use ($cookieJarInstance) {
            // Create the initial PSR-7 request from PendingRequest's state
            $psrRequest = $pendingReq->createPsrRequest();
            // Let Guzzle's CookieJar add the 'Cookie' header to the PSR-7 request
            $modifiedPsrRequest = $cookieJarInstance->withCookieHeader($psrRequest);

            // Instead of attempting to replace the entire PSR-7 request object on PendingRequest
            // (which might be problematic if setPsrRequest is not available or suitable),
            // we extract the 'Cookie' header from the modified PSR-7 request
            // and set it directly on the PendingRequest's header bag.
            // Saloon will then use this header when constructing the final PSR-7 request for sending.
            if ($modifiedPsrRequest->hasHeader('Cookie')) {
                $cookieHeaderValue = $modifiedPsrRequest->getHeaderLine('Cookie');
                $pendingReq->headers()->add('Cookie', $cookieHeaderValue);
            }

            // Note: This approach assumes `withCookieHeader` primarily modifies/adds the 'Cookie' header.
            // If it makes other significant changes to the PSR-7 request (e.g., URI, body, other headers)
            // that are essential, those would also need to be transferred to the PendingRequest's state.
            // For standard cookie handling, updating the 'Cookie' header is usually sufficient.
        });

        // Response Middleware: Extract Cookies from the response
        $pendingRequest->middleware()->onResponse(function (Response $response) use ($cookieJarInstance) {

            $sentPsrRequest = $response->getPsrRequest();
            $psrResponse = $response->getPsrResponse();
            $cookieJarInstance->extractCookies($sentPsrRequest, $psrResponse);
        });
    }

    public function withCookies(GuzzleCookieJarInterface|array|null $cookies, bool $strictMode = false): static
    {
        if (is_array($cookies)) {
            $this->cookieJar = new CookieJar($strictMode, $cookies);
        } elseif ($cookies instanceof GuzzleCookieJarInterface) {
            $this->cookieJar = $cookies;
        } else {
            $this->cookieJar = null;
        }

        return $this;
    }

    public function getCookieJar(): ?GuzzleCookieJarInterface
    {
        return $this->cookieJar;
    }
}
