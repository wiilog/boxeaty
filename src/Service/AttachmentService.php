<?php

namespace App\Service;

use App\Entity\Attachment;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

class AttachmentService {

    private const EXTENSIONS = [
        "image/gif" => "gif",
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/bmp" => "bmp",
    ];

    /** @Required */
    public KernelInterface $kernel;

    public function createAttachment(string $type, $file): ?Attachment {
        $attachment = new Attachment();

        if ($file instanceof UploadedFile) {
            $serverName = $this->saveFile($type, $file);
            $originalName = $file->getClientOriginalName();

            $attachment->setOriginalName($originalName)
                ->setServerName($serverName)
                ->setType($type);
        } else if (is_array($file)) {
            if($file[1] === null) {
                return null;
            }

            $serverName = $this->saveFile($type, $file);

            $attachment->setOriginalName($file[0] . "." . pathinfo($serverName, PATHINFO_EXTENSION))
                ->setServerName($serverName)
                ->setType($type);
        } else {
            throw new RuntimeException("Unsupported parameter type");
        }

        return $attachment;
    }

    public function removeFile(Attachment $attachment): bool {
        $directory = $this->getServerPath($attachment->getType());
        $filePath = "$directory/{$attachment->getServerName()}";
        try {
            $success = unlink($filePath);
        } catch (Throwable $ignored) {
            $success = false;
        }
        return $success;
    }

    private function saveFile(string $type, $file): string {
        $directory = $this->getServerPath($type);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if ($file instanceof UploadedFile) {
            $name = $this->generateName($type, $file->getClientOriginalExtension());
            $file->move($directory, $name);
        } else if (is_array($file)) {
            dump($file[1]);
            $name = $this->generateName($type, self::EXTENSIONS[mime_content_type($file[1])]);

            file_put_contents("$directory/$name", file_get_contents($file[1]));
        } else {
            throw new RuntimeException("Unsupported parameter type");
        }

        return $name;
    }

    private function generateName(string $type, string $extension): string {
        $directory = $this->getServerPath($type);

        do {
            $filename = bin2hex(random_bytes(16)) . '.' . strtolower($extension);
            $path = "$directory/$filename";
        } while (file_exists($path));

        return $filename;
    }

    private function getServerPath(string $type) {
        return $this->kernel->getProjectDir() . '/public/' . Attachment::DIRECTORY_PATHS[$type];
    }

}
