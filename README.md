# Image to AVIF

A WordPress plugin that automatically converts uploaded images to AVIF format for optimal performance.

[![Monthly Downloads](https://badgen.net/packagist/dm/puredazzle/wp-image-to-avif)](https://packagist.org/packages/puredazzle/wp-image-to-avif/stats)
[![Latest Version](https://badgen.net/packagist/v/puredazzle/wp-image-to-avif)](https://packagist.org/packages/puredazzle/wp-image-to-avif)

## Features

- Automatically converts JPEG, PNG, WebP, and GIF images to AVIF on upload
- Converts all generated thumbnail sizes
- Manual conversion button in the Media Library for existing images
- Automatically updates image URLs in srcset attributes

## Requirements

- PHP 8.2+
- ImageMagick with AVIF support

## Installation

Require the package with Composer in the root directory of your project.

```sh
composer require puredazzle/wp-image-to-avif
```

The plugin will be installed as a [must-use plugin](https://github.com/vinkla/wordplate#must-use-plugins).

## Configuration

The plugin uses a default quality setting of 85. Images larger than 10MB will not be converted.

## License

[MIT](LICENSE) © [Chris Andersson](https://github.com/puredazzle)
