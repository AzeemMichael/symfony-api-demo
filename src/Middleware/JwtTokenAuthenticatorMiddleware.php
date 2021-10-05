<?php declare(strict_types=1);

namespace App\Middleware;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class JwtTokenAuthenticatorMiddleware implements MiddlewareInterface
{

    private JWTEncoderInterface $jwtEncoder;
    private UserRepository $userRepository;

    public function __construct(JWTEncoderInterface $jwtEncoder, UserRepository $userRepository)
    {
        $this->jwtEncoder = $jwtEncoder;
        $this->userRepository = $userRepository;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->hasHeader('Authorization') &&
            str_starts_with($request->getHeader('Authorization')[0], 'Bearer ')) {
            $token = $this->extractToken($request);
            if ($token) {
                $user = $this->getUser($token);
                if ($user) {
                    return $handler->handle($request);
                }
            }
        }

        return new Response( 401, ['Content-Type' => 'application/json'], json_encode([
            'detail' => 'Missing credentials',
            'status' => 401,
            'type' => 'about:blank',
            'title' => 'Unauthorized',
        ]));
    }

    private function extractToken(ServerRequestInterface $request)
    {
        if (!$request->hasHeader('Authorization')) return false;

        $authorizationHeader = $request->getHeader('Authorization')[0];

        $headerParts = explode(' ', $authorizationHeader);

        if (!(2 === count($headerParts) && 0 === strcasecmp($headerParts[0], 'Bearer'))) return false;

        return $headerParts[1];
    }

    public function getUser(string $credentials): ?User
    {
        try {
            $data = $this->jwtEncoder->decode($credentials);
        } catch (JWTDecodeFailureException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid token');
        }

        if ($data === false) {
            throw new CustomUserMessageAuthenticationException('Invalid token');
        }

        $email = $data['email'];

        return $this->userRepository->findOneBy(['email' => $email]);
    }
}
