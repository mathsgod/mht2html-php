# mht2html-php

A PHP library to convert MHT/MHTML files into self-contained HTML, with all inline images embedded as base64 data URIs.

## Requirements

- PHP 8.0+
- [`ext-mailparse`](https://www.php.net/manual/en/book.mailparse.php) (`pecl install mailparse`)

## Installation

```bash
composer require mathsgod/mht2html
```

## Usage

### Convert from a file

```php
use Mht2Html\Converter;

$converter = new Converter();
$html = $converter->convertFile('/path/to/email.mht');

file_put_contents('output.html', $html);
```

### Convert from a string

```php
use Mht2Html\Converter;

$converter = new Converter();
$mht = file_get_contents('/path/to/email.mht');
$html = $converter->convertString($mht);
```

## How it works

1. Parses the MHT/MHTML file using [`php-mime-mail-parser`](https://github.com/php-mime-mail-parser/php-mime-mail-parser).
2. Extracts the HTML body (Quoted-Printable and charset decoding handled automatically).
3. Collects all inline image attachments and maps each `Content-Location` to a base64 data URI.
4. Replaces every `src="..."` reference in the HTML with the corresponding data URI, producing a fully self-contained HTML file with no external dependencies.

## API

### `Converter::convertFile(string $path): string`

Reads the MHT file at `$path` and returns self-contained HTML.  
Throws `\InvalidArgumentException` if the file cannot be read.

### `Converter::convertString(string $mht): string`

Accepts raw MHT/MHTML content as a string and returns self-contained HTML.

## License

MIT
