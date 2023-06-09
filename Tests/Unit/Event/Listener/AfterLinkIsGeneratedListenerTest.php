<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Typolink\LinkResult;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AfterLinkIsGeneratedListenerTest extends UnitTestCase
{
    use ProphecyTrait;

    public function test__construct()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);

        $listener = new AfterLinkIsGeneratedListener(new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal()), $this->prophesize(LinkService::class)->reveal());

        self::assertInstanceOf(AfterLinkIsGeneratedListener::class, $listener);
    }

    public function test__invokeNotModifingAnything()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);
        $siteFinder = $this->prophesize(SiteFinder::class);

        $this->prophesize(LinkService::class)->reveal();

        $linkService = $this->prophesize(LinkService::class);

        $linkService->resolve(Argument::any())->willReturn([]);

        $listener = new AfterLinkIsGeneratedListener(new UrlUtility(null, $resolver->reveal(), $siteFinder->reveal()), $linkService->reveal());

        $cObj = $this->prophesize(ContentObjectRenderer::class);

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('/', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifingFromPageUid()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $this->prophesize(LinkService::class)->reveal();

        $linkService = $this->prophesize(LinkService::class);

        $linkService->resolve(Argument::any())->willReturn(['pageuid' => '2']);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->getFrontendUrlForPage(Argument::is('/'), Argument::is(2))->willReturn('https://frontend-domain.tld/page');

        $listener = new AfterLinkIsGeneratedListener($urlUtility->reveal(), $linkService->reveal());

        $cObj = $this->prophesize(ContentObjectRenderer::class);

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkText('t3://page?uid=2');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('https://frontend-domain.tld/page', $event->getLinkResult()->getUrl());
    }

    public function test__invokeModifingWithoutPageId()
    {
        $resolver = $this->prophesize(Resolver::class);
        $resolver->evaluate(Argument::any())->willReturn(true);

        $this->prophesize(LinkService::class)->reveal();

        $linkService = $this->prophesize(LinkService::class);

        $linkService->resolve(Argument::any())->willReturn(['pageuid' => '|']);

        $site = new Site('test', 1, []);

        $urlUtility = $this->prophesize(UrlUtility::class);
        $urlUtility->getFrontendUrlWithSite(Argument::is('/'), Argument::is($site))->willReturn('https://front.typo3.tld');

        $listener = new AfterLinkIsGeneratedListener($urlUtility->reveal(), $linkService->reveal());

        $cObj = $this->prophesize(ContentObjectRenderer::class);
        $cObj->getRequest()->willReturn((new ServerRequest())->withAttribute('site', $site));

        $linkResult = new LinkResult('page', '/');
        $linkResult = $linkResult->withLinkText('|');

        $event = new AfterLinkIsGeneratedEvent($linkResult, $cObj->reveal(), []);
        $listener($event);

        self::assertSame('https://front.typo3.tld', $event->getLinkResult()->getUrl());
    }
}