<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 26.07.2019
 * Time: 13:32
 */

namespace datagutten\adtools\tests;

use datagutten\adtools;
use Exception;
use PHPUnit\Framework\TestCase;


class adtoolsTest extends TestCase
{
    /**
     * @var adtools\adtools
     */
    public $adtools;
    public static function setUpBeforeClass(): void
    {
        load_data::load_base_data();
        load_data::load_test_data();
    }
    public static function tearDownAfterClass(): void
    {
        load_data::delete();
    }

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->adtools=new adtools\adtools();
        $this->adtools->connect_and_bind('cn=admin,dc=example,dc=com', 'test', 'localhost');
    }

    public function testConnect()
    {
        set_include_path(__DIR__);
        $adtools=new adtools\adtools('test');
        if (version_compare(phpversion(), '8.1', '<'))
            $this->assertIsResource($adtools->ad);
        else
            $this->assertIsObject($adtools->ad);
    }

    public function testConnect_and_bind()
    {
        if (version_compare(phpversion(), '8.1', '<'))
            $this->assertIsResource($this->adtools->ad);
        else
            $this->assertIsObject($this->adtools->ad);
    }

    public function testLdap_query()
    {
        $result = $this->adtools->ldap_query('(objectclass=*)', array('base_dn'=>'OU=Test,DC=example,DC=com', 'subtree'=>false));
        $this->assertEquals('ou=adtools-test,ou=Test,dc=example,dc=com', $result);
    }

    public function MultipleHitsException()
    {
        $this->expectException(adtools\exceptions\MultipleHitsException::class);
        $this->adtools->ldap_query('(objectclass=user)', array('base_dn'=>'OU=Test,DC=example,DC=com', 'single_result'=>true));
    }

/*
    public function testQuery()
    {

    }

    public function testExtract_field()
    {

    }
*/

/*
    public function testLogin_form()
    {

    }*/
}
