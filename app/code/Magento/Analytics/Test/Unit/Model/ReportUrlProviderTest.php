<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Analytics\Test\Unit\Model;

use Magento\Analytics\Model\AnalyticsToken;
use Magento\Analytics\Model\Config\Backend\Baseurl\SubscriptionUpdateHandler;
use Magento\Analytics\Model\Connector\OTPRequest;
use Magento\Analytics\Model\Exception\State\SubscriptionUpdateException;
use Magento\Analytics\Model\ReportUrlProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\FlagManager;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReportUrlProviderTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $configMock;

    /**
     * @var AnalyticsToken|MockObject
     */
    private $analyticsTokenMock;

    /**
     * @var OTPRequest|MockObject
     */
    private $otpRequestMock;

    /**
     * @var FlagManager|MockObject
     */
    private $flagManagerMock;

    /**
     * @var ObjectManagerHelper
     */
    private $objectManagerHelper;

    /**
     * @var ReportUrlProvider
     */
    private $reportUrlProvider;

    /**
     * @var string
     */
    private $urlReportConfigPath = 'path/url/report';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->configMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);

        $this->analyticsTokenMock = $this->createMock(AnalyticsToken::class);

        $this->otpRequestMock = $this->createMock(OTPRequest::class);

        $this->flagManagerMock = $this->createMock(FlagManager::class);

        $this->objectManagerHelper = new ObjectManagerHelper($this);

        $this->reportUrlProvider = $this->objectManagerHelper->getObject(
            ReportUrlProvider::class,
            [
                'config' => $this->configMock,
                'analyticsToken' => $this->analyticsTokenMock,
                'otpRequest' => $this->otpRequestMock,
                'flagManager' => $this->flagManagerMock,
                'urlReportConfigPath' => $this->urlReportConfigPath,
            ]
        );
    }

    /**
     * @param bool $isTokenExist
     * @param string|null $otp If null OTP was not received.
     *
     * @dataProvider getUrlDataProvider
     */
    public function testGetUrl($isTokenExist, $otp)
    {
        $reportUrl = 'https://example.com/report';
        $url = '';

        $this->configMock
            ->expects($this->once())
            ->method('getValue')
            ->with($this->urlReportConfigPath)
            ->willReturn($reportUrl);
        $this->analyticsTokenMock
            ->expects($this->once())
            ->method('isTokenExist')
            ->with()
            ->willReturn($isTokenExist);
        $this->otpRequestMock
            ->expects($isTokenExist ? $this->once() : $this->never())
            ->method('call')
            ->with()
            ->willReturn($otp);
        if ($isTokenExist && $otp) {
            $url = $reportUrl . '?' . http_build_query(['otp' => $otp], '', '&');
        }
        $this->assertSame($url ?: $reportUrl, $this->reportUrlProvider->getUrl());
    }

    /**
     * @return array
     */
    public function getUrlDataProvider()
    {
        return [
            'TokenDoesNotExist' => [false, null],
            'TokenExistAndOtpEmpty' => [true, null],
            'TokenExistAndOtpValid' => [true, '249e6b658877bde2a77bc4ab'],
        ];
    }

    /**
     * @return void
     */
    public function testGetUrlWhenSubscriptionUpdateRunning()
    {
        $this->flagManagerMock
            ->expects($this->once())
            ->method('getFlagData')
            ->with(SubscriptionUpdateHandler::PREVIOUS_BASE_URL_FLAG_CODE)
            ->willReturn('http://store.com');
        $this->expectException(SubscriptionUpdateException::class);
        $this->reportUrlProvider->getUrl();
    }
}
