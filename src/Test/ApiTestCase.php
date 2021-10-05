<?php declare(strict_types=1);

namespace App\Test;

use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

class ApiTestCase extends KernelTestCase
{
    private static $staticClient;
    private static $history = [];

    /** @var Client  */
    protected $client;
    /** @var ConsoleOutput */
    private $output;
    /** @var FormatterHelper */
    private $formatterHelper;
    /** @var ResponseAsserter */
    private $responseAsserter;
    /** @var ORMExecutor */
    private $fixtureExecutor;
    /** @var ContainerAwareLoader */
    private $fixtureLoader;

    public static function setUpBeforeClass(): void
    {
        // ensure a fresh cache when debug mode is disabled
        (new Filesystem())->remove(__DIR__.'/../var/cache/test');

        $handler = HandlerStack::create();
        $handler->push(Middleware::history(self::$history));
        $baseUrl = getenv('TEST_BASE_URL');

        self::$staticClient = new Client([
            'base_uri' => (string)$baseUrl,
            'http_errors' => false,
            'verify' => false,
            'handler' => $handler
        ]);

    }

    public function setUp(): void
    {
        self::bootKernel(['debug' => false,]);
        $this->client = self::$staticClient;
        // reset the history
        self::$history = [];
//        $this->purgeDatabase();
    }

    protected function tearDown(): void
    {
        // purposefully overriding so Symfony's kernel isn't shutdown
    }


    protected function onNotSuccessfulTest(Throwable $t): void
    {
        if ($lastResponse = $this->getLastResponse()) {
            $this->printDebug('');
            $this->printDebug('<error>Failure!</error> when making the following request:');
            $this->printLastRequestUrl();
            $this->printDebug('');

            $this->debugResponse($lastResponse);
        }
        parent::onNotSuccessfulTest($t);
    }

    protected function addFixture(FixtureInterface $fixture): void
    {
        $this->getFixtureLoader()->addFixture($fixture);
    }

    protected function executeFixtures(): void
    {
        $this->getFixtureExecutor()->execute($this->getFixtureLoader()->getFixtures());
    }

    private function getFixtureExecutor(): ORMExecutor
    {
        if (!$this->fixtureExecutor) {
            $this->fixtureExecutor = new ORMExecutor($this->getEntityManager(), new ORMPurger($this->getEntityManager()));
        }
        return $this->fixtureExecutor;
    }

    private function getFixtureLoader(): ContainerAwareLoader
    {
        if (!$this->fixtureLoader) {
            $this->fixtureLoader = new ContainerAwareLoader(self::$kernel->getContainer());
        }
        return $this->fixtureLoader;
    }

    private function purgeDatabase(): void
    {
        $purger = new ORMPurger($this->getEntityManager());
        $purger->purge();
    }

    protected function printLastRequestUrl(): void
    {
        $lastRequest = $this->getLastRequest();

        if ($lastRequest) {
            $this->printDebug(sprintf('<comment>%s</comment>: <info>%s</info>', $lastRequest->getMethod(), $lastRequest->getUri()));
        } else {
            $this->printDebug('No request was made.');
        }
    }

    protected function debugResponse(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $this->printDebug(sprintf('%s: %s', $name, implode(', ', $values)));
        }
        $body = (string) $response->getBody();

        $contentType = $response->getHeader('Content-Type');
        $contentType = $contentType[0];
        if ($contentType == 'application/json' || str_contains($contentType, '+json')) {
            $data = json_decode($body);
            if ($data === null) {
                // invalid JSON!
                $this->printDebug($body);
            } else {
                // valid JSON, print it pretty
                $this->printDebug(json_encode($data, JSON_PRETTY_PRINT));
            }
        } else {
            // the response is HTML - see if we should print all of it or some of it
            $isValidHtml = str_contains($body, '</body>');

            if ($isValidHtml) {
                $this->printDebug('');
                $crawler = new Crawler($body);

                // very specific to Symfony's error page
                $isError = $crawler->filter('#traces-0')->count() > 0
                    || str_contains($body, 'looks like something went wrong');
                if ($isError) {
                    $this->printDebug('There was an Error!!!!');
                    $this->printDebug('');
                } else {
                    $this->printDebug('HTML Summary (h1 and h2):');
                }

                // finds the h1 and h2 tags and prints them only
                foreach ($crawler->filter('h1, h2')->extract(array('_text')) as $header) {
                    // avoid these meaningless headers
                    if (str_contains($header, 'Stack Trace')) {
                        continue;
                    }
                    if (str_contains($header, 'Logs')) {
                        continue;
                    }

                    // remove line breaks so the message looks nice
                    $header = str_replace("\n", ' ', trim($header));
                    // trim any excess whitespace "foo   bar" => "foo bar"
                    $header = preg_replace('/(\s)+/', ' ', $header);
                    $isError ? $this->printErrorBlock($header) : $this->printDebug($header);
                }

                /*
                 * When using the test environment, the profiler is not active
                 * for performance. To help debug, turn it on temporarily in
                 * the config_test.yml file (framework.profiler.collect)
                 */
                $profilerUrl = $response->getHeader('X-Debug-Token-Link');
                if ($profilerUrl) {
                    $fullProfilerUrl = $response->getHeader('Host')[0].$profilerUrl[0];
                    $this->printDebug('');
                    $this->printDebug(sprintf(
                        'Profiler URL: <comment>%s</comment>',
                        $fullProfilerUrl
                    ));
                }

                // an extra line for spacing
                $this->printDebug('');
            } else {
                $this->printDebug($body);
            }
        }
    }

    protected function printDebug(string $string): void
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }
        $this->output->writeln($string);
    }

    protected function printErrorBlock(string $string): void
    {
        if ($this->formatterHelper === null) {
            $this->formatterHelper = new FormatterHelper();
        }
        $output = $this->formatterHelper->formatBlock($string, 'bg=red;fg=white', true);
        $this->printDebug($output);
    }

    private function getLastRequest(): ?RequestInterface
    {
        if (!self::$history || empty(self::$history)) return null;

        $history = self::$history;
        $last = array_pop($history);
        return $last['request'];
    }

    private function getLastResponse(): ?ResponseInterface
    {
        if (!self::$history || empty(self::$history)) return null;

        $history = self::$history;
        $last = array_pop($history);
        return $last['response'];
    }

    protected function createUser($email, $plainPassword = 'foo'): UserInterface
    {
        $user = new User();
        $user->setEmail($email);
        $password = $this->getService('security.password_hasher')
            ->hashPassword($user, $plainPassword);
        $user->setPassword($password);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user;
    }

    protected function asserter(): ResponseAsserter
    {
        if ($this->responseAsserter === null) {
            $this->responseAsserter = new ResponseAsserter();
        }
        return $this->responseAsserter;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getService('doctrine.orm.entity_manager');
    }

    protected function getService(string $id): ?object
    {
        return static::getContainer()->get($id);
    }
}
