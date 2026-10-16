<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Installation & Deployment

The ecommerce admin panel lives in a separate repository, [`carepeerinfotech/ecommerce`](https://github.com/carepeerinfotech/ecommerce). It is included here as a **git submodule** at `packages/ecommerce` and loaded by Composer as a `path` repository.

A plain `git clone` or `git pull` does **not** download submodule code. If `packages/ecommerce` is empty, `composer install` fails with:

```
Class "Carepeerinfotech\Ecommerce\EcommerceServiceProvider" not found
Script @php artisan package:discover --ansi handling the post-autoload-dump event returned with error code 1
```

### Fresh clone

```bash
git clone --recurse-submodules https://github.com/wizcoderrSayam/ravi_exclusive.git
cd ravi_exclusive
composer run setup
```

### Existing clone or server

```bash
git submodule sync
git submodule update --init --recursive
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

Check that the package is present with `ls packages/ecommerce/src`. It should list `EcommerceServiceProvider.php`.

### Deploy script

Always update the submodule **before** running Composer:

```bash
git pull origin main
git submodule update --init --recursive
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

### Server access to the submodule

The submodule URL uses SSH (`git@github.com:carepeerinfotech/ecommerce.git`), and the repository is private. If `git submodule update` fails with `Permission denied (publickey)`, the server needs read access to that repository. Use one of these:

- **Deploy key**: generate a key on the server (`ssh-keygen -t ed25519`) and add the public key under *carepeerinfotech/ecommerce → Settings → Deploy keys*. GitHub does not allow the same deploy key on two repositories, so if the server already uses one for this repository, create a second key and give it a host alias in `~/.ssh/config`.
- **HTTPS with a personal access token**, set locally on the server only:

  ```bash
  git config submodule.packages/ecommerce.url https://<TOKEN>@github.com/carepeerinfotech/ecommerce.git
  git submodule update --init --recursive
  ```

  The token is stored in plain text in `.git/config`. Use a fine-grained token with read-only access to this one repository.

### Updating the package

To pull the latest package changes into this project:

```bash
git submodule update --remote packages/ecommerce
git add packages/ecommerce
git commit -m "Update ecommerce package"
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
