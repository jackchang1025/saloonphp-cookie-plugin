<?php

namespace Weijiajia\SaloonphpCookiePlugin\Contracts;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;

interface CookieJarInterface
{
    public function getCookieJar(): ?GuzzleCookieJarInterface;

}