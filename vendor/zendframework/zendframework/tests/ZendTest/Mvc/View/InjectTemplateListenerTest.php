<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Mvc\View;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\EventManager\EventManager;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\View\Http\InjectTemplateListener;
use Zend\View\Model\ViewModel;

class InjectTemplateListenerTest extends TestCase
{
    public function setUp()
    {
        $this->listener   = new InjectTemplateListener();
        $this->event      = new MvcEvent();
        $this->routeMatch = new RouteMatch(array());
        $this->event->setRouteMatch($this->routeMatch);
    }

    public function testSetsTemplateBasedOnRouteMatchIfNoTemplateIsSetOnViewModel()
    {
        $this->routeMatch->setParam('controller', 'Foo\Controller\SomewhatController');
        $this->routeMatch->setParam('action', 'useful');

        $model = new ViewModel();
        $this->event->setResult($model);

        $this->listener->injectTemplate($this->event);

        $this->assertEquals('foo/somewhat/useful', $model->getTemplate());
    }

    public function testUsesModuleAndControllerOnlyIfNoActionInRouteMatch()
    {
        $this->routeMatch->setParam('controller', 'Foo\Controller\SomewhatController');

        $model = new ViewModel();
        $this->event->setResult($model);

        $this->listener->injectTemplate($this->event);

        $this->assertEquals('foo/somewhat', $model->getTemplate());
    }

    public function testNormalizesLiteralControllerNameIfNoNamespaceSeparatorPresent()
    {
        $this->routeMatch->setParam('controller', 'SomewhatController');

        $model = new ViewModel();
        $this->event->setResult($model);

        $this->listener->injectTemplate($this->event);

        $this->assertEquals('somewhat', $model->getTemplate());
    }

    public function testNormalizesNamesToLowercase()
    {
        $this->routeMatch->setParam('controller', 'Somewhat.DerivedController');
        $this->routeMatch->setParam('action', 'some-UberCool');

        $model = new ViewModel();
        $this->event->setResult($model);

        $this->listener->injectTemplate($this->event);

        $this->assertEquals('somewhat.derived/some-uber-cool', $model->getTemplate());
    }

    public function testLackOfViewModelInResultBypassesTemplateInjection()
    {
        $this->assertNull($this->listener->injectTemplate($this->event));
        $this->assertNull($this->event->getResult());
    }

    public function testBypassesTemplateInjectionIfResultViewModelAlreadyHasATemplate()
    {
        $this->routeMatch->setParam('controller', 'Foo\Controller\SomewhatController');
        $this->routeMatch->setParam('action', 'useful');

        $model = new ViewModel();
        $model->setTemplate('custom');
        $this->event->setResult($model);

        $this->listener->injectTemplate($this->event);

        $this->assertEquals('custom', $model->getTemplate());
    }

    public function testMapsSubNamespaceToSubDirectoryWithControllerFromRouteMatch()
    {
        $this->routeMatch->setParam(ModuleRouteListener::MODULE_NAMESPACE, 'Aj\Controller\SweetAppleAcres\Reports');
        $this->routeMatch->setParam('controller', 'CiderSales');
        $this->routeMatch->setParam('action', 'PinkiePieRevenue');

        $moduleRouteListener = new ModuleRouteListener;
        $moduleRouteListener->onRoute($this->event);

        $model = new ViewModel();
        $this->event->setResult($model);
        $this->listener->injectTemplate($this->event);

        $this->assertEquals('aj/sweet-apple-acres/reports/cider-sales/pinkie-pie-revenue', $model->getTemplate());
    }

    public function testMapsSubNamespaceToSubDirectoryWithControllerFromRouteMatchHavingSubNamespace()
    {
        $this->routeMatch->setParam(ModuleRouteListener::MODULE_NAMESPACE, 'Aj\Controller\SweetAppleAcres\Reports');
        $this->routeMatch->setParam('controller', 'Sub\CiderSales');
        $this->routeMatch->setParam('action', 'PinkiePieRevenue');

        $moduleRouteListener = new ModuleRouteListener;
        $moduleRouteListener->onRoute($this->event);

        $model  = new ViewModel();
        $this->event->setResult($model);
        $this->listener->injectTemplate($this->event);

        $this->assertEquals('aj/sweet-apple-acres/reports/cider-sales/pinkie-pie-revenue', $model->getTemplate());
    }

    public function testMapsSubNamespaceToSubDirectoryWithControllerFromEventTarget()
    {
        $this->routeMatch->setParam(ModuleRouteListener::MODULE_NAMESPACE, 'ZendTest\Mvc\Controller\TestAsset');
        $this->routeMatch->setParam('action', 'test');

        $moduleRouteListener = new ModuleRouteListener;
        $moduleRouteListener->onRoute($this->event);

        $myViewModel  = new ViewModel();
        $myController = new \ZendTest\Mvc\Controller\TestAsset\SampleController();

        $this->event->setTarget($myController);
        $this->event->setResult($myViewModel);
        $this->listener->injectTemplate($this->event);

        $this->assertEquals('zend-test/controller/test-asset/sample/test', $myViewModel->getTemplate());
    }

    public function testMapsSubNamespaceToSubDirectoryWithControllerFromEventTargetShouldMatchControllerFromRouteParam()
    {
        $this->routeMatch->setParam(ModuleRouteListener::MODULE_NAMESPACE, 'ZendTest\Mvc\Controller');
        $this->routeMatch->setParam('controller', 'TestAsset\SampleController');
        $this->routeMatch->setParam('action', 'test');

        $moduleRouteListener = new ModuleRouteListener;
        $moduleRouteListener->onRoute($this->event);

        $myViewModel  = new ViewModel();
        $this->event->setResult($myViewModel);
        $this->listener->injectTemplate($this->event);

        $template1 = $myViewModel->getTemplate();

        $myViewModel  = new ViewModel();
        $myController = new \ZendTest\Mvc\Controller\TestAsset\SampleController();

        $this->event->setTarget($myController);
        $this->event->setResult($myViewModel);
        $this->listener->injectTemplate($this->event);

        $this->assertEquals($template1, $myViewModel->getTemplate());
    }


    public function testAttachesListenerAtExpectedPriority()
    {
        $events = new EventManager();
        $events->attachAggregate($this->listener);
        $listeners = $events->getListeners(MvcEvent::EVENT_DISPATCH);

        $expectedCallback = array($this->listener, 'injectTemplate');
        $expectedPriority = -90;
        $found            = false;
        foreach ($listeners as $listener) {
            $callback = $listener->getCallback();
            if ($callback === $expectedCallback) {
                if ($listener->getMetadatum('priority') == $expectedPriority) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Listener not found');
    }

    public function testDetachesListeners()
    {
        $events = new EventManager();
        $events->attachAggregate($this->listener);
        $listeners = $events->getListeners(MvcEvent::EVENT_DISPATCH);
        $this->assertEquals(1, count($listeners));
        $events->detachAggregate($this->listener);
        $listeners = $events->getListeners(MvcEvent::EVENT_DISPATCH);
        $this->assertEquals(0, count($listeners));
    }
}