<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

class EmailVerificationSubscriber implements EventSubscriberInterface
{
    private Security $security;
    private RouterInterface $router;
    private array $publicRoutes = [
        'app_verification_notice',
        'app_verify_email',
        'app_register',
        'app_login',
        'app_logout',
        'app_forgot_password_request',
        'app_reset_password',
    ];

    public function __construct(Security $security, RouterInterface $router)
    {
        $this->security = $security;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        $route = $event->getRequest()->attributes->get('_route');

        // Skip if no user is logged in or if it's a public route
        if (!$user instanceof User || in_array($route, $this->publicRoutes, true)) {
            return;
        }

        // Skip if the user is an admin
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Redirect to verification notice if email is not verified
        if (!$user->isVerified()) {
            $response = new RedirectResponse($this->router->generate('app_verification_notice'));
            $event->setResponse($response);
        }
    }
}
