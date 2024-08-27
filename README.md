Laravel JSON:API
===

This package allow to quickly prototype an [JSON:API](https://jsonapi.org) API, automatizing most of it from Eloquent or QueryBuilder.

It's based on top of Spatie [Query Builder](https://github.com/spatie/laravel-query-builder) and [JSON Api Paginate](https://github.com/spatie/laravel-json-api-paginate), allowing to easily switch when you need more flexibility without having to rework everything.

## Installation

You can install the package via composer:

```bash
composer require snow-digital/laravel-json-api
```

Optionally you can publish the config file with:

```bash
php artisan vendor:publish --tag=json-api-config
```

## Usage

If you are using Laravel default structure, you don't need to do anything else. It will detect and register automatically all Eloquent Models as resources.

You can check if the routes are registered correclty using:

```bash
php artisan route:list --name=json-api
```
