<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Core\Tests\Unit\DataHandling;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\NullFrontend;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\DataHandlerCheckModifyAccessListHookInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\PasswordPolicy\Event\EnrichPasswordValidationContextDataEvent;
use TYPO3\CMS\Core\PasswordPolicy\Validator\Dto\ContextData;
use TYPO3\CMS\Core\SysLog;
use TYPO3\CMS\Core\Tests\Unit\DataHandling\Fixtures\AllowAccessHookFixture;
use TYPO3\CMS\Core\Tests\Unit\DataHandling\Fixtures\InvalidHookFixture;
use TYPO3\CMS\Core\Tests\Unit\DataHandling\Fixtures\UserOddNumberFilter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DataHandlerTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;
    protected DataHandler&MockObject&AccessibleObjectInterface $subject;
    protected BackendUserAuthentication&MockObject $backendUserMock;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TCA'] = [];
        $cacheManager = new CacheManager();
        $cacheManager->registerCache(new NullFrontend('runtime'));
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager);
        $this->backendUserMock = $this->createMock(BackendUserAuthentication::class);
        $this->subject = $this->getAccessibleMock(DataHandler::class, null);
        $this->subject->start([], [], $this->backendUserMock);
    }

    /**
     * @test
     */
    public function fixtureCanBeCreated(): void
    {
        self::assertInstanceOf(DataHandler::class, $this->subject);
    }

    //////////////////////////////////////////
    // Test concerning checkModifyAccessList
    //////////////////////////////////////////
    /**
     * @test
     */
    public function adminIsAllowedToModifyNonAdminTable(): void
    {
        $this->subject->admin = true;
        self::assertTrue($this->subject->checkModifyAccessList('tt_content'));
    }

    /**
     * @test
     */
    public function nonAdminIsNorAllowedToModifyNonAdminTable(): void
    {
        $this->subject->admin = false;
        self::assertFalse($this->subject->checkModifyAccessList('tt_content'));
    }

    /**
     * @test
     */
    public function nonAdminWithTableModifyAccessIsAllowedToModifyNonAdminTable(): void
    {
        $this->subject->admin = false;
        $this->backendUserMock->groupData['tables_modify'] = 'tt_content';
        self::assertTrue($this->subject->checkModifyAccessList('tt_content'));
    }

    /**
     * @test
     */
    public function adminIsAllowedToModifyAdminTable(): void
    {
        $this->subject->admin = true;
        self::assertTrue($this->subject->checkModifyAccessList('be_users'));
    }

    /**
     * @test
     */
    public function nonAdminIsNotAllowedToModifyAdminTable(): void
    {
        $this->subject->admin = false;
        self::assertFalse($this->subject->checkModifyAccessList('be_users'));
    }

    /**
     * @test
     */
    public function nonAdminWithTableModifyAccessIsNotAllowedToModifyAdminTable(): void
    {
        $tableName = StringUtility::getUniqueId('aTable');
        $GLOBALS['TCA'] = [
            $tableName => [
                'ctrl' => [
                    'adminOnly' => true,
                ],
            ],
        ];
        $this->subject->admin = false;
        $this->backendUserMock->groupData['tables_modify'] = $tableName;
        self::assertFalse($this->subject->checkModifyAccessList($tableName));
    }

    public static function checkValueForDatetimeDataProvider(): array
    {
        // Three elements: input, timezone of input, expected output (UTC)
        return [
            'timestamp is passed through, as it is UTC' => [
                1457103519, 'Europe/Berlin', 1457103519,
            ],
            'ISO date is interpreted as local date and is output as correct timestamp' => [
                '2017-06-07T00:10:00Z', 'Europe/Berlin', 1496787000,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider checkValueForDatetimeDataProvider
     */
    public function checkValueForDatetime($input, $serverTimezone, $expectedOutput): void
    {
        $oldTimezone = date_default_timezone_get();
        date_default_timezone_set($serverTimezone);

        $output =  $this->subject->_call('checkValueForDatetime', $input, ['type' => 'datetime']);

        // set before the assertion is performed, so it is restored even for failing tests
        date_default_timezone_set($oldTimezone);

        self::assertEquals($expectedOutput, $output['value']);
    }

    public static function checkValueForColorDataProvider(): array
    {
        // Three elements: input, timezone of input, expected output (UTC)
        return [
            'trim is applied' => [
                ' #FF8700 ', '#FF8700',
            ],
            'max string length is respected' => [
                '#FF8700123', '#FF8700',
            ],
            'required is checked' => [
                '', null, ['required' => true],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider checkValueForColorDataProvider
     */
    public function checkValueForColor(string $input, mixed $expected, array $additionalFieldConfig = []): void
    {
        $output = $this->subject->_call(
            'checkValueForColor',
            $input,
            array_replace_recursive(['type' => 'datetime'], $additionalFieldConfig)
        );

        self::assertEquals($expected, $output['value'] ?? null);
    }

    /**
     * @test
     */
    public function checkValuePasswordWithSaltedPasswordKeepsExistingHash(): void
    {
        // Note the involved salted passwords are NOT mocked since the factory is static
        $inputValue = '$1$GNu9HdMt$RwkPb28pce4nXZfnplVZY/';
        $result = $this->subject->_call('checkValueForPassword', $inputValue, [], 'be_users', 0, 0);
        self::assertSame($inputValue, $result['value']);
    }

    /**
     * @test
     */
    public function checkValuePasswordWithSaltedPasswordReturnsHashForSaltedPassword(): void
    {
        $event = new EnrichPasswordValidationContextDataEvent(new ContextData(), [], '');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->willReturn($event);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $eventDispatcher);

        // Note the involved salted passwords are NOT mocked since the factory is static
        $inputValue = 'myPassword';
        $result = $this->subject->_call('checkValueForPassword', $inputValue, [], 'be_users', 0, 0);
        self::assertNotSame($inputValue, $result['value']);
    }

    public static function numberValueCheckRecognizesStringValuesAsIntegerValuesCorrectlyDataProvider(): array
    {
        return [
            'Empty string returns zero as integer' => [
                '',
                0,
            ],
            '"0" returns zero as integer' => [
                '0',
                0,
            ],
            '"-2000001" is interpreted correctly as -2000001 but is lower than -2000000 and set to -2000000' => [
                '-2000001',
                -2000000,
            ],
            '"-2000000" is interpreted correctly as -2000000 and is equal to -2000000' => [
                '-2000000',
                -2000000,
            ],
            '"2000000" is interpreted correctly as 2000000 and is equal to 2000000' => [
                '2000000',
                2000000,
            ],
            '"2000001" is interpreted correctly as 2000001 but is greater then 2000000 and set to 2000000' => [
                '2000001',
                2000000,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider numberValueCheckRecognizesStringValuesAsIntegerValuesCorrectlyDataProvider
     */
    public function numberValueCheckRecognizesStringValuesAsIntegerValuesCorrectly(string $value, int $expectedReturnValue): void
    {
        $tcaFieldConf = [
            'type' => 'number',
            'range' => [
                'lower' => '-2000000',
                'upper' => '2000000',
            ],
        ];
        $returnValue = $this->subject->_call('checkValueForNumber', $value, $tcaFieldConf, '', 0, 0, '');
        self::assertSame($expectedReturnValue, $returnValue['value']);
    }

    public static function numberValueCheckRecognizesDecimalStringValuesAsFloatValuesCorrectlyDataProvider(): iterable
    {
        yield 'simple negative decimal value with comma and one position' => [
            'input' => '-0,5',
            'expected' => '-0.50',
        ];

        yield 'simple integer' => [
            'input' => '1000',
            'expected' => '1000.00',
        ];

        yield 'positive decimal value with one position' => [
            'input' => '1000,0',
            'expected' => '1000.00',
        ];

        yield 'positive decimal value with 2 positions and trailing zero' => [
            'input' => '1000,10',
            'expected' => '1000.10',
        ];

        yield 'positive decimal value with 2 positions without trailing zero' => [
            'input' => '1000,11',
            'expected' => '1000.11',
        ];

        yield 'number separated with dots' => [
            'input' => '600.000.000,00',
            'expected' => '600000000.00',
        ];

        yield 'invalid input with characters between the number' => [
            'input' => '60aaa00',
            'expected' => '6000.00',
        ];
    }

    /**
     * @test
     * @dataProvider numberValueCheckRecognizesDecimalStringValuesAsFloatValuesCorrectlyDataProvider
     */
    public function numberValueCheckRecognizesDecimalStringValuesAsFloatValuesCorrectly(string $value, string $expectedReturnValue): void
    {
        $tcaFieldConf = [
            'type' => 'number',
            'format' => 'decimal',
        ];
        $returnValue = $this->subject->_call('checkValueForNumber', $value, $tcaFieldConf);
        self::assertSame($expectedReturnValue, $returnValue['value']);
    }

    /**
     * Data provider for inputValuesRangeDoubleDataProvider
     */
    public static function inputValuesRangeDoubleDataProvider(): array
    {
        return [
            'Empty string returns zero as string' => [
                '',
                '0.00',
            ],
            '"0" returns zero as string' => [
                '0',
                '0.00',
            ],
            '"-0.5" is interpreted correctly as -0.5 but is lower than 0 and set to 0' => [
                '-0.5',
                0,
            ],
            '"0.5" is interpreted correctly as 0.5 and is equal to 0.5' => [
                '0.5',
                '0.50',
            ],
            '"39.9" is interpreted correctly as 39.9 and is equal to 39.9' => [
                '39.9',
                '39.90',
            ],
            '"42.3" is interpreted correctly as 42.3 but is greater then 42 and set to 42' => [
                '42.3',
                42,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider inputValuesRangeDoubleDataProvider
     */
    public function inputValueCheckRespectsRightLowerAndUpperLimitForDouble(string $value, string|int $expectedReturnValue): void
    {
        $tcaFieldConf = [
            'type' => 'number',
            'format' => 'decimal',
            'range' => [
                'lower' => '0',
                'upper' => '42',
            ],
        ];
        $returnValue = $this->subject->_call('checkValueForNumber', $value, $tcaFieldConf);
        self::assertSame($expectedReturnValue, $returnValue['value']);
    }

    /**
     * @test
     * @dataProvider inputValuesRangeDoubleDataProvider
     */
    public function inputValueCheckRespectsRightLowerAndUpperLimitWithDefaultValueForDouble(string $value, string|int $expectedReturnValue): void
    {
        $tcaFieldConf = [
            'type' => 'number',
            'format' => 'decimal',
            'range' => [
                'lower' => '0',
                'upper' => '42',
            ],
            'default' => 0,
        ];
        $returnValue = $this->subject->_call('checkValueForNumber', $value, $tcaFieldConf, '', 0, 0, '');
        self::assertSame($expectedReturnValue, $returnValue['value']);
    }

    public static function datetimeValuesDataProvider(): array
    {
        return [
            'undershot date adjusted' => [
                '2018-02-28T00:00:00Z',
                1519862400,
            ],
            'exact lower date accepted' => [
                '2018-03-01T00:00:00Z',
                1519862400,
            ],
            'exact upper date accepted' => [
                '2018-03-31T23:59:59Z',
                1522540799,
            ],
            'exceeded date adjusted' => [
                '2018-04-01T00:00:00Z',
                1522540799,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider datetimeValuesDataProvider
     */
    public function valueCheckRecognizesDatetimeValuesAsIntegerValuesCorrectly(string $value, int $expected): void
    {
        $tcaFieldConf = [
            'type' => 'datetime',
            'range' => [
                // unix timestamp: 1519862400
                'lower' => gmmktime(0, 0, 0, 3, 1, 2018),
                // unix timestamp: 1522540799
                'upper' => gmmktime(23, 59, 59, 3, 31, 2018),
            ],
        ];

        // @todo Switch to UTC since otherwise DataHandler removes timezone offset
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $returnValue = $this->subject->_call('checkValueForDatetime', $value, $tcaFieldConf);

        date_default_timezone_set($previousTimezone);

        self::assertSame($expected, $returnValue['value']);
    }

    public static function inputValueRangeCheckIsIgnoredWhenDefaultIsZeroAndInputValueIsEmptyDataProvider(): array
    {
        return [
            'Empty string returns the number zero' => [
                '',
                0,
            ],
            'Zero returns zero' => [
                0,
                0,
            ],
            'Zero as a string returns zero' => [
                '0',
                0,
            ],
        ];
    }

    /**
     * @dataProvider inputValueRangeCheckIsIgnoredWhenDefaultIsZeroAndInputValueIsEmptyDataProvider
     * @test
     */
    public function inputValueRangeCheckIsIgnoredWhenDefaultIsZeroAndInputValueIsEmpty(
        string|int $inputValue,
        int $expected,
    ): void {
        $tcaFieldConf = [
            'type' => 'datetime',
            'default' => 0,
            'range' => [
                'lower' => 1627077600,
            ],
        ];

        $returnValue = $this->subject->_call('checkValueForDatetime', $inputValue, $tcaFieldConf);
        self::assertSame($expected, $returnValue['value']);
    }

    public static function datetimeValueCheckDbtypeIsIndependentFromTimezoneDataProvider(): array
    {
        return [
            // Values of this kind are passed in from the DateTime control
            'time from DateTime' => [
                '1970-01-01T18:54:00Z',
                'time',
                '18:54:00',
            ],
            'date from DateTime' => [
                '2020-11-25T00:00:00Z',
                'date',
                '2020-11-25',
            ],
            'datetime from DateTime' => [
                '2020-11-25T18:54:00Z',
                'datetime',
                '2020-11-25 18:54:00',
            ],
            // Values of this kind are passed in when a data record is copied
            'time from copying a record' => [
                '18:54:00',
                'time',
                '18:54:00',
            ],
            'date from copying a record' => [
                '2020-11-25',
                'date',
                '2020-11-25',
            ],
            'datetime from copying a record' => [
                '2020-11-25 18:54:00',
                'datetime',
                '2020-11-25 18:54:00',
            ],
        ];
    }

    /**
     * Tests whether native dbtype inputs are parsed independent of the server timezone.
     * @test
     * @dataProvider datetimeValueCheckDbtypeIsIndependentFromTimezoneDataProvider
     */
    public function datetimeValueCheckDbtypeIsIndependentFromTimezone(string $value, string $dbtype, string $expectedOutput): void
    {
        $tcaFieldConf = [
            'type' => 'datetime',
            'dbType' => $dbtype,
        ];

        $oldTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        $returnValue = $this->subject->_call('checkValueForDatetime', $value, $tcaFieldConf);

        // set before the assertion is performed, so it is restored even for failing tests
        date_default_timezone_set($oldTimezone);

        self::assertEquals($expectedOutput, $returnValue['value']);
    }

    ///////////////////////////////////////////
    // Tests concerning checkModifyAccessList
    ///////////////////////////////////////////
    //
    /**
     * Tests whether a wrong interface on the 'checkModifyAccessList' hook throws an exception.
     * @test
     */
    public function doesCheckModifyAccessListThrowExceptionOnWrongHookInterface(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1251892472);

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkModifyAccessList'][] = InvalidHookFixture::class;
        $this->subject->checkModifyAccessList('tt_content');
    }

    /**
     * Tests whether the 'checkModifyAccessList' hook is called correctly.
     *
     * @test
     */
    public function doesCheckModifyAccessListHookGetsCalled(): void
    {
        $hookClass = StringUtility::getUniqueId('tx_coretest');
        $hookMock = $this->getMockBuilder(DataHandlerCheckModifyAccessListHookInterface::class)
            ->onlyMethods(['checkModifyAccessList'])
            ->setMockClassName($hookClass)
            ->getMock();
        $hookMock->expects(self::once())->method('checkModifyAccessList');
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkModifyAccessList'][] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hookMock);
        $this->subject->checkModifyAccessList('tt_content');
    }

    /**
     * Tests whether the 'checkModifyAccessList' hook modifies the $accessAllowed variable.
     *
     * @test
     */
    public function doesCheckModifyAccessListHookModifyAccessAllowed(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkModifyAccessList'][] = AllowAccessHookFixture::class;
        self::assertTrue($this->subject->checkModifyAccessList('tt_content'));
    }

    /////////////////////////////////////
    // Tests concerning process_datamap
    /////////////////////////////////////
    /**
     * @test
     */
    public function processDatamapForFrozenNonZeroWorkspaceReturnsFalse(): void
    {
        $subject = $this->getMockBuilder(DataHandler::class)
            ->onlyMethods(['log'])
            ->getMock();
        $this->backendUserMock->workspace = 1;
        $this->backendUserMock->workspaceRec = ['freeze' => true];
        $subject->BE_USER = $this->backendUserMock;
        self::assertFalse($subject->process_datamap());
    }

    public static function checkValue_flex_procInData_travDSDataProvider(): iterable
    {
        yield 'Flat structure' => [
            'dataValues' => [
                'field1' => [
                    'vDEF' => 'wrong input',
                ],
            ],
            'DSelements' => [
                'field1' => [
                    'label' => 'A field',
                    'config' => [
                        'type' => 'number',
                        'required' => true,
                    ],
                ],
            ],
            'expected' => [
                'field1' => [
                    'vDEF' => 0,
                ],
            ],
        ];

        yield 'Array structure' => [
            'dataValues' => [
                'section' => [
                    'el' => [
                        '1' => [
                            'container1' => [
                                'el' => [
                                    'field1' => [
                                        'vDEF' => 'wrong input',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'DSelements' => [
                'section' => [
                    'type' => 'array',
                    'section' => true,
                    'el' => [
                        'container1' => [
                            'type' => 'array',
                            'el' => [
                                'field1' => [
                                    'label' => 'A field',
                                    'config' => [
                                        'type' => 'number',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'expected' => [
                'section' => [
                    'el' => [
                        '1' => [
                            'container1' => [
                                'el' => [
                                    'field1' => [
                                        'vDEF' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * This test ensures, that the eval method checkValue_SW is called on
     * flexform structures.
     *
     * @test
     * @dataProvider checkValue_flex_procInData_travDSDataProvider
     */
    public function checkValue_flex_procInData_travDS(array $dataValues, array $DSelements, array $expected): void
    {
        $pParams = [
            'tt_content',
            777,
            '<?xml ... ?>',
            'update',
            1,
            'tt_content:777:pi_flexform',
            0,
        ];

        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $this->subject->checkValue_flex_procInData_travDS($dataValues, [], $DSelements, $pParams, '', '');
        self::assertSame($expected, $dataValues);
    }

    /////////////////////////////////////
    // Tests concerning log
    /////////////////////////////////////
    /**
     * @test
     */
    public function logCallsWriteLogOfBackendUserIfLoggingIsEnabled(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->expects(self::once())->method('writelog');
        $this->subject->enableLogging = true;
        $this->subject->BE_USER = $backendUser;
        $this->subject->log('', 23, SysLog\Action::UNDEFINED, 42, SysLog\Error::MESSAGE, 'details');
    }

    /**
     * @test
     */
    public function logDoesNotCallWriteLogOfBackendUserIfLoggingIsDisabled(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->expects(self::never())->method('writelog');
        $this->subject->enableLogging = false;
        $this->subject->BE_USER = $backendUser;
        $this->subject->log('', 23, SysLog\Action::UNDEFINED, 42, SysLog\Error::MESSAGE, 'details');
    }

    /**
     * @test
     */
    public function logAddsEntryToLocalErrorLogArray(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $this->subject->BE_USER = $backendUser;
        $this->subject->enableLogging = true;
        $this->subject->errorLog = [];
        $logDetailsUnique = StringUtility::getUniqueId('details');
        $this->subject->log('', 23, SysLog\Action::UNDEFINED, 42, SysLog\Error::USER_ERROR, $logDetailsUnique);
        self::assertArrayHasKey(0, $this->subject->errorLog);
        self::assertStringEndsWith($logDetailsUnique, $this->subject->errorLog[0]);
    }

    /**
     * @test
     */
    public function logFormatsDetailMessageWithAdditionalDataInLocalErrorArray(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $this->subject->BE_USER = $backendUser;
        $this->subject->enableLogging = true;
        $this->subject->errorLog = [];
        $logDetails = StringUtility::getUniqueId('details');
        $this->subject->log('', 23, SysLog\Action::UNDEFINED, 42, SysLog\Error::USER_ERROR, '%1$s' . $logDetails . '%2$s', -1, ['foo', 'bar']);
        $expected = 'foo' . $logDetails . 'bar';
        self::assertStringEndsWith($expected, $this->subject->errorLog[0]);
    }

    /**
     * @test
     */
    public function logFormatsDetailMessageWithPlaceholders(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $this->subject->BE_USER = $backendUser;
        $this->subject->enableLogging = true;
        $this->subject->errorLog = [];
        $logDetails = 'An error occurred on {table}:{uid} when localizing';
        $this->subject->log('', 23, SysLog\Action::UNDEFINED, 42, SysLog\Error::USER_ERROR, $logDetails, -1, ['table' => 'tx_sometable', 0 => 'some random value']);
        // UID is kept as non-replaced, and other properties are not replaced.
        $expected = 'An error occurred on tx_sometable:{uid} when localizing';
        self::assertStringEndsWith($expected, $this->subject->errorLog[0]);
    }

    /**
     * @dataProvider equalSubmittedAndStoredValuesAreDeterminedDataProvider
     * @test
     */
    public function equalSubmittedAndStoredValuesAreDetermined(bool $expected, string|int|null $submittedValue, string|int|null $storedValue, string $storedType, bool $allowNull): void
    {
        $result = \Closure::bind(function () use ($submittedValue, $storedValue, $storedType, $allowNull) {
            return $this->isSubmittedValueEqualToStoredValue($submittedValue, $storedValue, $storedType, $allowNull);
        }, $this->subject, DataHandler::class)();
        self::assertEquals($expected, $result);
    }

    public static function equalSubmittedAndStoredValuesAreDeterminedDataProvider(): array
    {
        return [
            // String
            'string value "" vs. ""' => [
                true,
                '', '', 'string', false,
            ],
            'string value 0 vs. "0"' => [
                true,
                0, '0', 'string', false,
            ],
            'string value 1 vs. "1"' => [
                true,
                1, '1', 'string', false,
            ],
            'string value "0" vs. ""' => [
                false,
                '0', '', 'string', false,
            ],
            'string value 0 vs. ""' => [
                false,
                0, '', 'string', false,
            ],
            'string value null vs. ""' => [
                true,
                null, '', 'string', false,
            ],
            // Integer
            'integer value 0 vs. 0' => [
                true,
                0, 0, 'int', false,
            ],
            'integer value "0" vs. "0"' => [
                true,
                '0', '0', 'int', false,
            ],
            'integer value 0 vs. "0"' => [
                true,
                0, '0', 'int', false,
            ],
            'integer value "" vs. "0"' => [
                true,
                '', '0', 'int', false,
            ],
            'integer value "" vs. 0' => [
                true,
                '', 0, 'int', false,
            ],
            'integer value "0" vs. 0' => [
                true,
                '0', 0, 'int', false,
            ],
            'integer value 1 vs. 1' => [
                true,
                1, 1, 'int', false,
            ],
            'integer value 1 vs. "1"' => [
                true,
                1, '1', 'int', false,
            ],
            'integer value "1" vs. "1"' => [
                true,
                '1', '1', 'int', false,
            ],
            'integer value "1" vs. 1' => [
                true,
                '1', 1, 'int', false,
            ],
            'integer value "0" vs. "1"' => [
                false,
                '0', '1', 'int', false,
            ],
            // String with allowed NULL values
            'string with allowed null value "" vs. ""' => [
                true,
                '', '', 'string', true,
            ],
            'string with allowed null value 0 vs. "0"' => [
                true,
                0, '0', 'string', true,
            ],
            'string with allowed null value 1 vs. "1"' => [
                true,
                1, '1', 'string', true,
            ],
            'string with allowed null value "0" vs. ""' => [
                false,
                '0', '', 'string', true,
            ],
            'string with allowed null value 0 vs. ""' => [
                false,
                0, '', 'string', true,
            ],
            'string with allowed null value null vs. ""' => [
                false,
                null, '', 'string', true,
            ],
            'string with allowed null value "" vs. null' => [
                false,
                '', null, 'string', true,
            ],
            'string with allowed null value null vs. null' => [
                true,
                null, null, 'string', true,
            ],
            // Integer with allowed NULL values
            'integer with allowed null value 0 vs. 0' => [
                true,
                0, 0, 'int', true,
            ],
            'integer with allowed null value "0" vs. "0"' => [
                true,
                '0', '0', 'int', true,
            ],
            'integer with allowed null value 0 vs. "0"' => [
                true,
                0, '0', 'int', true,
            ],
            'integer with allowed null value "" vs. "0"' => [
                true,
                '', '0', 'int', true,
            ],
            'integer with allowed null value "" vs. 0' => [
                true,
                '', 0, 'int', true,
            ],
            'integer with allowed null value "0" vs. 0' => [
                true,
                '0', 0, 'int', true,
            ],
            'integer with allowed null value 1 vs. 1' => [
                true,
                1, 1, 'int', true,
            ],
            'integer with allowed null value "1" vs. "1"' => [
                true,
                '1', '1', 'int', true,
            ],
            'integer with allowed null value "1" vs. 1' => [
                true,
                '1', 1, 'int', true,
            ],
            'integer with allowed null value 1 vs. "1"' => [
                true,
                1, '1', 'int', true,
            ],
            'integer with allowed null value "0" vs. "1"' => [
                false,
                '0', '1', 'int', true,
            ],
            'integer with allowed null value null vs. ""' => [
                false,
                null, '', 'int', true,
            ],
            'integer with allowed null value "" vs. null' => [
                false,
                '', null, 'int', true,
            ],
            'integer with allowed null value null vs. null' => [
                true,
                null, null, 'int', true,
            ],
            'integer with allowed null value null vs. "0"' => [
                false,
                null, '0', 'int', true,
            ],
            'integer with allowed null value null vs. 0' => [
                false,
                null, 0, 'int', true,
            ],
            'integer with allowed null value "0" vs. null' => [
                false,
                '0', null, 'int', true,
            ],
        ];
    }

    /**
     * @test
     */
    public function deletePagesOnRootLevelIsDenied(): void
    {
        $dataHandlerMock = $this->getMockBuilder(DataHandler::class)
            ->onlyMethods(['canDeletePage', 'log'])
            ->getMock();
        $dataHandlerMock
            ->expects(self::never())
            ->method('canDeletePage');
        $dataHandlerMock
            ->expects(self::once())
            ->method('log')
            ->with('pages', 0, 3, 0, 2, 'Deleting all pages starting from the root-page is disabled', -1, [], 0);

        $dataHandlerMock->deletePages(0);
    }

    /**
     * @test
     */
    public function deleteRecord_procBasedOnFieldTypeRespectsEnableCascadingDelete(): void
    {
        $table = StringUtility::getUniqueId('foo_');
        $conf = [
            'type' => 'inline',
            'foreign_table' => StringUtility::getUniqueId('foreign_foo_'),
            'behaviour' => [
                'enableCascadingDelete' => 0,
            ],
        ];

        $mockRelationHandler = $this->createMock(RelationHandler::class);
        $mockRelationHandler->itemArray = [
            '1' => ['table' => StringUtility::getUniqueId('bar_'), 'id' => 67],
        ];

        $mockDataHandler = $this->getAccessibleMock(DataHandler::class, ['getRelationFieldType', 'deleteAction', 'createRelationHandlerInstance'], [], '', false);
        $mockDataHandler->expects(self::once())->method('getRelationFieldType')->willReturn('field');
        $mockDataHandler->expects(self::once())->method('createRelationHandlerInstance')->willReturn($mockRelationHandler);
        $mockDataHandler->expects(self::never())->method('deleteAction');
        $mockDataHandler->deleteRecord_procBasedOnFieldType($table, 42, 'bar', $conf);
    }

    public static function checkValue_checkReturnsExpectedValuesDataProvider(): array
    {
        return [
            'None item selected' => [
                0,
                0,
            ],
            'All items selected' => [
                7,
                7,
            ],
            'Item 1 and 2 are selected' => [
                3,
                3,
            ],
            'Value is higher than allowed (all checkboxes checked)' => [
                15,
                7,
            ],
            'Value is higher than allowed (some checkboxes checked)' => [
                11,
                3,
            ],
            'Negative value' => [
                -5,
                0,
            ],
        ];
    }

    /**
     * @dataProvider checkValue_checkReturnsExpectedValuesDataProvider
     * @test
     */
    public function checkValue_checkReturnsExpectedValues(string|int $value, string|int $expectedValue): void
    {
        $expectedResult = [
            'value' => $expectedValue,
        ];
        $result = [];
        $tcaFieldConfiguration = [
            'items' => [
                ['Item 1', 0],
                ['Item 2', 0],
                ['Item 3', 0],
            ],
        ];
        self::assertSame($expectedResult, $this->subject->_call('checkValueForCheck', $result, $value, $tcaFieldConfiguration, '', 0, 0, ''));
    }

    /**
     * @test
     */
    public function checkValueForInputConvertsNullToEmptyString(): void
    {
        $expectedResult = ['value' => ''];
        self::assertSame($expectedResult, $this->subject->_call('checkValueForInput', null, ['type' => 'input', 'max' => 40], 'tt_content', 'NEW55c0e67f8f4d32.04974534', 89, 'table_caption'));
    }

    public static function checkValueForJsonDataProvider(): \Generator
    {
        yield 'Converts empty string to array' => [
            '',
            ['value' => []],
        ];
        yield 'Handles invalid JSON' => [
            '_-invalid-_',
            [],
        ];
        yield 'Decodes JSON' => [
            '{"foo":"bar"}',
            ['value' => ['foo' => 'bar']],
        ];
        yield 'Array is not decoded' => [
            ['foo' => 'bar'],
            ['value' => ['foo' => 'bar']],
        ];
    }

    /**
     * @test
     * @dataProvider checkValueForJsonDataProvider
     */
    public function checkValueForJson(string|array $input, array $expected): void
    {
        self::assertSame(
            $expected,
            $this->subject->_call(
                'checkValueForJson',
                $input,
                ['type' => 'json']
            )
        );
    }

    /**
     * @test
     */
    public function checkValueForUuidReturnsValidUuidUnmodified(): void
    {
        self::assertEquals(
            'b3190536-1431-453e-afbb-25b8c5022513',
            Uuid::isValid($this->subject->_call('checkValueForUuid', 'b3190536-1431-453e-afbb-25b8c5022513', [])['value'])
        );
    }

    /**
     * @test
     */
    public function checkValueForUuidCreatesValidUuidValueForReqiredFieldsWithInvalidUuidGiven(): void
    {
        self::assertTrue(Uuid::isValid($this->subject->_call('checkValueForUuid', '', [])['value']));
        self::assertTrue(Uuid::isValid($this->subject->_call('checkValueForUuid', '-_invalid_-', [])['value']));
    }

    /**
     * @test
     */
    public function checkValueForUuidDiscardsInvalidUuidIfFieldIsNotRequired(): void
    {
        self::assertEmpty($this->subject->_call('checkValueForUuid', '', ['required' => false]));
        self::assertEmpty($this->subject->_call('checkValueForUuid', '-_invalid_-', ['required' => false]));
    }

    /**
     * @test
     */
    public function checkValueForUuidCreatesValidUuidValueWithDefinedVersion(): void
    {
        self::assertEquals(6, (int)$this->subject->_call('checkValueForUuid', '', ['version' => 6])['value'][14]);
        self::assertEquals(7, (int)$this->subject->_call('checkValueForUuid', '', ['version' => 7])['value'][14]);
        self::assertEquals(4, (int)$this->subject->_call('checkValueForUuid', '', ['version' => 4])['value'][14]);
        // Defaults to 4
        self::assertEquals(4, (int)$this->subject->_call('checkValueForUuid', '', ['version' => 123456678])['value'][14]);
        // Defaults to 4
        self::assertEquals(4, (int)$this->subject->_call('checkValueForUuid', '', [])['value'][14]);
    }

    /**
     * @test
     * @dataProvider referenceValuesAreCastedDataProvider
     */
    public function referenceValuesAreCasted(string $value, array $configuration, bool $isNew, int|string $expected): void
    {
        self::assertEquals(
            $expected,
            $this->subject->_call('castReferenceValue', $value, $configuration, $isNew)
        );
    }

    public static function referenceValuesAreCastedDataProvider(): array
    {
        return [
            'all empty' => [
                '', [], true, '',
            ],
            'cast zero with MM table' => [
                '', ['MM' => 'table'], true, 0,
            ],
            'cast zero with MM table with default value' => [
                '', ['MM' => 'table', 'default' => 13], true, 0,
            ],
            'cast zero with foreign field' => [
                '', ['foreign_field' => 'table', 'default' => 13], true, 0,
            ],
            'cast zero with foreign field with default value' => [
                '', ['foreign_field' => 'table'], true, 0,
            ],
            'pass zero' => [
                '0', [], true, '0',
            ],
            'pass value' => [
                '1', ['default' => 13], true, '1',
            ],
            'use default value' => [
                '', ['default' => 13], true, 13,
            ],
        ];
    }

    public static function clearPrefixFromValueRemovesPrefixDataProvider(): array
    {
        return [
            'normal case' => ['Test (copy 42)', 'Test'],
            // all cases below look fishy and indicate bugs
            'with double spaces before' => ['Test  (copy 42)', 'Test '],
            'with three spaces before' => ['Test   (copy 42)', 'Test  '],
            'with space after' => ['Test (copy 42) ', 'Test (copy 42) '],
            'with double spaces after' => ['Test (copy 42)  ', 'Test (copy 42)  '],
            'with three spaces after' => ['Test (copy 42)   ', 'Test (copy 42)   '],
            'with double tab before' => ['Test' . "\t" . '(copy 42)', 'Test'],
            'with double tab after' => ['Test (copy 42)' . "\t", 'Test (copy 42)' . "\t"],
        ];
    }

    /**
     * @test
     * @dataProvider clearPrefixFromValueRemovesPrefixDataProvider
     */
    public function clearPrefixFromValueRemovesPrefix(string $input, string $expected): void
    {
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->with('testLabel')->willReturn('(copy %s)');
        $GLOBALS['LANG'] = $languageServiceMock;
        $GLOBALS['TCA']['testTable']['ctrl']['prependAtCopy'] = 'testLabel';
        self::assertEquals($expected, (new DataHandler())->clearPrefixFromValue('testTable', $input));
    }

    public static function applyFiltersToValuesFiltersValuesDataProvider(): iterable
    {
        yield 'values are filtered by provided user function' => [
            'tcaFieldConfiguration' => [
                'filter' => [
                    [
                        'userFunc' => UserOddNumberFilter::class . '->filter',
                    ],
                ],
            ],
            'values' => [1, 2, 3, 4, 5],
            'expected' => [1, 3, 5],
        ];

        yield 'parameters are passed to the user function' => [
            'tcaFieldConfiguration' => [
                'filter' => [
                    [
                        'userFunc' => UserOddNumberFilter::class . '->filter',
                        'parameters' => [
                            'exclude' => 1,
                        ],
                    ],
                ],
            ],
            'values' => [1, 2, 3, 4, 5],
            'expected' => [3, 5],
        ];

        yield 'no filters return value as is' => [
            'tcaFieldConfiguration' => [],
            'values' => [1, 2, 3, 4, 5],
            'expected' => [1, 2, 3, 4, 5],
        ];
    }

    /**
     * @dataProvider applyFiltersToValuesFiltersValuesDataProvider
     * @test
     */
    public function applyFiltersToValuesFiltersValues(array $tcaFieldConfiguration, array $values, array $expected): void
    {
        self::assertEqualsCanonicalizing($expected, $this->subject->_call('applyFiltersToValues', $tcaFieldConfiguration, $values));
    }

    /**
     * @test
     */
    public function applyFiltersToValuesExpectsArray(): void
    {
        $tcaFieldConfiguration = [
            'filter' => [
                [
                    'userFunc' => UserOddNumberFilter::class . '->filter',
                    'parameters' => [
                        'break' => true,
                    ],
                ],
            ],
        ];

        $values = [1, 2, 3, 4, 5];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1336051942);
        $this->expectExceptionMessage('Expected userFunc filter "TYPO3\CMS\Core\Tests\Unit\DataHandling\Fixtures\UserOddNumberFilter->filter" to return an array. Got NULL.');
        $this->subject->_call('applyFiltersToValues', $tcaFieldConfiguration, $values);
    }

    public static function validateValueForRequiredReturnsExpectedValueDataHandler(): iterable
    {
        yield 'no required flag set with empty string' => [
            [],
            '',
            true,
        ];

        yield 'no required flag set with (string)0' => [
            [],
            '0',
            true,
        ];

        yield 'required flag set with empty string' => [
            ['required' => true],
            '',
            false,
        ];

        yield 'required flag set with null value' => [
            ['required' => true],
            null,
            false,
        ];

        yield 'required flag set with (string)0' => [
            ['required' => true],
            '0',
            true,
        ];

        yield 'required flag set with non-empty string' => [
            ['required' => true],
            'foobar',
            true,
        ];
    }

    /**
     * @dataProvider validateValueForRequiredReturnsExpectedValueDataHandler
     * @test
     */
    public function validateValueForRequiredReturnsExpectedValue(array $tcaFieldConfig, $input, bool $expectation): void
    {
        self::assertSame($expectation, $this->subject->_call('validateValueForRequired', $tcaFieldConfig, $input));
    }
}
