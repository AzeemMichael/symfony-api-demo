<?php declare(strict_types=1);

namespace App\Tests\Controller;

use App\Test\ApiTestCase;
use Psr\Http\Message\ResponseInterface;

class WidgetControllerTest extends ApiTestCase
{
    protected $jwtToken;

    public function setUp(): void
    {
        parent::setUp();
        $response = $this->client->post('/tokens', [
            'auth' => ['admin@example.com', 'secret']
        ]);
        $this->jwtToken = json_decode($response->getBody()->getContents(), true)['token'];
    }

    public function testPOST(): void
    {
        $response = $this->createWidget([
            'name' => uniqid('foo_'),
            'description' => uniqid('bar_')
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
        $this->assertTrue($response->hasHeader('X-Day'));

        $finishedData = json_decode($response->getBody()->getContents(), true);

        $this->assertStringEndsWith(
            "/widgets/{$finishedData['id']}",
            $response->getHeader('Location')[0]?? null
        );
        $this->assertArrayHasKey('name', $finishedData);
        $this->assertArrayHasKey('description', $finishedData);
    }

    public function testGET(): void
    {
        $postResponse = $this->createWidget([
            'name' => uniqid('foo_'),
            'description' => uniqid('bar_')
        ]);
        $finishedData = json_decode($postResponse->getBody()->getContents(), true);

        $response = $this->client->get("/widgets/{$finishedData['id']}", [
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertiesExist($response, [
            'id', 'name', 'description'
        ]);
        $this->asserter()->assertResponsePropertyEquals(
            $response,
            'name',
            $finishedData['name']
        );
    }

    public function testGETCollection(): void
    {
        for ($i=0; $i<5; $i++) {
            $this->createWidget([
                'name' => uniqid('foo_'),
                'description' => uniqid('bar_')
            ]);
        }

        $response = $this->client->get('/widgets', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyIsArray($response, 'widgets');
    }

    public function testPUT(): void
    {
        $postResponse = $this->createWidget([
            'name' => uniqid('foo_'),
            'description' => uniqid('bar_')
        ]);
        $data = ['name' => 'put_'.uniqid()];

        $response = $this->client->put($postResponse->getHeaderLine('Location'), [
            'body' => json_encode($data),
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyEquals($response, 'name', $data['name']);
        $this->asserter()->assertResponsePropertyEquals($response, 'description', null);
    }

    public function testPATCH(): void
    {
        $postResponse = $this->createWidget([
            'name' => uniqid('foo_'),
            'description' => uniqid('bar_')
        ]);
        $data = ['name' => 'patch_'.uniqid()];

        $response = $this->client->patch($postResponse->getHeaderLine('Location'), [
            'body' => json_encode($data),
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyEquals($response, 'name', $data['name']);
        $this->asserter()->assertResponsePropertyEquals($response, 'description', json_decode($postResponse->getBody()->getContents(), true)['description']);
    }

    public function testDELETE(): void
    {
        $postResponse = $this->createWidget([
            'name' => uniqid('foo_'),
            'description' => uniqid('bar_')
        ]);
        $response = $this->client->delete($postResponse->getHeaderLine('Location'), [
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testValidationErrors(): void
    {
        $response = $this->createWidget([
            'description' => uniqid('bar_')
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        $this->asserter()->assertResponsePropertiesExist($response, [
            'type',
            'title',
            'errors',
        ]);
        $this->asserter()->assertResponsePropertyExists($response, 'errors.name');
        $this->asserter()->assertResponsePropertyEquals(
            $response,
            'errors.name[0]',
            'Name field should not be blank'
        );
        $this->asserter()->assertResponsePropertyDoesNotExist($response, 'errors.description');
        $this->assertEquals('application/problem+json', $response->getHeader('Content-Type')[0]);
    }

    public function testInvalidJson(): void
    {
        $invalidJson = <<<EOF
{
    "name": "foo
    "description":"bar"
}
EOF;
        $response = $this->client->post('/widgets', [
            'body' => $invalidJson,
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test404Exception(): void
    {
        $response = $this->client->get('/widgets/fake', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('Content-Type')[0]);
        $this->asserter()->assertResponsePropertyEquals(
            $response,
            'type',
            'about:blank'
        );
        $this->asserter()->assertResponsePropertyEquals(
            $response,
            'title',
            'Not Found'
        );
        $this->asserter()->assertResponsePropertyEquals(
            $response,
            'detail',
            'No widget found for id fake'
        );
    }

    private function createWidget($data): ResponseInterface
    {
        return $this->client->post('/widgets', [
            'body' => json_encode($data),
            'headers' => [
                'Authorization' => 'Bearer '.$this->jwtToken,
            ]
        ]);
    }
}
