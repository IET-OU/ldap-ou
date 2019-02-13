
# IET-OU / ldap-ou

Lightweight Directory Access Protocol (_LDAP_), for The Open University.

Based on [Zend LDAP][]. Used in Applaud.

## Install .. test

Add IET's [satis][] repository to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://embed.open.ac.uk/iet-satis/"
    }
  ]
}
```

Then at a terminal, type:

```sh
composer require iet-ou/ldap-ou
```

Standalone test:

```sh
cp -n .env.example .env
vi .env  # Edit!
composer install
composer test

composer ldap -v -- --oucu=xyzz123
```

## API

```php
use IET_OU\LdapOu\LdapOu;

# LdapOu::loadDotenv() OR equivalent
LdapOu::connect();
LdapOu::searchByOucu('xyzz123');

$email = LdapOu::getEmailAddress();
```

## Sources

 * [GitHub search, org:IET-OU ~ ldap][search]
 * [GitHub: learningdesign `../LdapOu.php`][ld]
 * [GitHub: pd-open-ac-uk `../ou_people.module`][pd]
 * [GitHub: pd-open-ac-uk `../EventRssSimplePieParser.php`][pd-2]

---
[©][c] 2012-2019 [The Open University][ou] ([Institute of Educational Technology][iet]).

[c]: https://www.open.ac.uk/copyright "Copyright © 2012, 2013, 2019 The Open University (IET). All rights reserved. NOT open sourced!"
[iet]: https://iet.open.ac.uk/
[ou]: https://www.open.ac.uk/
[composer]: https://getcomposer.org/
[satis]: https://embed.open.ac.uk/iet-satis/

[zend ldap]: https://docs.zendframework.com/zend-ldap/
[search]: https://github.com/search?q=org%3AIET-OU+ldap&type=Commits",
[ld]: https://github.com/IET-OU/learningdesign/commits/38248347/tools_phase2/src/OU/IetUtilityBundle/Library/LdapOu.php#!-Aug-2013
[pd]: https://github.com/IET-OU/pd-open-ac-uk/blob/master/sites/all/modules/custom/ou_people/ou_people.module#!-July-2012
[pd-2]: https://github.com/IET-OU/pd-open-ac-uk/blob/master/sites/all/modules/custom/parser_eventrss/includes/EventRssSimplePieParser.inc#!-June-2012
[cfc]: https://github.com/IET-OU/coldfusion-people-profiles/blob/master/_includes/cfc/people.cfc

[End]: //.
