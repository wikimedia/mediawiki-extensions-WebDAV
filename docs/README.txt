==MediaWiki prequisites==

You will need to install the SabreDAV library. You can do this using 'composer'.
Execute

 composer install

in the extension root directory.

See [http://sabre.io/dav/install/] for details.

At the moment we use the version 1.8 of SabreDAV, because 2.x is not compatible
to PHP versions lower than 5.4. Unfortunately some of our customers still use
5.3 and can not be updated in a short term.

==Considerations==
As MediaWiki does not make a difference between underscores and spaces in wiki
article names and file names we normalise all paths to user underscores instead
of spaces

==TODO==
===Authentication===
At the moment we supprt SSO environments ''or'' HTTP Base Auth
(also for LDAPAuth MW setups witout "autoauth").
HTTP Base Auth has some issues with some clients. Maybe it would be better to
use HTTP Digest Auth.

==Setup on Windows==

The webdav.php file needs to be available in the ScriptPath. This can be done with a symlink

 mklink webdav.php extensions\WebDAV\webdav.php

==Setup on IIS==

You may need to configure the HTTP Verbs in the PHP FastCGI configuration!

[https://forum.owncloud.org/viewtopic.php?f=26&t=18287#p48625]

 Open IIS and navigate down the tree to the IIS Manager.
 Open your host in the Connections segment of the screen.
 Double click Handler Mappings.
 Find the PHP module you used (In my case I used the windows installer and have "PHP53_via_FastCGI" and right click it and select Edit.
 Click Request Restrictions.
 Go to the Verbs tab.
 Select the "ALL VERBS" radio dial.

==Using MediaWiki Authentication / aka Non-SSO-Setup==
Just add the following to you LocalSettings.php

$wgWebDAVAuthType = 'mw';

or for token auth:

$wgWebDAVAuthType = 'token';

==Notes==
* Multi-upload works
* When a new Media-File is created a 0 byte version will be published at first and then be overwritten by the correct one.
** This is due to an issue in FSFileBackend::doGetLocalCopyMulti where a new temp file is being created but PHP's copy method is to slow, so the new temp file has 0 bytes when MimeMagic::guessMimeType tries to fetch it
* Upload of large files might work if PHP's memory_limit is set properly
* Underscores in filenames are automatically replaced with spaces in the output

==Troubleshooting==
* If access on Windows 7+ is slow follow https://support.microsoft.com/en-us/kb/2445570
* If "The network name cannot be found" on Windows 8 follow http://serverfault.com/a/523093
* http://superuser.com/questions/678075/cant-mount-remote-directory-using-webdav

===Mounting drive to Windows-Explorer===
To be able to mount a drive to Windows-Explorer, make sure the endpoint gets
serverd by a secure connection (HTTPS). Otherwise Windows will not send any
user credentials using HTTB BaseAuth!

It also works with a self signed certificate!

Also make sure the enpoint gets served from a local network hostname, as Windows
does not send user credentials to a hostname with dots in it!

 Not OK: https://wiki.company.local, https://10.10.45.223
 OK:     https://wiki