<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;
use WiiCommon\Helper\Stream;

class PreparationService {

    /** @Required */
    public ClientOrderService $clientOrderService;

    /**
     * @param array $crates
     *      [
     *          'number' => string,
     *          'boxes' => [
     *              'selected' => string[]
     *          ][]
     *      ][]
     */
    public function handlePreparedCrates(EntityManagerInterface $entityManager,
                                         ClientOrder            $clientOrder,
                                         array                  $crates): array {

        $client = $clientOrder->getClient();
        $cart = $clientOrder->getLines()
            ->map(fn(ClientOrderLine $line) => [
                'boxType' => $line->getBoxType(),
                'quantity' => $line->getQuantity(),
            ])
            ->toArray();

        $cartSplitting = $this->clientOrderService->getCartSplitting($entityManager, $client, $cart);
        $preparedCrates = $this->unserializePreparedCrates($entityManager, $crates);

        $comparisonResult = $this->comparePreparedCratesAndSplitting($preparedCrates, $cartSplitting);

        if($comparisonResult['success']) {
            $comparisonResult['entities'] = $preparedCrates;
        }

        return $comparisonResult;
    }

    /**
     * @param array $crates
     *      [
     *          'number' => string,
     *          'boxes' => [
     *              'selected' => string[]
     *          ][]
     *      ][]
     */
    private function unserializePreparedCrates(EntityManagerInterface $entityManager,
                                               array                  $crates): array {
        $boxRepository = $entityManager->getRepository(Box::class);

        $preparedCrates = [];

        foreach($crates as $serializedCrate) {
            $crateNumber = $serializedCrate['number'];
            $crate = $boxRepository->findOneBy(['number' => $crateNumber]);

            if($crate) {
                $cartSplittingPreparedLine = [];
                $cartSplittingPreparedLine['crate'] = $crate;
                $cartSplittingPreparedLine['boxes'] = [];
                if(!empty($serializedCrate['boxes'])) {
                    foreach($serializedCrate['boxes'] as $serializedBoxes) {
                        if(!empty($serializedBoxes['selected'])) {
                            foreach($serializedBoxes['selected'] as $selectedNumber) {
                                $box = $boxRepository->findOneBy(['number' => $selectedNumber]);
                                if($box) {
                                    $cartSplittingPreparedLine['boxes'][] = $box;
                                }
                            }
                        }
                    }
                }
                $preparedCrates[] = $cartSplittingPreparedLine;
            }
        }
        return $preparedCrates;
    }

    /**
     * @param array $preparedCrates
     *      [
     *          'crate' => Box,
     *          'boxes' => Box[]
     *      ][]
     * @param array $cartSplitting
     *      [
     * 'type' => string,
     * 'boxes' => [
     * 'type' => string,
     * 'quantity' => number
     * ][]
     * ][]
     */
    private function comparePreparedCratesAndSplitting(array $preparedCrates,
                                                       array $cartSplitting): array {
        $result = [];
        if(count($preparedCrates) !== count($cartSplitting)) {
            $result['error'] = 'Le nombre de caisse ne correspond pas à celui attendu';
        } else {
            try {
                /** @var Box $preparedCrate */
                foreach($preparedCrates as $preparedCrate) {
                    if($preparedCrate->isBox()
                        || $preparedCrate->getState() !== BoxStateService::STATE_BOX_AVAILABLE) {
                        throw new Exception('Une des caisses préparée est invalide', 1000);
                    }

                    $respectiveSplittingLine = null;
                    $respectiveSplittingIndex = null;
                    foreach($cartSplitting as $splittingIndex => $splittingLine) {
                        if($preparedCrate->getType()
                            && $splittingLine['type'] === $preparedCrate->getType()->getName()) {
                            $respectiveSplittingLine = $splittingLine;
                            $respectiveSplittingIndex = $splittingIndex;
                            break;
                        }
                    }
                    if(!$respectiveSplittingLine) {
                        throw new Exception('Une des caisses préparée est invalide', 1000);
                    } else {
                        $groupedBoxes = [];

                        // we group boxes by type name and we check box validity
                        /** @var Box $box */
                        foreach($preparedCrate['boxes'] as $box) {
                            $key = $box->getType() ? $box->getType()->getName() : 0;
                            if(!isset($groupedBoxes[$key])) {
                                $groupedBoxes[$key] = [];
                            }
                            $groupedBoxes[$key][] = $box;

                            if(!$box->isBox()
                                || $box->getState() !== BoxStateService::STATE_BOX_AVAILABLE
                                || !$box->getQuality()
                                || !$box->getQuality()->isClean()) {
                                throw new Exception('Une des Box préparée est invalide', 1000);
                            }
                        }

                        $groupedSplittingBoxes = Stream::from($respectiveSplittingLine['boxes'])
                            ->reduce(function(array $acc, array $splittingBoxes) {
                                $type = $splittingBoxes['type'];
                                $quantity = $splittingBoxes['quantity'];
                                if(!isset($acc[$type])) {
                                    $acc[$type] = 0;
                                }
                                $acc[$type] += $quantity;
                            }, []);

                        foreach($groupedBoxes as $typeName => $boxes) {
                            if(!isset($groupedSplittingBoxes[$typeName])
                                || $groupedSplittingBoxes[$typeName] !== count($boxes)) {
                                throw new Exception("Le contenu d'une caisse ne correspond pas à celui attendu", 1000);
                            }
                            unset($groupedSplittingBoxes[$typeName]);
                        }

                        if(!empty($groupedSplittingBoxes)) {
                            throw new Exception("Le contenu d'une caisse ne correspond pas à celui attendu", 1000);
                        }

                        array_splice($cartSplitting, $respectiveSplittingIndex, 1);
                    }
                }
            } catch(Throwable $throwable) {
                // check if it is our exception
                if($throwable->getFile() === __FILE__
                    && $throwable->getCode() === 1000) {
                    $result['error'] = $throwable->getMessage();
                }
            }
        }

        $result['success'] = empty($result['error']);

        return $result;
    }

}
