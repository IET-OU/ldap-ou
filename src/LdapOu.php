<?php namespace IET_OU\LdapOu;

/**
 * Lightweight Directory Access Protocol, for The OU.
 *
 * @copyright  © 2012, 2013, 2019 The Open University (IET).
 * @author     Nick Freear, 05-Feb-2019.
 * @link https://docs.zendframework.com/zend-ldap/api/
 * @link https://getcomposer.org/apidoc/1.6.2/Composer/IO/ConsoleIO.html
 * @link https://getcomposer.org/apidoc/1.6.2/Composer/EventDispatcher/Event.html
 */

use Zend\Ldap\Ldap;
use Dotenv\Dotenv;
use Composer\EventDispatcher\Event;

class LdapOu
{
    const DOT_ENV = __DIR__ . '/..';
    const JSON_FILE = __DIR__ . '/../data/ldap-search.json';
    const SCHEMA_FILE = __DIR__ . '/../data/ldap-schema.txt';
    const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR;

    // Defaults.
    const HOST = 'DC1.open.ac.uk';
    const PORT = 3268;
    const BASE_DN = 'DC=Open,DC=AC,DC=UK';
    const FILTER_FORMAT = '(&(objectClass=user)(sAMAccountName=%s))';
    const TIMEOUT_SEC = 5;

    const EXAMPLE_OUCU = 'xyzz123';

    const ATTR_DEFAULT = 'cn|dn|samaccountname|samaccounttype|name|department|mail|'.
        'extensionattribute2|extensionattribute4|sn|title|lastlogontimestamp|whencreated|whenchanged|objectclass';
    const ATTR_MIN = 'cn|dn|samaccountname|mail|lastlogontimestamp';
    const ATTR_ALL = '*';
    const ATTR_SPECIAL = '*|+';

    const RE_ARG_OUCU = '/--oucu=(?P<arg>[a-z]\w+\d)/';

    protected static $io;
    protected static $event;
    protected static $ldap;

    protected static $searchResult;

    public static function cliTest(Event $event)
    {
        self::$event = $event;
        self::$io = $event->getIO();
        self::$io->warning(__METHOD__);

        $oucu = self::argv(self::RE_ARG_OUCU, 'OUCU');

        self::debug([ \get_class($event), $event->getName(), $event->getArguments(), ]);

        try {
            self::loadDotenv();

            self::connect();

            if (self::$io->isVerbose()) {
                self::$io->info(\get_class(self::$ldap));
                // print_r(self::$ldap->getOptions());
            }

            self::dumpSchema();

            $result = self::searchByOucu($oucu, self::$io->isVeryVerbose() ? self::ATTR_ALL : self::ATTR_MIN);

            self::debug('Email :~ ' .  self::getEmailAddress());

            file_put_contents(self::JSON_FILE, json_encode($result->toArray(), self::JSON_OPTIONS));
        } catch (\Exception $ex) {
            self::$io->error('ERROR: '. get_class($ex) . "\n  ". $ex->getMessage());
            exit(1);
        }
    }

    protected static function argv($regex, $label = null)
    {
        $argv = self::$event->getArguments();
        $argc = count( $argv );

        $result = $argc && preg_match($regex, $argv[ $argc - 1 ], $matches) ? $matches[ 'arg' ] : null;

        self::debug("Arg, $label: " . $result);
        return $result;
    }

    protected static function getenvDefault($varname, $default = null)
    {
        $result = \getenv($varname) ? \getenv($varname) : $default;

        self::debug("Getenv, $varname: " . ($varname === 'LDAP_OU_PASS' ? 'xxxx' : $result));

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
          'networkTimeout' => self::getenvDefault('LDAP_OU_TIMEOUT', self::TIMEOUT_SEC),
          'useSsl' => self::getenvDefault('LDAP_OU_USE_SSL', false), // Tried: 'useStartTls' => true,
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
        // $samaccounttype = self::get('samaccounttype');

        self::debug( self::$searchResult->toArray() );
        self::debug([ $lastLogin , sha1( (string) $lastLogin /* . $oucu . $samaccounttype */ ) ]);

        return self::$searchResult;
    }

    public static function getError()
    {
        return self::$ldap->getLastError() .' '. self::$ldap->getLastErrorCode();
    }

    public static function searchByOucu($oucu = null, $attributes = self::ATTR_MIN)
    {
        $oucu = $oucu ?: self::getenvDefault('LDAP_OUCU', self::EXAMPLE_OUCU);
        $oucuFilter = sprintf(self::FILTER_FORMAT, $oucu);

        return self::search($oucuFilter, Ldap::SEARCH_SCOPE_SUB, $attributes);
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
