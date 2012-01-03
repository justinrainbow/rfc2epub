<?php


namespace Rfc2ePub\Converter;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Txt2HtmlConverter
{
    private $source;

    private $outputDirectory;

    private $tocName = 'toc.html';

    public function __construct($source, $outputDirectory)
    {
        $this->source = $source;
        $this->outputDirectory = $outputDirectory;

        $builder = new ProcessBuilder(array(__DIR__.'/../Resources/bin/rfc2html.pl'));
        $builder
            ->add($this->source)
            ->setWorkingDirectory($this->outputDirectory)
        ;

        $this->process = $builder->getProcess();
    }

    public function convert()
    {
        $this->process->run();

        // file_put_contents($this->outputDirectory.'/'.$this->tocName, $this->process->getOutput());

        return true;
    }

    public function getFiles()
    {
        $dir = new \RecursiveDirectoryIterator($this->outputDirectory);
        $trimFromPath = strlen($this->outputDirectory) + 1;
        $files = array();

        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $shortPathname = substr($file->getPathname(), $trimFromPath);
            $files[$shortPathname] = $file->getPathname();
        }

        return $files;
    }
}
