<?php

namespace storfollo\adtools\tests;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;


class load_data
{
    public static function load($file)
    {
        $domains = require 'domains.php';
        $domain = $domains['test'];
        $process = new Process(['ldapadd','-c' ,'-H ldap://'. $domain['dc'], '-D', 'cn=admin,dc=example,dc=com', '-w', 'test', '-f', $file]);
        $process->run();
        if($process->getExitCode()==68)
            return;
        if (!$process->isSuccessful()) {
            var_dump($process->getCommandLine());
            throw new ProcessFailedException($process);
        }
    }

    public static function delete($dn=null)
    {
        $domains = require 'domains.php';
        $domain = $domains['test'];
        if(empty($dn))
            $dn = $domain['dn'];

        $process = new Process(['ldapdelete', '-r', '-H ldap://'. $domain['dc'], '-D', 'cn=admin,dc=example,dc=com' , '-w', 'test', $dn]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public static function load_base_data()
    {
        self::load(__DIR__ . '/slapd/base.ldif');
    }

    public static function load_test_data()
    {
        self::load(__DIR__.'/slapd/adtools_test_data_converted.ldif');
    }
    public static function load_group_test_data()
    {
        self::load(__DIR__.'/slapd/groups_converted.ldf');
    }
}