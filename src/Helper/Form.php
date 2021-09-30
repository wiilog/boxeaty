<?php

namespace App\Helper;

use Symfony\Component\HttpFoundation\JsonResponse;

class Form {

    private array $errors = [];

    public static function create() {
        return new Form();
    }

    public function addError(string $field, ?string $message = null) {
        if(!$message) {
            $this->errors[] = [
                "global" => true,
                "message" => $field,
            ];
        } else {
            $this->errors[] = [
                "field" => $field,
                "message" => $message,
            ];
        }
    }

    public function isValid() {
        return empty($this->errors);
    }

    public function errors(?callable $default = null) {
        if($this->errors) {
            return new JsonResponse([
                "success" => false,
                "errors" => [
                    "fields" => $this->errors,
                ],
            ]);
        } else if($default) {
            return $default();
        } else {
            return new JsonResponse([
                "success" => true,
            ]);
        }
    }

}
