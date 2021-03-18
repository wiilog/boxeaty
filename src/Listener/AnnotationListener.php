<?php

namespace App\Listener;

use App\Annotation\HasPermission;
use App\Service\RoleService;
use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Twig\Environment;

class AnnotationListener {

    /** @Required */
    public RoleService $roleService;

    /** @Required */
    public Environment $templating;

    public function onRequest(ControllerArgumentsEvent $event) {
        if (!$event->isMasterRequest() || !is_array($event->getController())) {
            return;
        }

        $reader = new AnnotationReader();
        [$controller, $method] = $event->getController();

        try {
            $class = new ReflectionClass($controller);
            $method = $class->getMethod($method);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Failed to read annotation");
        }

        $annotation = $reader->getMethodAnnotation($method, HasPermission::class);
        if ($annotation instanceof HasPermission) {
            $this->handleHasPermission($event, $annotation);
        }
    }

    private function handleHasPermission(ControllerArgumentsEvent $event, HasPermission $annotation) {
        if (!$this->roleService->hasPermission(...$annotation->value)) {
            $event->setController(function() use ($annotation) {
                if ($annotation->mode == HasPermission::IN_JSON) {
                    return new JsonResponse([
                        "success" => false,
                        "message" => "Vous n'avez pas les permissions nécessaires",
                    ]);
                } else if ($annotation->mode == HasPermission::IN_RENDER) {
                    return new Response($this->templating->render("security/access_denied.html.twig"));
                } else {
                    throw new \RuntimeException("Unknown mode $annotation->mode");
                }
            });
        }
    }

}
