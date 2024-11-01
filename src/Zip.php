<?php


namespace WhatArmy\Watchtower;


use PhpZip\ZipFile;
use ZipArchive;

class Zip
{
    public $zipArchiveAvailable;
    public $filename;
    public $files;

    public function __construct($filename)
    {
        $this->filename = $filename;
        $this->zipArchiveAvailable = $this->zipArchiveAvailable();
    }

    /**
     * @param mixed $files
     * @return Zip
     */
    public function setFiles($files)
    {
        $this->files = $files;
        return $this;
    }

    public function createOrUpdateArchive()
    {
        $this->zipArchiveAvailable ? $this->useZipArchive() : $this->usePhpZip();
    }

    /**
     * @throws \PhpZip\Exception\ZipException
     */
    private function usePhpZip()
    {
        $archive_location = WHTHQ_BACKUP_DIR . '/' . $this->filename;
        $zippy = new ZipFile();
        if (file_exists($archive_location)) {
            $zippy->openFile($archive_location);
        }
        foreach ($this->files as $file) {
            $zippy->addSplFile(new \SplFileInfo(ABSPATH . $file), $file);
        }
        $zippy->saveAsFile($archive_location);
        $zippy->close();
    }

    private function useZipArchive()
    {
        $archive_location = WHTHQ_BACKUP_DIR . '/' . $this->filename;
        $zippy = new ZipArchive();
        if (!file_exists($archive_location)) {
            $zippy->open($archive_location, ZipArchive::CREATE);
        } else {
            $zippy->open($archive_location);
        }

        foreach ($this->files as $file) {
            $zippy->addFile(ABSPATH . $file, $file);
        }
        $zippy->close();
    }


    /**
     * @return bool
     */
    private function zipArchiveAvailable(): bool
    {
        return class_exists("ZipArchive");
    }
}
