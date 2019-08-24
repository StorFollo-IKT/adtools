<?php
namespace datagutten\adtools\tests;

use datagutten\adtools;
use PHPUnit\Framework\TestCase;


class adtoolsWriteTest extends TestCase
{
    /**
     * @var adtools\adtools
     */
    public $adtools;
    public static function setUpBeforeClass(): void
    {
        load_data::load_base_data();
    }

    public function setUp(): void
    {
        set_include_path(__DIR__);
        $this->adtools=new adtools\adtools('test');
        load_data::load_test_data();
    }

    /**
     * @throws adtools\exceptions\LdapException
     * @throws adtools\exceptions\MultipleHitsException
     * @throws adtools\exceptions\NoHitsException
     */
    public function testMove()
    {
        $new = $this->adtools->move('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'OU=move,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertEquals('CN=user2,OU=move,OU=adtools-test,OU=Test,DC=example,DC=com', $new);
        $result = $this->adtools->ldap_query('(displayName=user2)', array('base_dn'=>'ou=Test,dc=example,dc=com'));
        $this->assertEquals('cn=user2,ou=move,ou=adtools-test,ou=Test,dc=example,dc=com', $result);
        ldap_delete($this->adtools->ad, $new);
    }

    /**
     * @throws adtools\exceptions\LdapException
     */
    public function testMoveNonExisting()
    {
        $this->expectException(adtools\exceptions\LdapException::class);
        $this->adtools->move('CN=user3,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'OU=move,OU=adtools-test,OU=Test,DC=example,DC=com');
    }

    /**
     * @throws adtools\exceptions\LdapException
     */
    public function testChange_password()
    {
        $this->adtools->change_password('CN=user1,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'test2');
        $this->adtools->change_password('CN=user1 ,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'test2', true);
        $result = $this->adtools->find_object('user1', 'OU=adtools-test,OU=Test,DC=example,DC=com','user', array('pwdLastSet'));
        $this->assertEquals('0', $result);
    }

    public function tearDown(): void
    {
        load_data::delete('OU=adtools-test,OU=Test,DC=example,DC=com');
    }
}
