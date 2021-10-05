<?php declare(strict_types=1);

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

class ResponseFactory
{
    public function createResponse(ApiProblem $apiProblem) : JsonResponse
    {
        $data = $apiProblem->toArray();
        // making type a URL, to a temporarily fake page
        if ($data['type'] !== 'about:blank') {
            $data['type'] = "https://localhost:8000/docs/errors#{$data['type']}";
        }

        $response = new JsonResponse(
            $data,
            $apiProblem->getStatusCode()
        );
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
