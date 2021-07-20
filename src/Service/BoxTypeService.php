<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\BoxType;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class BoxTypeService {

    /** @Required */
    public AttachmentService $attachmentService;

    public function persistBoxType(EntityManagerInterface $entityManager,
                                   BoxType $boxType,
                                   StdClass $content): void {
        $boxType
            ->setName($content->name)
            ->setPrice($content->price)
            ->setActive($content->active)
            ->setCapacity($content->capacity)
            ->setShape($content->shape)
            ->setVolume($content->volume ?? null)
            ->setWeight($content->weight ?? null);

        if (isset($content->image)) {
            $imageAttachment = $this->attachmentService->createAttachment(Attachment::TYPE_BOX_TYPE_IMAGE, $content->image);
            $entityManager->persist($imageAttachment);
            $boxType->setImage($imageAttachment);
        }
        else if ($content->fileDeleted && $boxType->getImage()) {
            $this->attachmentService->removeFile($boxType->getImage());
            $entityManager->remove($boxType->getImage());
            $boxType->setImage(null);
        }
        $entityManager->persist($boxType);
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
