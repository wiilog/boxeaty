<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\BoxType;
use Doctrine\ORM\EntityManagerInterface;
use StdClass;

class BoxTypeService {

    /** @Required */
    public AttachmentService $attachmentService;

    public function persistBoxType(EntityManagerInterface $entityManager,
                                   BoxType $boxType,
                                   StdClass $content): void {

        if ($boxType->getName() !== BoxType::STARTER_KIT) {
            $boxType
                ->setName($content->name)
                ->setActive($content->active);
        }

        $boxType
            ->setPrice($content->price)
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

}
