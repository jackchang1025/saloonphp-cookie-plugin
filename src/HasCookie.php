<?php

namespace Weijiajia\SaloonphpCookiePlugin;

use Saloon\Http\PendingRequest;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;
use GuzzleHttp\Cookie\CookieJar;
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface;

trait HasCookie
{
    protected ?GuzzleCookieJarInterface $cookieJar = null;

    public function bootHasCookie(PendingRequest $pendingRequest): void
    {
        $request = $pendingRequest->getRequest();
        $connector = $pendingRequest->getConnector();

        if (! $request instanceof CookieJarInterface && ! $connector instanceof CookieJarInterface) {
            throw new CookieException(sprintf('Your connector or request must implement %s to use the HasCaching plugin', CookieJarInterface::class));
        }

        /** @var GuzzleCookieJarInterface $cookieJar */
        $cookieJar = $request instanceof CookieJarInterface 
            ? $request->getCookieJar() 
            : $connector->getCookieJar();

        $pendingRequest->getConnector()->config()->add('cookies', $cookieJar);

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