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

        if (isset($content->price)) {
            $boxType->setPrice($content->price);
        } if (isset($content->capacity)) {
            $boxType->setPrice($content->capacity);
        } if (isset($content->shape)) {
            $boxType->setPrice($content->shape);
        } if (isset($content->volume)) {
            $boxType->setPrice($content->volume);
        } if (isset($content->weight)) {
            $boxType->setPrice($content->weight);
        }

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
