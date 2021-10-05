<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Test\ApiTestCase;

class TokenControllerTest extends ApiTestCase
{
    public function testPOSTCreateToken(): void
    {
        $email = 'admin@example.com';
        $plainPassword = 'secret';
//        $this->createUser($email, $plainPassword);

        $response = $this->client->post('/tokens', [
            'auth' => [$email, $plainPassword]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyExists(
            $response,
            'token'
        );
    }

    public function testPOSTTokenInvalidCredentials(): void
    {
        $response = $this->client->post('/tokens', [
            'auth' => ['admin@example.com', 'donoevil'] // sending in bad credentials
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
