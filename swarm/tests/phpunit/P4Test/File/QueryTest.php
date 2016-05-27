<?php
/**
 * Test methods for the P4 File Query class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\File;

use P4Test\TestCase;
use P4\File\File;
use P4\Spec\Change;
use P4\File\Query as FileQuery;

class QueryTest extends TestCase
{
    /**
     * Provide test array runner, as most of the included test methods
     * require almost identical infrastructure for checking success/failure.
     *
     * @param  array  $tests  The array of tests to run.
     * @param  array  $method The FileQuery method suffix, after 'get' and 'set'.
     */
    protected function runTests($tests, $method)
    {
        $setMethod = "set$method";
        $getMethod = "get$method";
        foreach ($tests as $test) {
            $label = $test['label'];
            $query = new FileQuery;
            try {
                $query->$setMethod($test['argument']);
                if ($test['error']) {
                    $this->fail("$label - unexpected success");
                } else {
                    $this->assertEquals(
                        $test['expected'],
                        $query->$getMethod(),
                        "$label - expected value"
                    );
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\PHPUnit\Framework\ExpectationFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                if (!$test['error']) {
                    $this->fail("$label - Unexpected exception (". get_class($e) .') :'. $e->getMessage());
                } else {
                    list($class, $error) = each($test['error']);
                    $this->assertEquals(
                        $class,
                        get_class($e),
                        "$label - expected exception class: ". $e->getMessage()
                    );
                    $this->assertEquals(
                        $error,
                        $e->getMessage(),
                        "$label - expected exception message"
                    );
                }
            }
        }
    }

    /**
     * Test out-of-the-box behaviour of filter constructors.
     */
    public function testInitialConditions()
    {
        $query = new FileQuery;
        $this->assertTrue($query instanceof FileQuery, 'Expected class.');
        $array = $query->toArray();
        $this->assertEquals(
            array(
                FileQuery::QUERY_FILTER                  => null,
                FileQuery::QUERY_SORT_BY                 => null,
                FileQuery::QUERY_SORT_REVERSE            => false,
                FileQuery::QUERY_LIMIT_FIELDS            => null,
                FileQuery::QUERY_LIMIT_TO_CHANGELIST     => null,
                FileQuery::QUERY_LIMIT_TO_NEEDS_RESOLVE  => false,
                FileQuery::QUERY_LIMIT_TO_OPENED         => false,
                FileQuery::QUERY_MAX_FILES               => null,
                FileQuery::QUERY_START_ROW               => null,
                FileQuery::QUERY_FILESPECS               => null,
            ),
            $query->toArray(),
            'Expected options as array'
        );

        $query = FileQuery::create();
        $this->assertTrue($query instanceof FileQuery, 'Expected class.');
        $array = $query->toArray();
        $this->assertEquals(
            array(
                FileQuery::QUERY_FILTER                  => null,
                FileQuery::QUERY_SORT_BY                 => null,
                FileQuery::QUERY_SORT_REVERSE            => false,
                FileQuery::QUERY_LIMIT_FIELDS            => null,
                FileQuery::QUERY_LIMIT_TO_CHANGELIST     => null,
                FileQuery::QUERY_LIMIT_TO_NEEDS_RESOLVE  => false,
                FileQuery::QUERY_LIMIT_TO_OPENED         => false,
                FileQuery::QUERY_MAX_FILES               => null,
                FileQuery::QUERY_START_ROW               => null,
                FileQuery::QUERY_FILESPECS               => null,
            ),
            $query->toArray(),
            'Expected options as array'
        );
    }

    /**
     * Test behaviour of filter constructors.
     */
    public function testConstructorOptions()
    {
        $badOptions = array(
            FileQuery::QUERY_FILTER                  => -1,
            FileQuery::QUERY_SORT_BY                 => -1,
            FileQuery::QUERY_SORT_REVERSE            => -1,
            FileQuery::QUERY_LIMIT_FIELDS            => -1,
            FileQuery::QUERY_LIMIT_TO_CHANGELIST     => -1,
            FileQuery::QUERY_LIMIT_TO_NEEDS_RESOLVE  => -1,
            FileQuery::QUERY_LIMIT_TO_OPENED         => -1,
            FileQuery::QUERY_MAX_FILES               => -1,
            FileQuery::QUERY_START_ROW               => -1,
            FileQuery::QUERY_FILESPECS               => -1,
        );

        try {
            $query = new FileQuery($badOptions);
            $this->fail('Unexpected success with bad options.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals(
                'Cannot set filter; argument must be a P4\File\Filter, a string, or null.',
                $e->getMessage(),
                'Expected exception'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected exception ('. get_class($e) .') :'. $e->getMessage());
        }

        $goodOptions = array(
            FileQuery::QUERY_FILTER                  => new \P4\File\Filter('filter'),
            FileQuery::QUERY_SORT_BY                 => 'column',
            FileQuery::QUERY_SORT_REVERSE            => true,
            FileQuery::QUERY_LIMIT_FIELDS            => array('d'),
            FileQuery::QUERY_LIMIT_TO_CHANGELIST     => 5,
            FileQuery::QUERY_LIMIT_TO_NEEDS_RESOLVE  => true,
            FileQuery::QUERY_LIMIT_TO_OPENED         => true,
            FileQuery::QUERY_MAX_FILES               => 10,
            FileQuery::QUERY_START_ROW               => 5,
            FileQuery::QUERY_FILESPECS               => array('filespec'),
        );
        $query = new FileQuery($goodOptions);
        $expected = $goodOptions;
        $expected[FileQuery::QUERY_SORT_BY] = array('column' => null);
        $this->assertEquals(
            $expected,
            $query->toArray(),
            'Expected options'
        );

        $query = FileQuery::create($goodOptions);
        $this->assertEquals(
            $expected,
            $query->toArray(),
            'Expected options'
        );
    }

    /**
     * Test get/set Filter attribute.
     */
    public function testGetSetFilter()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': bool',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException' =>
                        'Cannot set filter; argument must be a P4\File\Filter, a string, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': numeric',
                'argument'  => 1,
                'error'     => array(
                    'InvalidArgumentException' =>
                        'Cannot set filter; argument must be a P4\File\Filter, a string, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => 'foobar',
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => array(
                    'InvalidArgumentException' =>
                        'Cannot set filter; argument must be a P4\File\Filter, a string, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': some object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' =>
                        'Cannot set filter; argument must be a P4\File\Filter, a string, or null.'
                ),
                'expected'  => null,
            )
        );

        // This block is needed here because runTests does not support non P4\File\Filter
        // objects for the argument.
        foreach ($tests as $test) {
            $label = $test['label'];
            $query = new FileQuery;
            try {
                $query->setFilter($test['argument']);
                if (isset($test['addFilter'])) {
                    foreach ((array) $test['addFilter'] as $filter) {
                        $query->addFilter($filter);
                    }
                }

                $expr = $query->getFilter() instanceof \P4\File\Filter
                      ? $query->getFilter()->getExpression()
                      : $query->getFilter();
                $this->assertEquals(
                    $test['expected'],
                    $expr,
                    "$label - expected value"
                );
            } catch (\Exception $e) {
                if ($test['error']) {
                    $this->assertEquals(
                        'Cannot set filter; argument must be a P4\File\Filter, a string, or null.',
                        $test['error']['InvalidArgumentException'],
                        "$label - expected InvalidArgumentException"
                    );
                } else {
                    $this->fail($e->getMessage());
                }
            }
        }
    }

    /**
     * Test get/set SortBy attribute.
     */
    public function testGetSetSortBy()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': bool',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; argument must be an array, string, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': numeric',
                'argument'  => 1,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; argument must be an array, string, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => array('foobar' => null),
            ),
            array(
                'label'     => __LINE__ .': array with null',
                'argument'  => array(null),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid sort clause provided.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array with numeric',
                'argument'  => array(1),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid sort clause provided.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array with object',
                'argument'  => array(new \stdClass),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid sort clause provided.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, good string field',
                'argument'  => array('foobar'),
                'error'     => null,
                'expected'  => array('foobar' => null),
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, good array',
                'argument'  => array('foobar'),
                'error'     => null,
                'expected'  => array('foobar' => null),
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, null string field',
                'argument'  => array(null => null),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid field name in clause #1.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, bad string field',
                'argument'  => array('#foobar'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid field name in clause #1.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, field with bogus options',
                'argument'  => array('foobar' => new \stdClass()),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid sort options in clause #1.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, field with unknown option',
                'argument'  => array('foobar' => array('fred')),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid sort options in clause #1.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, field with overlapping a|d options',
                'argument'  => array(
                    'foobar' => array(FileQuery::SORT_ASCENDING, FileQuery::SORT_DESCENDING)
                ),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; invalid sort options in clause #1.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array, 1 clause, field with valid options',
                'argument'  => array(
                    'foobar' => array(FileQuery::SORT_DESCENDING)
                ),
                'error'     => null,
                'expected'  => array('foobar' => array(FileQuery::SORT_DESCENDING)),
            ),
            array(
                'label'     => __LINE__ .': array, 2 clauses, 1 array + 1 string',
                'argument'  => array(
                    'foobar' => array(FileQuery::SORT_DESCENDING),
                    'oobleck'
                ),
                'error'     => null,
                'expected'  => array(
                    'foobar' => array(FileQuery::SORT_DESCENDING),
                    'oobleck' => null
                ),
            ),
            array(
                'label'     => __LINE__ .': array, 3 clauses',
                'argument'  => array('foobar', 'test', 'oobleck'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; argument contains more than 2 clauses.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set sort by; argument must be an array, string, or null.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'SortBy');
    }

    /**
     * Test get/set ReverseOrder attribute.
     */
    public function testGetSetReverseOrder()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric non-zero',
                'argument'  => 1,
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric zero',
                'argument'  => 0,
                'error'     => null,
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': string numeric',
                'argument'  => '1',
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => null,
                'expected'  => true,
            ),
        );

        $this->runTests($tests, 'ReverseOrder');
    }

    /**
     * Test get/set LimitFields attribute.
     */
    public function testGetSetLimitFields()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException'
                        => 'Cannot set limiting fields; argument must be a string, an array, or null.'
                ),
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric',
                'argument'  => 1,
                'error'     => array(
                    'InvalidArgumentException'
                        => 'Cannot set limiting fields; argument must be a string, an array, or null.'
                ),
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => array('foobar'),
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => null,
                'expected'  => array('foobar'),
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException'
                        => 'Cannot set limiting fields; argument must be a string, an array, or null.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'LimitFields');
    }

    /**
     * Test get/set LimitToNeedsResolve attribute.
     */
    public function testGetSetLimitToNeedsResolve()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to needs resolve; argument must be a boolean.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric non-zero',
                'argument'  => 1,
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric zero',
                'argument'  => 0,
                'error'     => null,
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': string numeric',
                'argument'  => '1',
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to needs resolve; argument must be a boolean.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to needs resolve; argument must be a boolean.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'LimitToNeedsResolve');
    }

    /**
     * Test get/set LimitToOpened attribute.
     */
    public function testGetSetLimitToOpened()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to opened files; argument must be a boolean.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric non-zero',
                'argument'  => 1,
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric zero',
                'argument'  => 0,
                'error'     => null,
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': string numeric',
                'argument'  => '1',
                'error'     => null,
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to opened files; argument must be a boolean.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to opened files; argument must be a boolean.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'LimitToOpened');
    }

    /**
     * Test get/set LimitToChangelist attribute.
     */
    public function testGetSetLimitToChangelist()
    {
        $change = new Change;
        $change->setId(123);

        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to changelist; argument must be a changelist id,'
                        . ' a P4\Spec\Change object, or null.'
                ),
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': positive numeric',
                'argument'  => 1,
                'error'     => null,
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': negative numeric',
                'argument'  => -1,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to changelist; argument must be a changelist id,'
                        . ' a P4\Spec\Change object, or null.'
                ),
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': zero',
                'argument'  => 0,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to changelist; argument must be a changelist id,'
                        . ' a P4\Spec\Change object, or null.'
                ),
                'expected'  => 0,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to changelist; argument must be a changelist id,'
                        . ' a P4\Spec\Change object, or null.'
                ),
                'expected'  => 0,
            ),
            array(
                'label'     => __LINE__ .': default changelist',
                'argument'  => 'default',
                'error'     => null,
                'expected'  => 'default',
            ),
            array(
                'label'     => __LINE__ .': string numeric',
                'argument'  => '1',
                'error'     => null,
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to changelist; argument must be a changelist id,'
                        . ' a P4\Spec\Change object, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': some object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set limit to changelist; argument must be a changelist id,'
                        . ' a P4\Spec\Change object, or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': P4\Spec\Change object',
                'argument'  => $change,
                'error'     => null,
                'expected'  => 123,
            ),
        );

        $this->runTests($tests, 'LimitToChangelist');
    }

    /**
     * Test get/set StartRow attribute.
     */
    public function testGetSetStartRow()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set start row; argument must be a positive integer or null.'
                ),
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': positive numeric',
                'argument'  => 1,
                'error'     => null,
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': negative numeric',
                'argument'  => -1,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set start row; argument must be a positive integer or null.'
                ),
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': zero',
                'argument'  => 0,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string numeric',
                'argument'  => '1',
                'error'     => null,
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set start row; argument must be a positive integer or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set start row; argument must be a positive integer or null.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'StartRow');
    }

    /**
     * Test get/set MaxFiles attribute.
     */
    public function testGetSetMaxFiles()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set max files; argument must be a positive integer or null.'
                ),
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': positive numeric',
                'argument'  => 1,
                'error'     => null,
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': negative numeric',
                'argument'  => -1,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set max files; argument must be a positive integer or null.'
                ),
                'expected'  => false,
            ),
            array(
                'label'     => __LINE__ .': zero',
                'argument'  => 0,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string numeric',
                'argument'  => '1',
                'error'     => null,
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set max files; argument must be a positive integer or null.'
                ),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set max files; argument must be a positive integer or null.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'MaxFiles');
    }

    /**
     * Test get/set of filespecs.
     */
    public function testGetSetFilespecs()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => null,
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': boolean',
                'argument'  => true,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set filespecs; argument must be a string, an array, or null.'
                ),
                'expected'  => true,
            ),
            array(
                'label'     => __LINE__ .': numeric',
                'argument'  => 1,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set filespecs; argument must be a string, an array, or null.'
                ),
                'expected'  => 1,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'foobar',
                'error'     => null,
                'expected'  => array('foobar'),
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('foobar'),
                'error'     => null,
                'expected'  => array('foobar'),
            ),
            array(
                'label'     => __LINE__ .': hash',
                'argument'  => array('foobar' => 'testing'),
                'error'     => null,
                'expected'  => array('testing'),
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot set filespecs; argument must be a string, an array, or null.'
                ),
                'expected'  => null,
            ),
        );

        $this->runTests($tests, 'Filespecs');
    }

    /**
     * Test addFilespec().
     */
    public function testAddFilespec()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => array('InvalidArgumentException' => 'Cannot add filespec; argument must be a string.'),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': numeric',
                'argument'  => 1,
                'error'     => array('InvalidArgumentException' => 'Cannot add filespec; argument must be a string.'),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'string',
                'error'     => null,
                'expected'  => array('string'),
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('string'),
                'error'     => array('InvalidArgumentException' => 'Cannot add filespec; argument must be a string.'),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array('InvalidArgumentException' => 'Cannot add filespec; argument must be a string.'),
                'expected'  => null,
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $query = new FileQuery;
            try {
                $query->addFilespec($test['argument']);
                if ($test['error']) {
                    $this->fail("$label - unexpected success");
                } else {
                    $this->assertEquals(
                        $test['expected'],
                        $query->getFilespecs(),
                        "$label - expected value"
                    );
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\PHPUnit\Framework\ExpectationFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                if (!$test['error']) {
                    $this->fail("$label - Unexpected exception (". get_class($e) .') :'. $e->getMessage());
                } else {
                    list($class, $error) = each($test['error']);
                    $this->assertEquals(
                        $class,
                        get_class($e),
                        "$label - expected exception class: ". $e->getMessage()
                    );
                    $this->assertEquals(
                        $error,
                        $e->getMessage(),
                        "$label - expected exception message"
                    );
                }
            }
        }

        $query = FileQuery::create()->addFilespec('one')->addFilespec('two')->addFilespec('three');
        $this->assertEquals(
            array('one', 'two', 'three'),
            $query->getFilespecs(),
            'Expected filespecs after add chain'
        );
    }

    /**
     * Test addFilespecs().
     */
    public function testAddFilespecs()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null',
                'argument'  => null,
                'error'     => array('InvalidArgumentException' => 'Cannot add filespecs; argument must be an array.'),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': numeric',
                'argument'  => 1,
                'error'     => array('InvalidArgumentException' => 'Cannot add filespecs; argument must be an array.'),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': string',
                'argument'  => 'string',
                'error'     => array('InvalidArgumentException' => 'Cannot add filespecs; argument must be an array.'),
                'expected'  => null,
            ),
            array(
                'label'     => __LINE__ .': array',
                'argument'  => array('string'),
                'error'     => null,
                'expected'  => array('string'),
            ),
            array(
                'label'     => __LINE__ .': object',
                'argument'  => new \stdClass,
                'error'     => array('InvalidArgumentException' => 'Cannot add filespecs; argument must be an array.'),
                'expected'  => null,
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $query = new FileQuery;
            try {
                $query->addFilespecs($test['argument']);
                if ($test['error']) {
                    $this->fail("$label - unexpected success");
                } else {
                    $this->assertEquals(
                        $test['expected'],
                        $query->getFilespecs(),
                        "$label - expected value"
                    );
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\PHPUnit\Framework\ExpectationFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                if (!$test['error']) {
                    $this->fail("$label - Unexpected exception (". get_class($e) .') :'. $e->getMessage());
                } else {
                    list($class, $error) = each($test['error']);
                    $this->assertEquals(
                        $class,
                        get_class($e),
                        "$label - expected exception class: ". $e->getMessage()
                    );
                    $this->assertEquals(
                        $error,
                        $e->getMessage(),
                        "$label - expected exception message"
                    );
                }
            }
        }

        $query = new FileQuery;
        $query->addFilespecs(array('one', 'two', 'three'));
        $this->assertEquals(
            array('one', 'two', 'three'),
            $query->getFilespecs(),
            'Expected filespecs after initial add'
        );

        // add array containing a dupe
        $query->addFilespecs(array('two', 'four', 'five'));
        $this->assertEquals(
            array('one', 'two', 'three', 'two', 'four', 'five'),
            $query->getFilespecs(),
            'Expected filespecs after 2nd add'
        );
    }

    /**
     * Test getFstatFlags().
     */
    public function testGetFstatFlags()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': fresh query',
                'query'     => new FileQuery,
                'expected'  => array('-Oal'),
            ),
            array(
                'label'     => __LINE__ .': reversed order',
                'query'     => FileQuery::create()->setReverseOrder(true),
                'expected'  => array('-r', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by fileSize',
                'query'     => FileQuery::create()->setSortBy(FileQuery::SORT_FILE_SIZE),
                'expected'  => array('-Ss', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by fileType',
                'query'     => FileQuery::create()->setSortBy(FileQuery::SORT_FILE_TYPE),
                'expected'  => array('-St', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by date',
                'query'     => FileQuery::create()->setSortBy(FileQuery::SORT_DATE),
                'expected'  => array('-Sd', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by head rev',
                'query'     => FileQuery::create()->setSortBy(FileQuery::SORT_HEAD_REV),
                'expected'  => array('-Sr', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by have rev',
                'query'     => FileQuery::create()->setSortBy(FileQuery::SORT_HAVE_REV),
                'expected'  => array('-Sh', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by attribute',
                'query'     => FileQuery::create()->setSortBy(array('field')),
                'expected'  => array('-S', 'attr-field=a', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by attribute, then date',
                'query'     => FileQuery::create()->setSortBy(array('field', FileQuery::SORT_DATE)),
                'expected'  => array('-S', 'attr-field=a,REdate=a', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': sort by attribute descending, then date',
                'query'     => FileQuery::create()->setSortBy(
                    array(
                        'field' => array(FileQuery::SORT_DESCENDING),
                        FileQuery::SORT_DATE
                    )
                ),
                'expected'  => array('-S', 'attr-field=d,REdate=a', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': limit to opened',
                'query'     => FileQuery::create()->setLimitToOpened(true),
                'expected'  => array('-Ro', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': limit to needs resolve',
                'query'     => FileQuery::create()->setLimitToNeedsResolve(true),
                'expected'  => array('-Ru', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': limit to changelist 1',
                'query'     => FileQuery::create()->setLimitToChangelist(1),
                'expected'  => array('-e', 1, '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': limit to default changelist',
                'query'     => FileQuery::create()->setLimitToChangelist('default'),
                'expected'  => array('-e', 'default', '-Ro', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': limit fields',
                'query'     => FileQuery::create()->setLimitFields(array('depotFile', 'headRev')),
                'expected'  => array('-T', 'depotFile headRev', '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': max files',
                'query'     => FileQuery::create()->setMaxFiles(7),
                'expected'  => array('-m', 7, '-Oal'),
            ),
            array(
                'label'     => __LINE__ .': filter',
                'query'     => FileQuery::create()->setFilter('attr-title=test')
                                                      ->setMaxFiles(7)
                                                      ->setLimitFields(array('depotFile', 'headRev'))
                                                      ->setLimitToChangelist(1)
                                                      ->setLimitToOpened(true)
                                                      ->setLimitToNeedsResolve(true)
                                                      ->setSortBy(FileQuery::SORT_HAVE_REV)
                                                      ->setReverseOrder(true),
                'expected'  => array(
                    '-F', 'attr-title=test',
                    '-T', 'depotFile headRev',
                    '-m', 7,
                    '-e', 1,
                    '-Ro',
                    '-Ru',
                    '-Sh',
                    '-r',
                    '-Oal'
                ),
            ),

            array(
                'label'     => __LINE__ .': all',
                'query'     => FileQuery::create()->setFilter('attr-title=test'),
                'expected'  => array('-F', 'attr-title=test', '-Oal'),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $this->assertEquals(
                $test['expected'],
                $test['query']->getFstatFlags(),
                "$label - expected flags"
            );
        }
    }
}
