## This package is based on the repo no more maintained by nicolasbeauvais.

# Flysystem adapter for the Microsoft OneDrive API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tomsim/flysystem-onedrive.svg?style=flat-square)](https://packagist.org/packages/tomsim/flysystem-onedrive)
[![Build Status](https://img.shields.io/travis/tomsim/flysystem-onedrive/main.svg?style=flat-square)](https://travis-ci.org/tomsim/flysystem-onedrive)
[![StyleCI](https://github.styleci.io/repos/526379113/shield?branch=main)](https://styleci.io/repos/526379113)
[![Quality Score](https://img.shields.io/scrutinizer/g/tomsim/flysystem-onedrive.svg?style=flat-square)](https://scrutinizer-ci.com/g/tomsim/flysystem-onedrive)
[![Total Downloads](https://img.shields.io/packagist/dt/tomsim/flysystem-onedrive.svg?style=flat-square)](https://packagist.org/packages/tomsim/flysystem-onedrive)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.1-4baaaa.svg)](CODE_OF_CONDUCT.md) 

This package contains a [Flysystem](https://flysystem.thephpleague.com/) adapter for OneDrive. Under the hood, the [Microsoft Graph SDK](https://github.com/microsoftgraph/msgraph-sdk-php) is used.

## Installation

You can install the package via composer:

``` bash
composer require tomsim/flysystem-onedrive
```
or add direct this repo in composer.json

```json
"repositories": [
        {
            "url": "https://github.com/tomsim/flysystem-onedrive.git",
            "type": "git"
        }
    ],
```

## Usage

The first thing you need to do is get an authorization token for the Microsoft Graph API. For that you need to create an app on the [Microsoft Azure Portal](https://portal.azure.com/).

``` php
use Microsoft\Graph\Graph;
use League\Flysystem\Filesystem;
use TomSim\FlysystemOneDrive\OneDriveAdapter;

$graph = new Graph();
$graph->setAccessToken('T28cB69Fa5d9649b...');

$adapter = new OneDriveAdapter($graph, 'root');
$filesystem = new Filesystem($adapter);

```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email tomas.simkevicius@gmail.com instead of using the issue tracker.

## Credits

- [Tomas Šimkevičius](https://github.com/tomsim)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
