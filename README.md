# Mini WP Hook Parser

This is a basic parser that extracts hook documentation from WordPress source code. It is based on [WP Parser](https://github.com/WordPress/phpdoc-parser). Mainly intended for my personal use.

## Usage

```php
use YahnisElsts\MiniWpHookParser\Parser;

Parser::extractAndWriteToFile( 
    '/path/to/wordpress', 
    '/path/to/output.json' 
);
```

More advanced usage: 

```php
use YahnisElsts\MiniWpHookParser\Parser;
$wordpressRoot = '/path/to/wordpress';

//Find all PHP files, potentially excluding some.
$files = Parser::findSourceFiles(
    $wordpressRoot, 
    ['/excluded-dir/', 'excluded-file.php']
);

//Extract documented hooks from the files.
$hooks = Parser::parseFiles($files);

//Convert the hook details to associative arrays.
$exported = Parser::exportHooks(
    $hooks, 
    $wordpressRoot //Used to convert absolute paths to relative paths.
);
```

## Why not just use WP Parser?

At the time of writing, WP Parser was inconvenient to use because it's implemented as a WordPress plugin. I wanted something that I could use in a standalone PHP project. 

I also tried [WP Hooks Generator](https://github.com/wp-hooks/generator), but it triggered a large number of warnings and completely failed to parse some files. This could be because it had some very old dependencies, and I was trying to run it on PHP 8.1.

In the end, it was easier to just write my own, stripped-down version of WP Parser.

## Credits

This project was heavily inspired by [WP Parser](https://github.com/WordPress/phpdoc-parser) and reuses some of its code. 