<?php

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Enums\Method;
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface as PluginCookieJarInterface;
use Weijiajia\SaloonphpCookiePlugin\CookieException;
use Weijiajia\SaloonphpCookiePlugin\HasCookie;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use GuzzleHttp\Cookie\SetCookie;
beforeEach(function () {
    $this->traitObject = new class {
        use HasCookie;
    };
});

test('getCookieJar initially returns null', function () {
    $this->assertNull($this->traitObject->getCookieJar());
});

test('withCookies sets guzzle cookie jar', function () {
    $mockCookieJar = $this->createMock(GuzzleCookieJarInterface::class);
    $this->traitObject->withCookies($mockCookieJar);
    $this->assertSame($mockCookieJar, $this->traitObject->getCookieJar());
});

test('withCookies creates cookie jar from array', function () {
    $cookiesArray = [
        ['Name' => 'test', 'Value' => '123', 'Domain' => 'example.com']
    ];
    $this->traitObject->withCookies($cookiesArray);
    $this->assertInstanceOf(CookieJar::class, $this->traitObject->getCookieJar());
});

test('withCookies sets null', function () {
    $this->traitObject->withCookies(null);
    $this->assertNull($this->traitObject->getCookieJar());
});

test('bootHasCookie throws exception when no provider', function () {
    // Connector uses HasCookie, but neither Connector nor Request implement PluginCookieJarInterface
    $connectorUsingTrait = new class extends Connector {
        use HasCookie; // Connector uses the trait
        public function resolveBaseUrl(): string { return 'http://localhost'; }
        // This class does NOT implement PluginCookieJarInterface
    };

    $plainRequest = new class extends Request {
        protected Method $method = Method::GET;
        public function resolveEndpoint(): string { return '/noop'; }
        // This class does NOT implement PluginCookieJarInterface
    };

    $mockClient = new MockClient(['*' => MockResponse::make(body: '', status: 200)]);

    // The bootHasCookie method on $connectorUsingTrait will be triggered by Saloon during send().
    $connectorUsingTrait->send($plainRequest, $mockClient);

})->throws(CookieException::class, sprintf('Your connector or request must implement %s to use the HasCookie plugin', PluginCookieJarInterface::class));

test('bootHasCookie does not attach middleware if provider returns null cookie jar', function () {
    
    // This test remains a direct unit test of bootHasCookie's internal logic,
    // as asserting "absence of middleware registration" via send() is more complex.
    $bootTestTraitObject = new class { use HasCookie; };
    
    $anonymousConnector = new class extends Connector {
        public function resolveBaseUrl(): string { return 'http://dummy-connector.com'; }
    };
    $cookieProviderRequest = new class extends Request implements PluginCookieJarInterface {
        protected Method $method = Method::GET;
        public function resolveEndpoint(): string { return '/noop'; }
        public function getCookieJar(): ?GuzzleCookieJarInterface { return null; }
    };

    // We need a PendingRequest to call bootHasCookie on the trait object.
    // For this specific test, we directly create a PendingRequest.
    // The $bootTestTraitObject will act as if it's part of the PendingRequest's lifecycle.
    $pendingRequest = $this->getMockBuilder(PendingRequest::class)
        ->setConstructorArgs([$anonymousConnector, $cookieProviderRequest])
        ->onlyMethods(['middleware'])
        ->getMock();

    $pipelineMock = $this->getMockBuilder(\Saloon\Helpers\MiddlewarePipeline::class)
        ->disableOriginalConstructor()
        ->getMock();
    $pipelineMock->expects($this->never())->method('onRequest');
    $pipelineMock->expects($this->never())->method('onResponse');
    $pendingRequest->method('middleware')->willReturn($pipelineMock);

    $bootTestTraitObject->bootHasCookie($pendingRequest); // Directly test the boot method
    $this->assertTrue(true); // Assertion: no exception and middleware methods not called

});

test('bootHasCookie with request as provider (via send)', function () {
    $mockGuzzleCookieJar = $this->createMock(GuzzleCookieJarInterface::class);
    $cookieProviderRequest = new class($mockGuzzleCookieJar) extends Request implements PluginCookieJarInterface {
        use HasCookie;
        protected Method $method = Method::GET;
        private GuzzleCookieJarInterface $jarInstance;
        public function __construct(GuzzleCookieJarInterface $jar) {
            $this->jarInstance = $jar;
        }
        public function resolveEndpoint(): string { return '/test'; }
        public function getCookieJar(): ?GuzzleCookieJarInterface { return $this->jarInstance; }
    };
    $plainConnector = new class extends Connector {
        public function resolveBaseUrl(): string { return 'http://localhost'; }
    };
    $modifiedPsrRequest = new Psr7Request('GET', 'http://localhost/test', ['Cookie' => 'name=value']);
    $psrResponse = new Psr7Response(200, ['Set-Cookie' => 'session=abc'], 'response body'); // Added body to Psr7Response

    $mockGuzzleCookieJar->expects($this->once())
        ->method('withCookieHeader')
        ->with($this->isInstanceOf(Psr7Request::class))
        ->willReturn($modifiedPsrRequest);
    $mockGuzzleCookieJar->expects($this->once())
        ->method('extractCookies')
        ->with($this->isInstanceOf(Psr7Request::class), $this->isInstanceOf(Psr7Response::class));
    $mockClient = new MockClient([
        '*' => MockResponse::make(
            body: (string) $psrResponse->getBody(),
            status: $psrResponse->getStatusCode(),
            headers: $psrResponse->getHeaders()
        ),
    ]);
    $plainConnector->send($cookieProviderRequest, $mockClient);
});

test('bootHasCookie with connector as provider (via send)', function () {
    $mockGuzzleCookieJar = $this->createMock(GuzzleCookieJarInterface::class);
    $cookieProviderConnector = new class($mockGuzzleCookieJar) extends Connector implements PluginCookieJarInterface {
        use HasCookie;
        private GuzzleCookieJarInterface $jarInstance;
        public function __construct(GuzzleCookieJarInterface $jar) {
            $this->jarInstance = $jar;
        }
        public function resolveBaseUrl(): string { return 'http://localhost'; }
        public function getCookieJar(): ?GuzzleCookieJarInterface { return $this->jarInstance; }
    };
    $plainRequest = new class extends Request {
        protected Method $method = Method::GET;
        public function resolveEndpoint(): string { return '/test'; }
    };
    $modifiedPsrRequest = new Psr7Request('GET', 'http://localhost/test', ['Cookie' => 'name=value']);
    $psrResponse = new Psr7Response(200, ['Set-Cookie' => 'session=abc'], 'response body'); // Added body to Psr7Response

    $mockGuzzleCookieJar->expects($this->once())
        ->method('withCookieHeader')
        ->with($this->isInstanceOf(Psr7Request::class))
        ->willReturn($modifiedPsrRequest);
    $mockGuzzleCookieJar->expects($this->once())
        ->method('extractCookies')
        ->with($this->isInstanceOf(Psr7Request::class), $this->isInstanceOf(Psr7Response::class));
    $mockClient = new MockClient([
        '*' => MockResponse::make(
            body: (string) $psrResponse->getBody(),
            status: $psrResponse->getStatusCode(),
            headers: $psrResponse->getHeaders()
        ),
    ]);
    $cookieProviderConnector->send($plainRequest, $mockClient);
});

it("test cookie header is added to the request", function () {
    
    $anonymousConnector = new class extends Connector  implements PluginCookieJarInterface
    {
        use HasCookie;
        public function resolveBaseUrl(): string { return 'http://dummy-connector.com'; }
    };

    $cookieProviderRequest = new class extends Request {
        protected Method $method = Method::GET;
        public function resolveEndpoint(): string { return '/noop'; }
    };

    $mockClient = new MockClient([$cookieProviderRequest::class => MockResponse::make(body: '', status: 200)]);

    $cookieJar = new CookieJar();
    $cookieJar->setCookie(new SetCookie(['Name' => 'test', 'Value' => '123', 'Domain' => 'dummy-connector.com']));

    $anonymousConnector->withCookies($cookieJar);

    // The bootHasCookie method on $connectorUsingTrait will be triggered by Saloon during send().
    $response = $anonymousConnector->send($cookieProviderRequest, $mockClient);

    expect($response->getPendingRequest()->headers()->get('Cookie'))->toBe('test=123');

});

it("test cookie is extracted from the response", function () {
    
    $cookieJar = new CookieJar();
    
    $anonymousConnector = new class extends Connector  implements PluginCookieJarInterface
    {
        use HasCookie;
        public function resolveBaseUrl(): string { return 'http://dummy-connector.com'; }
    };

    $cookieProviderRequest = new class extends Request {
        protected Method $method = Method::GET;
        public function resolveEndpoint(): string { return '/noop'; }
    };

    $mockClient = new MockClient([ $cookieProviderRequest::class => MockResponse::make(body: '', status: 200,headers: ['Set-Cookie' => 'test=123']) ]);

    $anonymousConnector->withCookies($cookieJar);

    $anonymousConnector->send($cookieProviderRequest, $mockClient);

    expect($cookieJar->getCookieByName('test'))->not->toBeNull()->and($cookieJar->getCookieByName('test')->getValue())->toBe('123');
});