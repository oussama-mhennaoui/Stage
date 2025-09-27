<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

class EmailVerificationHandler implements AccessDeniedHandlerInterface
{
    private RouterInterface $router;
    private Security $security;

    public function __construct(RouterInterface $router, Security $security)
    {
        $this->router = $router;
        $this->security = $security;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?RedirectResponse
    {
        // Get the authenticated user
        $user = $this->security->getUser();
        
        // If there's a user and they're not verified, redirect to verification notice
        if ($user && method_exists($user, 'isVerified') && $user->isVerified() === false) {
            return new RedirectResponse($this->router->generate('app_verification_notice'));
        }

        return null;
    }
}
