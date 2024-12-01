<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestLoggerSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $requestLogger;
    private ?SecurityBundleSecurity $security;

    // Tableau pour stocker les données à logger
    private array $logData = [];

    public function __construct(LoggerInterface $requestLogger, SecurityBundleSecurity $security = null)
    {
        $this->requestLogger = $requestLogger;
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Récupération des données de la requête
        $this->logData['IP'] = $request->getClientIp();
        $this->logData['Method'] = $request->getMethod();
        $this->logData['URI'] = $request->getRequestUri();
        $this->logData['Query Params'] = $request->query->all();
        $requestBody = json_decode($request->getContent(), true);
        if (!empty($requestBody)) {
            foreach($requestBody as $key => $value) {
                if (stripos($key, "password") !== false) {
                    $requestBody[$key] = "***hidden***";
                }
            }
        }
        $this->logData['Request Body'] = $requestBody;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (is_array($controller)) {
            $controllerName = get_class($controller[0]);
            $actionName = $controller[1];
        } else {
            $controllerName = get_class($controller);
            $actionName = '__invoke';
        }

        $user = $this->security?->getUser();
        $userIdentifier = $user ? $user->getUserIdentifier() : 'anonymous';

        // Ajout des données du contrôleur
        $this->logData['Controller'] = $controllerName;
        $this->logData['Action'] = $actionName;
        $this->logData['User'] = $userIdentifier;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // Ajout des données de la réponse
        $this->logData['Status Code'] = $response->getStatusCode();

        // Enregistrement du log complet
        $this->requestLogger->info('Full Request Log', $this->logData);
    }
}
