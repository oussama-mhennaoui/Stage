<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppAuthenticator extends AbstractLoginFormAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        // Get credentials from the request
        $credentials = [
            'email' => $request->request->get('email', ''),
            'password' => $request->request->get('password', ''),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $credentials['email']);

        // Create a new passport with the credentials
        return new Passport(
            new UserBadge($credentials['email']),
            new PasswordCredentials($credentials['password']),
            [
                new CsrfTokenBadge('authenticate', $credentials['csrf_token']),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Always clear the target path to prevent any redirect loops
        $session = $request->getSession();
        $this->removeTargetPath($session, $firewallName);
        
        // Get the user
        $user = $token->getUser();
        
        // Determine the target URL based on user role
        $targetUrl = $this->determineTargetUrl($user);
        
        // Create a response that will prevent any caching
        $response = new RedirectResponse($targetUrl);
        $response->setCache([
            'no_store' => true,
            'max_age' => 0
        ]);
        
        return $response;
    }
    
    private function determineTargetUrl($user): string
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true) || 
            in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return $this->urlGenerator->generate('app_admin');
        } 
        
        if (in_array('ROLE_ENTREPRISE', $user->getRoles(), true)) {
            return $this->urlGenerator->generate('app_offre_index');
        }
        
        // Default for ROLE_ETUDIANT and ROLE_DIPLOME
        return $this->urlGenerator->generate('app_home');
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('app_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
