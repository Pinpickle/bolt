<?php
namespace Bolt\Tests\Controller;

use Bolt\Configuration\ResourceManager;
use Bolt\Controllers\Backend;
use Bolt\Tests\BoltUnitTest;
use Bolt\Tests\Mocks\LoripsumMock;
use Bolt\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class to test correct operation of src/Controller/Backend.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 **/

class BackendTest extends BoltUnitTest
{
    public function testDashboard()
    {
        $this->resetDb();
        $app = $this->getApp();
        $this->addSomeContent();
        $twig = $this->getMockTwig();
        $phpunit = $this;
        $testHandler = function ($template, $context) use ($phpunit) {
            $phpunit->assertEquals('dashboard/dashboard.twig', $template);
            $phpunit->assertNotEmpty($context['context']);
            $phpunit->assertArrayHasKey('latest', $context['context']);
            $phpunit->assertArrayHasKey('suggestloripsum', $context['context']);

            return new Response();
        };

        $twig->expects($this->any())
            ->method('render')
            ->will($this->returnCallBack($testHandler));
        $this->allowLogin($app);
        $app['render'] = $twig;
        $request = Request::create('/bolt');
        $app->run($request);
    }

    public function testDbCheck()
    {
        $app = $this->getApp();
        $this->allowLogin($app);
        $check = $this->getMock('Bolt\Database\IntegrityChecker', array('checkTablesIntegrity'), array($app));
        $check->expects($this->atLeastOnce())
            ->method('checkTablesIntegrity')
            ->will($this->returnValue(array('message', 'hint')));

        $app['integritychecker'] = $check;
        $request = Request::create('/bolt/dbcheck');
        $this->checkTwigForTemplate($app, 'dbcheck/dbcheck.twig');

        $app->run($request);
    }

    public function testDbUpdate()
    {
        $app = $this->getApp();
        $this->allowLogin($app);
        $check = $this->getMock('Bolt\Database\IntegrityChecker', array('repairTables'), array($app));

        $check->expects($this->at(0))
            ->method('repairTables')
            ->will($this->returnValue(""));

        $check->expects($this->at(1))
            ->method('repairTables')
            ->will($this->returnValue("Testing"));

        $app['integritychecker'] = $check;
        ResourceManager::$theApp = $app;

        $request = Request::create('/bolt/dbupdate', 'POST', array('return' => 'edit'));
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/bolt/file/edit/files/app/config/contenttypes.yml', $response->getTargetUrl());
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));

        $request = Request::create('/bolt/dbupdate', 'POST', array('return' => 'edit'));
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/bolt/file/edit/files/app/config/contenttypes.yml', $response->getTargetUrl());
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));

        $request = Request::create('/bolt/dbupdate', "POST");
        $response = $app->handle($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/bolt/dbupdate_result?messages=null', $response->getTargetUrl());
    }

    public function testDbUpdateResult()
    {
        $app = $this->getApp();
        $this->allowLogin($app);

        $request = Request::create('/bolt/dbupdate_result');
        $this->checkTwigForTemplate($app, 'dbcheck/dbcheck.twig');

        $app->run($request);
    }

    public function testClearCache()
    {
        $app = $this->getApp();
        $this->allowLogin($app);
        $cache = $this->getMock('Bolt\Cache', array('clearCache'), array(__DIR__,$app));
        $cache->expects($this->at(0))
            ->method('clearCache')
            ->will($this->returnValue(array('successfiles' => '1.txt', 'failedfiles' => '2.txt')));

        $cache->expects($this->at(1))
            ->method('clearCache')
            ->will($this->returnValue(array('successfiles' => '1.txt')));

        $app['cache'] = $cache;
        $request = Request::create('/bolt/clearcache');
        $this->checkTwigForTemplate($app, 'clearcache/clearcache.twig');
        $response = $app->handle($request);

        $this->assertNotEmpty($app['session']->getFlashBag()->get('error'));

        $request = Request::create('/bolt/clearcache');
        $this->checkTwigForTemplate($app, 'clearcache/clearcache.twig');
        $response = $app->handle($request);
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));
    }

    public function testChangeLog()
    {
        $app = $this->getApp();
        $this->allowLogin($app);
        $log = $this->getMock('Bolt\Logger\Manager', array('clear', 'trim'), array($app));
        $log->expects($this->once())
            ->method('clear')
            ->will($this->returnValue(true));

        $log->expects($this->once())
            ->method('trim')
            ->will($this->returnValue(true));

        $app['logger.manager'] = $log;

        ResourceManager::$theApp = $app;

        $request = Request::create('/bolt/changelog', 'GET', array('action' => 'trim'));
        $response = $app->handle($request);
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));

        $request = Request::create('/bolt/changelog', 'GET', array('action' => 'clear'));
        $response = $app->handle($request);
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));

        $this->assertEquals('/bolt/changelog', $response->getTargetUrl());

        $request = Request::create('/bolt/changelog');
        $this->checkTwigForTemplate($app, 'activity/changelog.twig');
        $app->run($request);
    }

    public function testSystemLog()
    {
        $app = $this->getApp();
        $this->allowLogin($app);
        $log = $this->getMock('Bolt\Logger\Manager', array('clear', 'trim'), array($app));
        $log->expects($this->once())
            ->method('clear')
            ->will($this->returnValue(true));

        $log->expects($this->once())
            ->method('trim')
            ->will($this->returnValue(true));

        $app['logger.manager'] = $log;

        ResourceManager::$theApp = $app;

        $request = Request::create('/bolt/systemlog', 'GET', array('action' => 'trim'));
        $response = $app->handle($request);
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));

        $request = Request::create('/bolt/systemlog', 'GET', array('action' => 'clear'));
        $response = $app->handle($request);
        $this->assertNotEmpty($app['session']->getFlashBag()->get('success'));

        $this->assertEquals('/bolt/systemlog', $response->getTargetUrl());

        $request = Request::create('/bolt/systemlog');
        $this->checkTwigForTemplate($app, 'activity/systemlog.twig');
        $app->run($request);
    }


    public function testChangelogRecordAll()
    {
        $app = $this->getApp();
        $app['config']->set('general/changelog/enabled', true);
        $controller = new Backend();

        // First test tests without any changelogs available
        $app['request'] = $request = Request::create('/bolt/changelog/pages');
        $response = $controller->changelogRecordAll('pages', null, $app, $request);
        $context = $response->getContext();
        $this->assertEquals(0, count($context['context']['entries']));
        $this->assertNull($context['context']['content']);
        $this->assertEquals('Pages', $context['context']['title']);
        $this->assertEquals('pages', $context['context']['contenttype']['slug']);

        // Search for a specific record where the content object doesn't exist
        $app['request'] = $request = Request::create('/bolt/changelog/pages/1');
        $response = $controller->changelogRecordAll('pages', 200, $app, $request);
        $context = $response->getContext();
        $this->assertEquals("Page #200", $context['context']['title']);

        // This block generates a changelog on the page in question so we have something to test.
        $this->addSomeContent();
        $app['request'] = Request::create("/");
        $content = $app['storage']->getContent('pages/1');
        $content->setValues(array('status' => 'draft', 'ownerid' => 99));
        $app['storage']->saveContent($content, 'Test Suite Update');


        // Now handle all the other request variations
        $app['request'] = $request = Request::create('/bolt/changelog');
        $response = $controller->changelogRecordAll(null, null, $app, $request);
        $context = $response->getContext();
        $this->assertEquals('All content types', $context['context']['title']);
        $this->assertEquals(1, count($context['context']['entries']));
        $this->assertEquals(1, $context['context']['pagecount']);

        $app['request'] = $request = Request::create('/bolt/changelog/pages');
        $response = $controller->changelogRecordAll('pages', null, $app, $request);
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['title']);
        $this->assertEquals(1, count($context['context']['entries']));
        $this->assertEquals(1, $context['context']['pagecount']);

        $app['request'] = $request = Request::create('/bolt/changelog/pages/1');
        $response = $controller->changelogRecordAll('pages', '1', $app, $request);
        $context = $response->getContext();
        $this->assertEquals($content['title'], $context['context']['title']);
        $this->assertEquals(1, count($context['context']['entries']));
        $this->assertEquals(1, $context['context']['pagecount']);

        // Test pagination
        $app['request'] = $request = Request::create('/bolt/changelog/pages', 'GET', array('page'=>'all'));
        $response = $controller->changelogRecordAll('pages', null, $app, $request);
        $context = $response->getContext();
        $this->assertNull($context['context']['currentpage']);
        $this->assertNull($context['context']['pagecount']);

        $app['request'] = $request = Request::create('/bolt/changelog/pages', 'GET', array('page'=>'1'));
        $response = $controller->changelogRecordAll('pages', null, $app, $request);
        $context = $response->getContext();
        $this->assertEquals(1, $context['context']['currentpage']);

        // Finally we delete the original content record, but make sure the logs still show
        $originalTitle = $content['title'];
        $app['storage']->deleteContent('pages', 1);
        $app['request'] = $request = Request::create('/bolt/changelog/pages/1');
        $response = $controller->changelogRecordAll('pages', '1', $app, $request);
        $context = $response->getContext();
        $this->assertEquals($originalTitle, $context['context']['title']);
        // Note the delete generates an extra log, hence the extra count
        $this->assertEquals(2, count($context['context']['entries']));
    }

    public function testChangelogRecordSingle()
    {
        $app = $this->getApp();
        $app['config']->set('general/changelog/enabled', true);
        $controller = new Backend();

        $app['request'] = $request = Request::create('/bolt/changelog/pages/1/1');
        $response = $controller->changelogRecordSingle('pages', 1, 1, $app, $request);
        $context = $response->getContext();
        $this->assertInstanceOf('Bolt\Logger\ChangeLogItem', $context['context']['entry']);

        // Test non-existing entry
        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\HttpException', 'exist');
        $app['request'] = $request = Request::create('/bolt/changelog/pages/1/100');
        $response = $controller->changelogRecordSingle('pages', 1, 100, $app, $request);
        $context = $response->getContext();
    }

    public function testOmnisearch()
    {
        $app = $this->getApp();
        $this->allowLogin($app);

        $request = Request::create('/bolt/omnisearch', 'GET', array('q' => 'test'));
        $this->checkTwigForTemplate($app, 'omnisearch/omnisearch.twig');

        $app->run($request);
    }

    public function testPrefill()
    {
        $app = $this->getApp();
        $controller = new Backend();

        $app['request'] =  $request = Request::create('/bolt/prefill');
        $response = $controller->prefill($app, $request);
        $context = $response->getContext();
        $this->assertEquals(3, count($context['context']['contenttypes']));
        $this->assertInstanceOf('Symfony\Component\Form\FormView', $context['context']['form']);

        // Test the post
        $app['request'] = $request = Request::create('/bolt/prefill', 'POST', array('contenttypes'=>'pages'));
        $response = $controller->prefill($app, $request);
        $this->assertEquals('/bolt/prefill', $response->getTargetUrl());

        // Test for the Exception if connection fails to the prefill service
        $store = $this->getMock('Bolt\Storage', array('preFill'), array($app));
        $store->expects($this->any())
            ->method('preFill')
            ->will($this->returnCallback(function(){
                throw new \Guzzle\Http\Exception\RequestException();
            }));
        $app['storage'] = $store;

        $logger = $this->getMock('Monolog\Logger', array('error'), array('test'));
        $logger->expects($this->once())
            ->method('error')
            ->with("Timeout attempting to the 'Lorem Ipsum' generator. Unable to add dummy content.");
        $app['logger.system'] = $logger;

        $app['request'] = $request = Request::create('/bolt/prefill', 'POST', array('contenttypes'=>'pages'));
        $response = $controller->prefill($app, $request);

    }

    public function testOverview()
    {
        $app = $this->getApp();
        $controller = new Backend();

        $app['request'] = $request = Request::create('/bolt/overview/pages');
        $response = $controller->overview($app, 'pages');
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['contenttype']['name']);
        $this->assertGreaterThan(1, count($context['context']['multiplecontent']));

        // Test the the default records per page can be set
        $app['request'] = $request = Request::create('/bolt/overview/showcases');
        $response = $controller->overview($app, 'showcases');

        // Test redirect when user isn't allowed.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($app));
        $users->expects($this->once())
            ->method('isAllowed')
            ->will($this->returnValue(false));
        $app['users'] = $users;

        $app['request'] = $request = Request::create('/bolt/overview/pages');
        $response = $controller->overview($app, 'pages');
        $this->assertEquals('/bolt', $response->getTargetUrl());

    }

    public function testRelatedTo()
    {
        $app = $this->getApp();
        $controller = new Backend();

        $app['request'] = $request = Request::create('/bolt/relatedto/showcases/1');
        $response = $controller->relatedTo('showcases', 1, $app, $request);
        $context = $response->getContext();
        $this->assertEquals(1, $context['context']['id']);
        $this->assertEquals('Showcase', $context['context']['name']);
        $this->assertEquals('Showcases', $context['context']['contenttype']['name']);
        $this->assertEquals(2, count($context['context']['relations']));
        // By default we show the first one
        $this->assertEquals('Entries', $context['context']['show_contenttype']['name']);

        // Now we specify we want to see pages
        $app['request'] = $request = Request::create('/bolt/relatedto/showcases/1', 'GET', array('show'=>'pages'));
        $response = $controller->relatedTo('showcases', 1, $app, $request);
        $context = $response->getContext();
        $this->assertEquals('Pages', $context['context']['show_contenttype']['name']);


        // Try a request where there are no relations
        $app['request'] = $request = Request::create('/bolt/relatedto/pages/1');
        $response = $controller->relatedTo('pages', 1, $app, $request);
        $context = $response->getContext();
        $this->assertNull($context['context']['relations']);

        // Test redirect when user isn't allowed.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($app));
        $users->expects($this->once())
            ->method('isAllowed')
            ->will($this->returnValue(false));
        $app['users'] = $users;

        $app['request'] = $request = Request::create('/bolt/relatedto/showcases/1');
        $response = $controller->relatedTo('showcases', 1, $app, $request);
        $this->assertEquals('/bolt', $response->getTargetUrl());
    }

    public function testEditContentGet()
    {
        $app = $this->getApp();
        $controller = new Backend();

        // First test will fail permission so we check we are kicked back to the dashboard
        $app['request'] = $request = Request::create('/bolt/editcontent/pages/4');
        $response = $controller->editContent('pages', 4, $app, $request);
        $this->assertEquals("/bolt", $response->getTargetUrl());

        // Since we're the test user we won't automatically have permission to edit.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($app));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $app['users'] = $users;

        $app['request'] = $request = Request::create('/bolt/editcontent/pages/4');
        $response = $controller->editContent('pages', 4, $app, $request);
        $context= $response->getContext();
        $this->assertEquals('Pages', $context['context']['contenttype']['name']);
        $this->assertInstanceOf('Bolt\Content', $context['context']['content']);

        // Test creation
        $app['request'] = $request = Request::create('/bolt/editcontent/pages');
        $response = $controller->editContent('pages', null, $app, $request);
        $context= $response->getContext();
        $this->assertEquals('Pages', $context['context']['contenttype']['name']);
        $this->assertInstanceOf('Bolt\Content', $context['context']['content']);
        $this->assertNull($context['context']['content']->id);

        // Test that non-existent throws a redirect
        $app['request'] = $request = Request::create('/bolt/editcontent/pages/310');
        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\HttpException', 'not-existing');
        $response = $controller->editContent('pages', 310, $app, $request);

    }

    public function testEditContentDuplicate()
    {
        $app = $this->getApp();
        $controller = new Backend();
        // Since we're the test user we won't automatically have permission to edit.
        $users = $this->getMock('Bolt\Users', array('isAllowed'), array($app));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $app['users'] = $users;

        $app['request'] = $request = Request::create('/bolt/editcontent/pages/4', 'GET', array('duplicate'=>true));
        $original = $app['storage']->getContent('pages/4');
        $response = $controller->editContent('pages', 4, $app, $request);
        $context = $response->getContext();

        // Check that correct fields are equal in new object
        $new = $context['context']['content'];
        $this->assertEquals($new['body'], $original['body']);
        $this->assertEquals($new['title'], $original['title']);
        $this->assertEquals($new['teaser'], $original['teaser']);

        // Check that some have been cleared.
        $this->assertEquals('', $new['id']);
        $this->assertEquals('', $new['slug']);
        $this->assertEquals('', $new['ownerid']);

    }

    public function testEditContentCSRF()
    {
        $app = $this->getApp();
        $controller = new Backend();
        $users = $this->getMock('Bolt\Users', array('isAllowed', 'checkAntiCSRFToken'), array($app));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $users->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(false));

        $app['users'] = $users;


        $app['request'] = $request = Request::create('/bolt/editcontent/showcases/3', 'POST');
        $this->setExpectedException('Symfony\Component\HttpKernel\Exception\HttpException', 'Something went wrong');
        $response = $controller->editContent('showcases', 3, $app, $request);
    }

    public function testEditContentPermissions()
    {
        $app = $this->getApp();

        $users = $this->getMock('Bolt\Users', array('isAllowed', 'checkAntiCSRFToken'), array($app));
        $users->expects($this->at(0))
            ->method('isAllowed')
            ->will($this->returnValue(true));

        $users->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));

        $app['users'] = $users;

        // We should get kicked here because we dont have permissions to edit this
        $controller = new Backend();
        $app['request'] = $request = Request::create('/bolt/editcontent/showcases/3', 'POST');
        $response = $controller->editContent('showcases', 3, $app, $request);
        $this->assertEquals("/bolt", $response->getTargetUrl());
    }

    public function testEditContentPost()
    {
        $app = $this->getApp();
        $controller = new Backend();

        $users = $this->getMock('Bolt\Users', array('isAllowed', 'checkAntiCSRFToken'), array($app));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $users->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));

        $app['users'] = $users;

        $app['request'] = $request = Request::create('/bolt/editcontent/showcases/3', 'POST', array('floatfield'=>1.2));
        $original = $app['storage']->getContent('showcases/3');
        $response = $controller->editContent('showcases', 3, $app, $request);
        $this->assertEquals('/bolt/overview/showcases', $response->getTargetUrl());

    }

    public function testEditContentPostAjax()
    {
        $app = $this->getApp();
        $controller = new Backend();

        // Since we're the test user we won't automatically have permission to edit.
        $users = $this->getMock('Bolt\Users', array('isAllowed', 'checkAntiCSRFToken'), array($app));
        $users->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnValue(true));
        $users->expects($this->any())
            ->method('checkAntiCSRFToken')
            ->will($this->returnValue(true));

        $app['users'] = $users;

        $app['request'] = $request = Request::create('/bolt/editcontent/pages/4?returnto=ajax', 'POST');
        $original = $app['storage']->getContent('pages/4');
        $response = $controller->editContent('pages', 4, $app, $request);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $returned = json_decode($response->getContent());
        $this->assertEquals($original['title'], $returned->title);
    }


    protected function addSomeContent()
    {
        $app = $this->getApp();
        $this->addDefaultUser($app);
        $app['config']->set('taxonomy/categories/options', array('news'));
        $prefillMock = new LoripsumMock();
        $app['prefill'] = $prefillMock;

        $storage = new Storage($app);
        $storage->prefill(array('showcases', 'pages'));
    }

}
