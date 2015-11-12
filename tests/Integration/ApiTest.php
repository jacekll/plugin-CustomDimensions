<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CustomDimensions\tests\Integration;

use Piwik\Plugins\CustomDimensions\API;
use Piwik\Plugins\CustomDimensions\CustomDimensions;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Exception;

/**
 * @group CustomDimensions
 * @group ApiTest
 * @group Plugins
 */
class ApiTest extends IntegrationTestCase
{
    /**
     * @var API
     */
    private $api;

    public function setUp()
    {
        parent::setUp();

        $this->api = API::getInstance();

        Fixture::createSuperUser();
        if (!Fixture::siteCreated(1)) {
            Fixture::createWebsite('2012-01-01 00:00:00');
        }

        $this->setSuperUser();
    }

    /**
     * @dataProvider getInvalidConfigForNewDimensions
     */
    public function test_configureNewDimension_shouldFailWhenThereIsAnError($dimension)
    {
        try {
            $this->api->configureNewCustomDimension($idSite = 1, $dimension['name'], $dimension['scope'], $dimension['active'], $dimension['extractions']);
        } catch (Exception $e) {
            $this->assertContains($dimension['message'], $e->getMessage());
            return;
        }

        $this->fail('An expected exception has not been thrown');
    }

    public function getInvalidConfigForNewDimensions()
    {
        return array(
            array(array('message' => 'CustomDimensions_NameAllowedCharacters',   'name' => 'Inval/\\nam&<b>e</b>', 'scope' => CustomDimensions::SCOPE_VISIT, 'active' => '1', 'extractions' => array())),
            array(array('message' => "Invalid value 'anyScOPe' for 'scope'",     'name' => 'Valid Name äöü',       'scope' => 'anyScOPe',                    'active' => '1', 'extractions' => array())),
            array(array('message' => "Invalid value '2' for 'active' specified", 'name' => 'Valid Name äöü',       'scope' => CustomDimensions::SCOPE_VISIT, 'active' => '2', 'extractions' => array())),
            array(array('message' => 'Extractions has to be an array',           'name' => 'Valid Name äöü',       'scope' => CustomDimensions::SCOPE_VISIT, 'active' => '1', 'extractions' => 5)),
        );
    }

    public function test_configureNewDimension_shouldReturnCreatedIdOnSuccess()
    {
        $id = $this->api->configureNewCustomDimension($idSite = 1, 'Valid Name äöü', CustomDimensions::SCOPE_VISIT, '1', array(array('dimension' => 'urlparam', 'pattern' => 'test')));

        $this->assertSame(1, $id);

        // verify created
        $dimensions = $this->api->getConfiguredCustomDimensions(1);

        $expectedDimension = array(
            'idcustomdimension' => '1',
            'idsite' => '1',
            'name' => 'Valid Name äöü',
            'index' => '1',
            'scope' => 'visit',
            'active' => true,
            'extractions' => array(
                array ('dimension' => 'urlparam', 'pattern' => 'test'))
            );
        $this->assertSame(array($expectedDimension), $dimensions);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasAdminAccess
     */
    public function test_configureNewDimension_shouldFailWhenNotHavingAdminPermissions()
    {
        $this->setUser();
        $this->api->configureNewCustomDimension($idSite = 1, 'Valid Name äöü', CustomDimensions::SCOPE_VISIT, '1', array(array('dimension' => 'urlparam', 'pattern' => 'test')));
    }

    /**
     * @dataProvider getInvalidConfigForExistingDimensions
     */
    public function test_configureExistingCustomDimension_shouldFailWhenThereIsAnError($dimension)
    {
        try {
            $this->test_configureNewDimension_shouldReturnCreatedIdOnSuccess();
            $this->api->configureExistingCustomDimension($dimension['id'], $idSite = 1, $dimension['name'], $dimension['active'], $dimension['extractions']);
        } catch (Exception $e) {
            $this->assertContains($dimension['message'], $e->getMessage());
            return;
        }

        $this->fail('An expected exception has not been thrown');
    }

    public function getInvalidConfigForExistingDimensions()
    {
        return array(
            array(array('message' => "CustomDimensions_ExceptionDimensionDoesNotExist", 'id' => '999', 'name' => 'Valid Name äöü', 'active' => '1', 'extractions' => array())),
            array(array('message' => 'CustomDimensions_NameAllowedCharacters',          'id' => '1',   'name' => 'Inval/\\nam&<b>e</b>', 'active' => '1', 'extractions' => array())),
            array(array('message' => "Invalid value '2' for 'active' specified",        'id' => '1',   'name' => 'Valid Name äöü', 'active' => '2', 'extractions' => array())),
            array(array('message' => 'Extractions has to be an array',                  'id' => '1',   'name' => 'Valid Name äöü', 'active' => '1', 'extractions' => 5)),
        );
    }

    public function test_configureExistingCustomDimension_shouldReturnNothingOnSuccess()
    {
        $this->test_configureNewDimension_shouldReturnCreatedIdOnSuccess();
        $return = $this->api->configureExistingCustomDimension($id = 1, $idSite = 1, 'New Valid Name äöü', '0', array(array('dimension' => 'urlparam', 'pattern' => 'newtest')));

        $this->assertNull($return);

        // verify updated
        $dimensions = $this->api->getConfiguredCustomDimensions(1);
        $this->assertCount(1, $dimensions);
        $this->assertSame('New Valid Name äöü', $dimensions[0]['name']);
        $this->assertFalse($dimensions[0]['active']);
        $this->assertSame('newtest', $dimensions[0]['extractions'][0]['pattern']);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasAdminAccess
     */
    public function test_configureExistingCustomDimension_shouldFailWhenNotHavingAdminPermissions()
    {
        $this->setUser();
        $this->api->configureExistingCustomDimension($id = 1, $idSite = 1, 'New Valid Name äöü', '0', array(array('dimension' => 'urlparam', 'pattern' => 'newtest')));
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasAdminAccess
     */
    public function test_getConfiguredCustomDimensions_shouldFailWhenNotHavingAdminPermissions()
    {
        $this->setUser();
        $this->api->getConfiguredCustomDimensions($idSite = 1);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasAdminAccess
     */
    public function test_getAvailableScopes_shouldFailWhenNotHavingAdminPermissions()
    {
        $this->setUser();
        $this->api->getAvailableScopes($idSite = 1);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasSomeAdminAccess
     */
    public function test_getAvailableExtractionDimensions_shouldFailWhenNotHavingAdminPermissions()
    {
        $this->setUser();
        $this->api->getAvailableExtractionDimensions();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage checkUserHasViewAccess
     */
    public function test_getCustomDimension_shouldFailWhenNotHavingViewPermissions()
    {
        $this->setAnonymousUser();
        $this->api->getCustomDimension($idDimension = 1, $idSite = 1, $period = 'day', $date = 'today');
    }

    public function provideContainerConfig()
    {
        return array(
            'Piwik\Access' => new FakeAccess()
        );
    }

    protected function setSuperUser()
    {
        FakeAccess::clearAccess(true);
    }

    protected function setUser()
    {
        FakeAccess::clearAccess(false);
        FakeAccess::$idSitesView = array(1);
        FakeAccess::$idSitesAdmin = array();
        FakeAccess::$identity = 'aUser';
    }

    protected function setAnonymousUser()
    {
        FakeAccess::clearAccess();
        FakeAccess::$identity = 'anonymous';
    }

}
