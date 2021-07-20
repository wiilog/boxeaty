<?php

namespace App\Service;

use App\Entity\Attachment;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

class AttachmentService {

    /** @Required */
    public KernelInterface $kernel;

    public function createAttachment(string $type, UploadedFile $file): Attachment {
        $serverName = $this->saveFile($type, $file);
        $originalName = $file->getClientOriginalName();

        $attachment = new Attachment();
        $attachment
            ->setOriginalName($originalName)
            ->setServerName($serverName)
            ->setType($type);
        return $attachment;
    }

    public function removeFile(Attachment $attachment): bool {
        $directory = $this->getServerPath($attachment->getType());
        $filePath = $directory . '/' . $attachment->getServerName();
        try {
            $success = unlink($filePath);
        }
        catch(Throwable $ignored) {
            $success = false;
        }
        return $success;
    }

    private function saveFile(string $type, UploadedFile $file): string {
        $directory = $this->getServerPath($type);
        do {
            $filename = uniqid() . '.' . strtolower($file->getClientOriginalExtension()) ?? '';
            $filePath = $directory . '/' . $filename;
        }
        while (file_exists($filePath));

        $file->move($directory, $filename);

        return $filename;
    }

    private function getServerPath(string $type) {
        return $this->kernel->getProjectDir() . '/public/' . Attachment::ATTACHMENT_DIRECTORY_PATHS[$type];
    }
}
