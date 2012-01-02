<?php

/*
 * This file is part of the rfc2epub package
 *
 * (c) Justin Rainbow <justin.rainbow@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Rfc2ePub\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ePub\Resource\Dumper\OpfResourceDumper;
use ePub\Resource\Dumper\TocResourceDumper;
use ePub\Definition\Package;
use ePub\Definition\Manifest;
use ePub\Definition\Metadata;
use ePub\Definition\ManifestItem;
use ePub\Definition\MetadataItem;

/**
 * @author Justin Rainbow <justin.rainbow@gmail.com>
 */
class CreateCommand extends Command
{
	private $workingDir;

    protected function configure()
    {
        $this
            ->setName('create')
            ->setDescription('Creates an ePub file from RFC HTML files.')
            ->setDefinition(array(
				new InputArgument('output', InputArgument::REQUIRED, 'Target location for the created ePub file.'),
				new InputArgument('source', InputArgument::REQUIRED, 'Directory containing the HTML files needed for ePub'),
                // new InputOption('dev', null, InputOption::VALUE_NONE, 'Forces installation from package sources when possible, including VCS information.'),
                // new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Outputs the operations but will not execute anything (implicitly enables --verbose).'),
                // new InputOption('no-install-recommends', null, InputOption::VALUE_NONE, 'Do not install recommended packages.'),
                // new InputOption('install-suggests', null, InputOption::VALUE_NONE, 'Also install suggested packages.'),
            ))
            ->setHelp(<<<EOT
The <info>create</info> command builds a ePub file

<info>rfc2epub create [output] [source]</info>

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$output = $input->getArgument('output');
		$source = $input->getArgument('source');

		// use realpath because we need the absolute location
		$filename = realpath(dirname($output)).'/'.basename($output);
		$inputDir   = new \RecursiveDirectoryIterator($source);
		$workingDir = sys_get_temp_dir() . uniqid('build-epub-');
		mkdir($workingDir);


		$this->workingDir = $workingDir;



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

		foreach (new \RecursiveIteratorIterator($inputDir) as $file) {
		  $item = new ManifestItem();
		  $item->href = ltrim(str_replace($source, '', $file->getPathname()), '/');
		  $item->type = 'application/xhtml+xml';
		  $item->id   = $file->getBasename('.html');
		  $item->setContent($this->repairSpecHTML(file_get_contents($file->getPathname())));

		  $package->manifest->add($item);
		  $package->spine->add($item);
		}









		$this->addFromString('mimetype', 'application/epub+zip');
		$this->addFromString('META-INF/container.xml', <<<EOT
<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
   <rootfiles>
      <rootfile full-path="content.opf" media-type="application/oebps-package+xml"/>
   </rootfiles>
</container>
EOT
		);

		$dumper = new OpfResourceDumper($package);
		$this->addFromString('content.opf', $dumper->dump());

		foreach ($package->manifest->all() as $item) {
			$this->addFromString($item->href, $item->getContent());
		}

		exec(sprintf('(cd %s && zip -q0Xj %s %s)', escapeshellarg($workingDir), escapeshellarg($filename), 'mimetype'));
		exec(sprintf('(cd %s && zip -Xur9D %s *)', escapeshellarg($workingDir), escapeshellarg($filename)));
		exec(sprintf('rm -rf %s', escapeshellarg($workingDir)));
    }
	
	private function addFromString($path, $contents)
	{
		$file = $this->workingDir . '/' . $path;
		if (!is_dir($dir = dirname($file))) {
			mkdir($dir);
		}
	
		file_put_contents($file, $contents);
	}


	private function repairSpecHTML($html)
	{
	    $dom = new \DOMDocument();
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

	      if ($previous && $previous instanceof \DOMElement && $previous->tagName == 'dd') {
	        $para->previousSibling->appendChild($para);
	      }
	    }

	    $doc = new \DOMDocument('1.0');
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
	
}