<?php namespace IET_OU\LdapOu;

/**
 *
 * @copyright  © 2012, 2013, 2019 The Open University (IET).
 */

use Zend\Ldap\Ldap;
use Dotenv\Dotenv;
use Composer\EventDispatcher\Event;

class LdapOu
{
    const DOT_ENV = __DIR__ . '/..';
    const JSON_FILE = __DIR__ . '/../data/ldap-search.json';
    const SCHEMA_FILE = __DIR__ . './../data/ldap-schema.txt';

    const HOST = 'DC1.open.ac.uk';
    const PORT = 3268;
    const BASE_DN = 'DC=Open,DC=AC,DC=UK';
    const FILTER_FORMAT = '(&(objectClass=user)(sAMAccountName=%s))';

    const EXAMPLE_OUCU = 'xyzz123';

    protected static $io;

    public static function test(Event $event)
    {
        self::$io = $event->getIO();
        self::$io->warning(__METHOD__);

        if (self::$io->isVerbose()) {
            print_r([ \get_class($event), $event->getName(), $event->getArguments(), ]);
        }

        $dotenv = Dotenv::create(self::DOT_ENV);
        $dotenv->load();

        $ldap = new \Zend\Ldap\Ldap([
          'host' => self::getenvDefault('LDAP_OU_HOST', self::HOST),
          'port' => self::getenvDefault('LDAP_OU_PORT', self::PORT),
          'username' => self::getenvDefault('LDAP_OU_USER'), // $ldaprdn,
          'password' => self::getenvDefault('LDAP_OU_PASS'), // $ldappass,
          'baseDn' => self::getenvDefault('LDAP_OU_BASE_DN', self::BASE_DN),
          'accountFilterFormat' => self::getenvDefault('LDAP_OU_FILTER_FORMAT', self::FILTER_FORMAT),
        ]);

        if (self::$io->isVerbose()) {
            self::$io->info(\get_class($ldap));
            print_r($ldap->getOptions());
        }

        $ldap->bind();

        if (self::getenvDefault('LDAP_OU_SCHEMA')) {
            $schema = $ldap->getSchema();
            file_put_contents( self::SCHEMA_FILE, print_r( $schema, true ));
        }

        // Staff OUCUs: ...
        $query = sprintf(self::FILTER_FORMAT, self::getenvDefault('LDAP_OUCU', self::EXAMPLE_OUCU));

        $result = $ldap->search($query, null, Ldap::SEARCH_SCOPE_SUB, [ '*' ] );

        // Empty! $result = $ldap->search('(&(objectClass=student)(sAMAccountName=abc123))', null, Ldap::SEARCH_SCOPE_SUB, [ '*' ] );

        $lastLogin = $result->toArray()[ 0 ][ 'lastlogontimestamp' ][ 0 ];
        $oucu = $result->toArray()[ 0 ][ 'samaccountname' ];
        $samaccounttype = $result->toArray()[ 0 ][ 'samaccounttype' ];

        if (self::$io->isVeryVerbose()) {
            print_r( $result->toArray() );
        }
        print_r([ $lastLogin , sha1( (string) $lastLogin /* . $oucu . $samaccounttype */ ) ]);

        file_put_contents( self::JSON_FILE, json_encode( $result->toArray(), JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR ));
    }

    protected static function getenvDefault($varname, $default = null)
    {
        $result = \getenv($varname) ? \getenv($varname) : $default;

        self::$io->warning("Getenv, $varname: $result", $context = [ ]);

        return $result;
    }
}
