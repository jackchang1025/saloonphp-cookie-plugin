# Saloon PHP Cookie Plugin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijiajia/saloonphp-cookie-plugin.svg?style=flat-square)](https://packagist.org/packages/weijiajia/saloonphp-cookie-plugin)
[![Total Downloads](https://img.shields.io/packagist/dt/weijiajia/saloonphp-cookie-plugin.svg?style=flat-square)](https://packagist.org/packages/weijiajia/saloonphp-cookie-plugin)

A cookie management plugin for [Saloon PHP](https://github.com/saloonphp/saloon), allowing for easy cookie handling in your API integrations.

## English Documentation

### Installation

You can install the package via composer:

```bash
composer require weijiajia/saloonphp-cookie-plugin
```

### Requirements

- PHP 8.0 or higher
- Saloon PHP v3.0 or higher

### Usage

#### 1. Implement the CookieJarInterface

First, implement the `CookieJarInterface` in your connector or request class:

```php
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;

class YourConnector extends Connector implements CookieJarInterface
{
    // Implementation required by CookieJarInterface
    public function getCookieJar(): ?GuzzleCookieJarInterface
    {
        // Return your cookie jar or null
    }
}
```

#### 2. Use the HasCookie trait

Add the `HasCookie` trait to your connector or request class:

```php
use Weijiajia\SaloonphpCookiePlugin\HasCookie;

class YourConnector extends Connector implements CookieJarInterface
{
    use HasCookie;
    
    // Rest of your class implementation
}
```

#### 3. Working with cookies

You can set cookies in various ways:

```php
// Set cookies using an array
$connector->withCookies([
    'name' => 'value',
    'another_cookie' => 'another_value',
]);

// Or use a GuzzleHttp CookieJar
$cookieJar = new \GuzzleHttp\Cookie\CookieJar();
// Configure your cookie jar...
$connector->withCookies($cookieJar);

// You can also use strict mode
$connector->withCookies($cookies, true); // Second parameter enables strict mode
```

#### 4. Access the cookie jar

You can access the cookie jar at any time:

```php
$cookieJar = $connector->getCookieJar();
```

### Example

```php
use Saloon\Http\Connector;
use Weijiajia\SaloonphpCookiePlugin\HasCookie;
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;

class ApiConnector extends Connector implements CookieJarInterface
{
    use HasCookie;
    
    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }
}

// Using the connector with cookies
$connector = new ApiConnector();
$connector->withCookies([
    'session_id' => '123456789',
    'user_token' => 'abcdef123456',
]);

// Make requests, and cookies will be handled automatically
$response = $connector->send(new YourRequest());
```

---

## 中文文档

### 安装

您可以通过 Composer 安装此插件：

```bash
composer require weijiajia/saloonphp-cookie-plugin
```

### 要求

- PHP 8.0 或更高版本
- Saloon PHP v3.0 或更高版本

### 使用方法

#### 1. 实现 CookieJarInterface

首先，在您的连接器或请求类中实现 `CookieJarInterface`：

```php
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;

class YourConnector extends Connector implements CookieJarInterface
{
    // CookieJarInterface 要求实现的方法
    public function getCookieJar(): ?GuzzleCookieJarInterface
    {
        // 返回您的 cookie jar 或 null
    }
}
```

#### 2. 使用 HasCookie trait

在您的连接器或请求类中添加 `HasCookie` trait：

```php
use Weijiajia\SaloonphpCookiePlugin\HasCookie;

class YourConnector extends Connector implements CookieJarInterface
{
    use HasCookie;
    
    // 类的其余实现
}
```

#### 3. 处理 cookies

您可以通过多种方式设置 cookies：

```php
// 使用数组设置 cookies
$connector->withCookies([
    'name' => 'value',
    'another_cookie' => 'another_value',
]);

// 或者使用 GuzzleHttp CookieJar
$cookieJar = new \GuzzleHttp\Cookie\CookieJar();
// 配置您的 cookie jar...
$connector->withCookies($cookieJar);

// 您还可以使用严格模式
$connector->withCookies($cookies, true); // 第二个参数启用严格模式
```

#### 4. 访问 cookie jar

您可以随时访问 cookie jar：

```php
$cookieJar = $connector->getCookieJar();
```

### 示例

```php
use Saloon\Http\Connector;
use Weijiajia\SaloonphpCookiePlugin\HasCookie;
use Weijiajia\SaloonphpCookiePlugin\Contracts\CookieJarInterface;
use GuzzleHttp\Cookie\CookieJarInterface as GuzzleCookieJarInterface;

class ApiConnector extends Connector implements CookieJarInterface
{
    use HasCookie;
    
    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }
}

// 使用带有 cookies 的连接器
$connector = new ApiConnector();
$connector->withCookies([
    'session_id' => '123456789',
    'user_token' => 'abcdef123456',
]);

// 发送请求，cookies 将自动处理
$response = $connector->send(new YourRequest());
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 