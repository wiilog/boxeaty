<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\BoxType;
use Doctrine\ORM\EntityManagerInterface;

class BoxTypeService {

    /** @Required */
    public AttachmentService $attachmentService;

    public function persistBoxType(EntityManagerInterface $manager, BoxType $boxType, $content): void {

        if ($boxType->getName() === BoxType::STARTER_KIT) {
            $boxType->setActive(true);
        } else {
            $boxType->setName($content->name)
                ->setActive($content->active);
        }

        if (isset($content->price)) {
            $boxType->setPrice($content->price);
        }

        if (isset($content->capacity)) {
            $boxType->setCapacity($content->capacity);
        }

        if (isset($content->shape)) {
            $boxType->setShape($content->shape);
        }

        if (isset($content->volume)) {
            $boxType->setVolume($content->volume);
        }

        if (isset($content->weight)) {
            $boxType->setWeight($content->weight);
        }

        if (isset($content->image)) {
            $imageAttachment = $this->attachmentService->createAttachment(Attachment::TYPE_BOX_TYPE_IMAGE, $content->image);
            $manager->persist($imageAttachment);
            $boxType->setImage($imageAttachment);
        } else if ($content->fileDeleted && $boxType->getImage()) {
            $this->attachmentService->removeFile($boxType->getImage());
            $manager->remove($boxType->getImage());
            $boxType->setImage(null);
        }

        $manager->persist($boxType);
    }

}
