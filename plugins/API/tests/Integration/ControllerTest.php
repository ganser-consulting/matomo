<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\API\tests\Integration;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Plugins\API\Controller;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group API
 * @group Plugins
 */
class ControllerTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Common::$headersSentInTests = [];
    }

    public function tearDown(): void
    {
        $_GET = [];
        Request::setIsRootRequestApiRequest(null);

        parent::tearDown();
    }

    public function testIndexSendsDataResponseHeadersForAnApiRequest()
    {
        $_GET = [
            'module' => 'API',
            'method' => 'API.getBulkRequest',
            'format' => 'json',
            'urls' => [],
        ];
        Request::setIsRootRequestApiRequest('API.getBulkRequest');

        (new Controller())->index();

        $this->assertSame('nosniff', trim(Common::$headersSentInTests['X-Content-Type-Options'] ?? ''));
        $this->assertSame('deny', trim(Common::$headersSentInTests['X-Frame-Options'] ?? ''));
        $this->assertSame('no-referrer', trim(Common::$headersSentInTests['Referrer-Policy'] ?? ''));
        $policy = trim(Common::$headersSentInTests['Content-Security-Policy'] ?? '');
        $this->assertStringStartsWith("default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:", $policy);
        $this->assertStringEndsWith("base-uri 'none'; form-action 'none'; frame-ancestors 'none';", $policy);
        $this->assertSame('application/json; charset=utf-8', trim(Common::$headersSentInTests['Content-Type'] ?? ''));
    }

    /**
     * The module also serves HTML pages, and appending a method parameter to one of their URLs must
     * not make its response be treated as API output.
     */
    public function testSendsNoDataResponseHeadersWhileAnHtmlActionOfTheApiModuleIsDispatched()
    {
        $_GET = [
            'module' => 'API',
            'action' => 'listAllAPI',
            'method' => 'API.getBulkRequest',
            'format' => 'json',
            'urls' => [],
        ];
        Request::setIsRootRequestApiRequest('API.getBulkRequest');

        // the API call such a page makes while it is being rendered
        (new Controller())->index();

        $this->assertArrayNotHasKey('Content-Security-Policy', Common::$headersSentInTests);
        $this->assertArrayNotHasKey('X-Frame-Options', Common::$headersSentInTests);
    }

    public function testIndexSendsDataResponseHeadersForAnApiRequestWithAnUnparseableMethod()
    {
        $_GET = [
            'module' => 'API',
            'method' => 'no-such-method',
            'format' => 'json',
        ];

        (new Controller())->index();

        $this->assertSame('nosniff', trim(Common::$headersSentInTests['X-Content-Type-Options'] ?? ''));
        $this->assertArrayHasKey('Content-Security-Policy', Common::$headersSentInTests);
    }

    public function testIndexSendsNoDataResponseHeadersWhenTheRequestIsNotAnApiRequest()
    {
        $_GET = [
            'module' => 'CoreHome',
            'method' => 'API.getBulkRequest',
            'format' => 'json',
            'urls' => [],
        ];

        (new Controller())->index();

        $this->assertArrayNotHasKey('Content-Security-Policy', Common::$headersSentInTests);
        $this->assertArrayNotHasKey('X-Content-Type-Options', Common::$headersSentInTests);
    }

    /**
     * @dataProvider getFormatSpellings
     */
    public function testIndexServesArrayOutputAsPlainText(string $format)
    {
        $_GET = [
            'module' => 'API',
            'method' => 'API.getBulkRequest',
            'format' => $format,
            'serialize' => '0',
            'urls' => [],
        ];

        $response = (new Controller())->index();

        self::assertSame(var_export([], true), $response);
        self::assertSame(
            'text/plain; charset=utf-8',
            trim(Common::$headersSentInTests['Content-Type'] ?? '')
        );
    }

    public function getFormatSpellings(): array
    {
        return [
            ['original'],
            ['ORIGINAL'],
            ['Original'],
        ];
    }
}
