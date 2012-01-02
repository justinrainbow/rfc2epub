# rfc2epub

## Installation

```sh
git clone https://github.com/justinrainbow/rfc2epub.git && cd rfc2epub
wget http://getcomposer.org/composer.phar
php composer.phar install
```

## Usage

```sh
mkdir specs
wget http://www.w3.org/Protocols/rfc2616/rfc2616.txt -O specs/rfc2616.txt
(cd specs && ../bin/rfc2html.pl rfc2616.txt)
bin/rfc2epub rfc2616.epub specs/
```


## TODO

 * Simplify the generation process
 * Possibly remove the perl dependency
 * Add support for cover art / other metadata
