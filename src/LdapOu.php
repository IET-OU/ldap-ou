<?php namespace IET_OU\LdapOu;

/**
 * Lightweight Directory Access Protocol, for The OU.
 *
 * @copyright  © 2012, 2013, 2019 The Open University (IET).
 * @author     Nick Freear, 05-Feb-2019.
 */

use Zend\Ldap\Ldap;
use Dotenv\Dotenv;
use Composer\EventDispatcher\Event;

class LdapOu
{
    const DOT_ENV = __DIR__ . '/..';
    const JSON_FILE = __DIR__ . '/../data/ldap-search.json';
    const SCHEMA_FILE = __DIR__ . '/../data/ldap-schema.txt';

    // Defaults.
    const HOST = 'DC1.open.ac.uk';
    const PORT = 3268;
    const BASE_DN = 'DC=Open,DC=AC,DC=UK';
    const FILTER_FORMAT = '(&(objectClass=user)(sAMAccountName=%s))';

    const EXAMPLE_OUCU = 'xyzz123';

    const ATTR_DEFAULT = 'cn|dn|samaccountname|samaccounttype|name|department|mail|'.
        'extensionattribute2|lastlogontimestamp|whencreated|whenchanged|objectclass';
    const ATTR_ALL = '*';
    const ATTR_SPECIAL = '*|+';

    protected static $io;
    protected static $ldap;

    protected static $searchResult;

    public static function test(Event $event)
    {
        self::$io = $event->getIO();
        self::$io->warning(__METHOD__);

        self::debug([ \get_class($event), $event->getName(), $event->getArguments(), ]);

        self::loadDotenv();

        self::connect();

        if (self::$io->isVerbose()) {
            self::$io->info(\get_class(self::$ldap));
            print_r(self::$ldap->getOptions());
        }

        self::dumpSchema();

        $result = self::searchByOucu();

        self::debug('Email :~ ' .  self::getEmailAddress());

        file_put_contents( self::JSON_FILE, json_encode( $result->toArray(), JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR ));
    }

    protected static function getenvDefault($varname, $default = null)
    {
        $result = \getenv($varname) ? \getenv($varname) : $default;

        self::debug("Getenv, $varname: $result");

        return $result;
    }

    protected static function loadDotenv()
    {
        $dotenv = Dotenv::create(self::DOT_ENV);
        $dotenv->load();
    }

    public static function connect()
    {
        self::$ldap = new \Zend\Ldap\Ldap([
          'host' => self::getenvDefault('LDAP_OU_HOST', self::HOST),
          'port' => self::getenvDefault('LDAP_OU_PORT', self::PORT),
          'username' => self::getenvDefault('LDAP_OU_USER'), // $ldaprdn,
          'password' => self::getenvDefault('LDAP_OU_PASS'), // $ldappass,
          'baseDn' => self::getenvDefault('LDAP_OU_BASE_DN', self::BASE_DN),
          'accountFilterFormat' => self::getenvDefault('LDAP_OU_FILTER_FORMAT', self::FILTER_FORMAT),
        ]);

        return self::$ldap->bind();
    }

    protected static function dumpSchema()
    {
        if (self::getenvDefault('LDAP_OU_SCHEMA')) {
            $schema = self::$ldap->getSchema();
            file_put_contents( self::SCHEMA_FILE, print_r( $schema, true ));
        }
    }

    public static function search($filter, $scope = Ldap::SEARCH_SCOPE_SUB, $attributes = self::ATTR_DEFAULT)
    {
        $arAttributes = is_string($attributes) ? explode('|', $attributes) : $attributes;

        self::$searchResult = self::$ldap->search( $filter, null, $scope, $arAttributes );
        self::debug( 'Last error :~ ' . self::getError() );

        // Empty! $ldap->search('(&(objectClass=student)(sAMAccountName=abc123))', null, Ldap::SEARCH_SCOPE_SUB, [ '*' ]);

        $lastLogin = self::get('lastlogontimestamp');
        $oucu = self::get('samaccountname');
        $samaccounttype = self::get('samaccounttype');

        self::debug( self::$searchResult->toArray() );
        self::debug([ $lastLogin , sha1( (string) $lastLogin /* . $oucu . $samaccounttype */ ) ]);

        return self::$searchResult;
    }

    public static function getError()
    {
        return self::$ldap->getLastError() .' '. self::$ldap->getLastErrorCode();
    }

    public static function searchByOucu($oucu = null)
    {
        $oucu = $oucu ?: self::getenvDefault('LDAP_OUCU', self::EXAMPLE_OUCU);
        $oucuFilter = sprintf(self::FILTER_FORMAT, $oucu);

        return self::search($oucuFilter);
    }

    public static function exists()
    {
        return (bool) self::$searchResult->getFirst();
    }

    public static function getEmailAddress()
    {
        return self::get('mail');
    }

    public static function get($key)
    {
        return self::exists() ? self::$searchResult->toArray()[ 0 ][ $key ][ 0 ] : null;
    }

    protected static function debug($obj)
    {
        if (self::$io && self::$io->isVerbose()) {
            if (is_string($obj)) {
                self::$io->warning($obj);
            } else {
                print_r($obj);
            }
        }
    }
}
