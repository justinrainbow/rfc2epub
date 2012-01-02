<?php

require_once __DIR__.'/autoload.php';

use ePub\Resource\Dumper\OpfResourceDumper;
use ePub\Resource\Dumper\TocResourceDumper;
use ePub\Definition\Package;
use ePub\Definition\Manifest;
use ePub\Definition\Metadata;
use ePub\Definition\ManifestItem;
use ePub\Definition\MetadataItem;

$outputDir = __DIR__.'/epub';
$filename = __DIR__.'/http-spec.epub';
$workingDir = sys_get_temp_dir() . uniqid('build-epub-');
mkdir($workingDir);


$files = glob(__DIR__.'/epub/*.html');


function addFromString($path, $contents)
{
	global $workingDir;
	
	$file = $workingDir . '/' . $path;
	if (!is_dir($dir = dirname($file))) {
		mkdir($dir);
	}
	
	file_put_contents($file, $contents);
}


function repairSpecHTML($html)
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    @$dom->loadHTML($html);

	// rename all <a name="blah"> to <a id="blah">
    foreach ($dom->getElementsByTagName('a') as $anchor) {
      if ($anchor->hasAttribute('name')) {
        $anchor->setAttribute('id', $anchor->getAttribute('name'));
        $anchor->removeAttribute('name');
      }
    }

    // filter the top <address> tag out (body > address:first-child)
    $address = $dom->getElementsByTagName('address')->item(0);
    if ($address && $address->parentNode->tagName == 'body') {
      $address->parentNode->removeChild($address);
    }

    // move all the misplaced <p> tags inside <dl>
    foreach ($dom->getElementsByTagName('p') as $para) {
      $previous = $para->previousSibling;

      if ($previous && $previous instanceof DOMElement && $previous->tagName == 'dd') {
        $para->previousSibling->appendChild($para);
      }
    }

    $doc = new DOMDocument('1.0');
    $doc->preserveWhitespace = false;
    $doc->formatOutput = true;
    $doc->loadHTML(<<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" 
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
EOT
);

    foreach ($dom->getElementsByTagName('html') as $node) {
      $el = $doc->importNode($node, true);
      $doc->appendChild($el);
    }

    return $doc->saveXML();
}



$package = new Package();

$metadata = array(
  'title' => 'HTTP Protocol',
  'identifier' => 'http-protocol',
  'language'   => 'en',
);

foreach ($metadata as $name => $value) {
  $item = new MetadataItem();
  $item->name = $name;
  $item->value = $value;

  $package->metadata->add($item);
}


$toc = new ManifestItem();
$toc->type = 'application/x-dtbncx+xml';
$toc->href = 'toc.ncx';
$toc->id   = 'ncx';
$toc->setContent(function () use ($package) {
	$dumper = new TocResourceDumper($package);
	return $dumper->dump();
});
$package->manifest->add($toc);

foreach ($files as $file) {
  $item = new ManifestItem();
  $item->href = str_replace(__DIR__.'/epub/', '', $file);
  $item->type = 'application/xhtml+xml';
  $item->id   = basename($file, '.html');
  $item->setContent(function () use ($file) {
	  return repairSpecHTML(file_get_contents($file));
  });

  $package->manifest->add($item);
  $package->spine->add($item);
}









addFromString('mimetype', 'application/epub+zip');
addFromString('META-INF/container.xml', <<<EOT
<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
   <rootfiles>
      <rootfile full-path="content.opf" media-type="application/oebps-package+xml"/>
   </rootfiles>
</container>
EOT
);

$dumper = new OpfResourceDumper($package);
addFromString('content.opf', $dumper->dump());

foreach ($package->manifest->all() as $item) {
	addFromString($item->href, $item->getContent());
}

exec(sprintf('(cd %s && zip -q0Xj %s %s)', escapeshellarg($workingDir), escapeshellarg($filename), 'mimetype'));
exec(sprintf('(cd %s && zip -Xur9D %s *)', escapeshellarg($workingDir), escapeshellarg($filename)));
exec(sprintf('rm -rf %s', escapeshellarg($workingDir)));